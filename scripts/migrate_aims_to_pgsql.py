#!/usr/bin/env python3
"""Merge old MariaDB data (dhqhattian) into the LIVE PostgreSQL database (dhq_hattian_pg).

The MariaDB snapshot (29 Jan 2025 -> 11 Jan 2026) and the live PostgreSQL database
(11 Jan 2026 -> today) both use ids starting from 1, so the same id can mean two
different rows. This script therefore NEVER truncates and NEVER overwrites live rows:

  * a MariaDB row that already exists in PostgreSQL (same content) is left alone,
    or replaced with the MariaDB copy when --update-existing is given;
  * a MariaDB row that is missing is inserted with its original id when that id is
    free, otherwise with a new id above the current max id;
  * foreign keys (patient_id, invoice_id) are re-pointed to the final ids;
  * every source -> destination id pair is stored in the PostgreSQL table
    `aims_import_id_map`, so re-running the script never creates duplicates;
  * at the end the id sequence of every PostgreSQL table is set to max(id) + 1
    (a sequence that is already further ahead is left alone).

Default mode is a DRY RUN: the full merge is executed inside a transaction and then
rolled back, so every error (foreign key, length, ...) shows up but nothing is saved.
Use --run --yes to really save.
"""

from __future__ import annotations

import argparse
import os
import csv
from dataclasses import dataclass, field
from datetime import datetime
from decimal import Decimal
from pathlib import Path
from typing import Any

import pymysql
import psycopg2
from psycopg2 import sql
from psycopg2.extras import execute_batch, execute_values


MAP_TABLE = "aims_import_id_map"

INTEGER_BOUNDS = {
    "smallint": (-32768, 32767),
    "integer": (-2147483648, 2147483647),
    "bigint": (-9223372036854775808, 9223372036854775807),
}

# Lookup tables: not copied, only compared so that foreign keys are known to line up.
REFERENCE_TABLES = [
    "users",
    "departments",
    "fee_categories",
    "fee_types",
    "government_departments",
    "admission_wards",
    "patient_attendant_relations",
]
IGNORED_COMPARE_COLUMNS = {"created_at", "updated_at", "deleted_at"}


@dataclass
class TableSpec:
    name: str
    # column -> parent table whose ids were re-mapped
    fk_cols: dict[str, str]
    # columns that identify "the same row" in both databases (after FK re-mapping)
    fp_cols: list[str]


# Order matters: parents before children.
TABLE_SPECS = [
    TableSpec(
        "patients",
        {},
        ["created_at", "first_name", "last_name", "mobile", "cnic"],
    ),
    TableSpec(
        "invoices",
        {"patient_id": "patients"},
        ["created_at", "user_id", "total_amount", "patient_id"],
    ),
    TableSpec(
        "chits",
        {"patient_id": "patients"},
        ["created_at", "user_id", "fee_type_id", "amount", "patient_id"],
    ),
    TableSpec(
        "patient_tests",
        {"patient_id": "patients", "invoice_id": "invoices"},
        ["created_at", "fee_type_id", "total_amount", "patient_id", "invoice_id"],
    ),
    TableSpec(
        "admissions",
        {"patient_id": "patients", "invoice_id": "invoices"},
        ["created_at", "patient_id", "invoice_id", "unit_ward", "disease"],
    ),
]


@dataclass
class DbConfig:
    host: str
    port: int
    database: str
    user: str
    password: str


@dataclass
class TableStats:
    table: str
    source_rows: int = 0
    already_mapped: int = 0
    matched_existing: int = 0
    inserted_same_id: int = 0
    inserted_new_id: int = 0
    updated: int = 0
    skipped_missing_parent: int = 0
    dest_rows_before: int = 0
    dest_rows_after: int = 0
    remapped: list[tuple[int, int]] = field(default_factory=list)

    @property
    def accounted(self) -> int:
        return (
            self.already_mapped
            + self.matched_existing
            + self.inserted_same_id
            + self.inserted_new_id
            + self.skipped_missing_parent
        )


@dataclass
class SequenceResult:
    table: str
    column: str
    sequence: str
    max_id: int
    current_next: int
    new_next: int


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Merge MariaDB (old data) into the live PostgreSQL database without losing live rows."
    )

    parser.add_argument("--source-host", default="127.0.0.1")
    parser.add_argument("--source-port", type=int, default=3306)
    parser.add_argument("--source-db", default="dhqhattian")
    parser.add_argument("--source-user", default="root")
    parser.add_argument("--source-password", default="")

    parser.add_argument("--dest-host", default="127.0.0.1")
    parser.add_argument("--dest-port", type=int, default=5432)
    parser.add_argument("--dest-db", default="dhq_hattian_pg")
    parser.add_argument("--dest-user", default="postgres")
    parser.add_argument("--dest-password", default=os.environ.get("PGPASSWORD", ""), help="Defaults to the PGPASSWORD environment variable.")

    parser.add_argument("--batch-size", type=int, default=5000, help="Batch size for inserts.")
    parser.add_argument(
        "--update-existing",
        action="store_true",
        help="Also overwrite rows that already exist in PostgreSQL with the MariaDB copy "
        "(off by default: MariaDB is older and could revert live edits).",
    )
    parser.add_argument(
        "--sequences-only",
        action="store_true",
        help="Do not copy anything, only set every id sequence to max(id) + 1.",
    )
    parser.add_argument(
        "--report",
        default=str(Path(__file__).with_name("aims_id_remap_report.csv")),
        help="CSV file listing every row that received a different id than it had in MariaDB.",
    )
    parser.add_argument("--run", action="store_true", help="Really save. Without it: dry run (rolled back).")
    parser.add_argument("--yes", action="store_true", help="Confirm execution in run mode.")

    return parser.parse_args()


def get_mariadb_connection(cfg: DbConfig):
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def get_postgres_connection(cfg: DbConfig):
    return psycopg2.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        dbname=cfg.database,
    )


def sanitize_value(value: Any, dest_type: str) -> Any:
    if value is None:
        return None

    if dest_type == "boolean":
        if isinstance(value, bool):
            return value
        if isinstance(value, (int, Decimal)):
            return bool(value)
        if isinstance(value, str):
            lowered = value.strip().lower()
            if lowered in {"1", "t", "true", "yes", "y"}:
                return True
            if lowered in {"0", "f", "false", "no", "n"}:
                return False

    if isinstance(value, str) and dest_type in {"date", "timestamp without time zone", "timestamp with time zone"}:
        # MariaDB may contain invalid zero-dates that PostgreSQL rejects.
        if value.startswith("0000-00-00"):
            return None

    if dest_type in INTEGER_BOUNDS:
        if isinstance(value, bool):
            return int(value)

        if isinstance(value, (int, Decimal)):
            int_value = int(value)
        elif isinstance(value, str):
            cleaned = value.strip()
            if cleaned == "":
                return None
            try:
                int_value = int(cleaned)
            except ValueError:
                return None
        else:
            return None

        min_value, max_value = INTEGER_BOUNDS[dest_type]
        if int_value < min_value or int_value > max_value:
            return None

        return int_value

    return value


def normalize(value: Any) -> Any:
    """Make MariaDB and PostgreSQL values comparable."""
    if isinstance(value, str):
        return value.strip()
    if isinstance(value, datetime) and value.tzinfo is not None:
        return value.replace(tzinfo=None)
    return value


def get_dest_columns_and_types(dest_conn, table: str) -> list[tuple[str, str]]:
    with dest_conn.cursor() as cursor:
        cursor.execute(
            """
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = %s
            ORDER BY ordinal_position
            """,
            (table,),
        )
        return [(row[0], row[1]) for row in cursor.fetchall()]


def get_source_columns(source_conn, table: str) -> set[str]:
    with source_conn.cursor() as cursor:
        cursor.execute(f"SHOW COLUMNS FROM `{table}`")
        return {row["Field"] for row in cursor.fetchall()}


def table_exists(dest_conn, table: str) -> bool:
    with dest_conn.cursor() as cursor:
        cursor.execute(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = %s",
            (table,),
        )
        return cursor.fetchone() is not None


def ensure_map_table(dest_conn) -> None:
    with dest_conn.cursor() as cursor:
        cursor.execute(
            sql.SQL(
                """
                CREATE TABLE IF NOT EXISTS {} (
                    table_name text NOT NULL,
                    source_id bigint NOT NULL,
                    dest_id bigint NOT NULL,
                    status text NOT NULL,
                    imported_at timestamptz NOT NULL DEFAULT now(),
                    PRIMARY KEY (table_name, source_id)
                )
                """
            ).format(sql.Identifier(MAP_TABLE))
        )


def load_saved_map(dest_conn, table: str) -> dict[int, int]:
    with dest_conn.cursor() as cursor:
        cursor.execute(
            sql.SQL("SELECT source_id, dest_id FROM {} WHERE table_name = %s").format(sql.Identifier(MAP_TABLE)),
            (table,),
        )
        return {int(src): int(dst) for src, dst in cursor.fetchall()}


def check_reference_tables(source_conn, dest_conn) -> None:
    print("\nReference tables check (ids must line up for foreign keys):")
    for table in REFERENCE_TABLES:
        if not table_exists(dest_conn, table):
            print(f"  WARN {table}: missing in PostgreSQL")
            continue

        compare_ids_only = table == "users"
        dest_cols = [c for c, _ in get_dest_columns_and_types(dest_conn, table)]
        src_cols = get_source_columns(source_conn, table)
        cols = [
            c
            for c in dest_cols
            if c in src_cols and (c == "id" or (not compare_ids_only and c not in IGNORED_COMPARE_COLUMNS))
        ]
        with source_conn.cursor() as cursor:
            cursor.execute(f"SELECT {', '.join(f'`{c}`' for c in cols)} FROM `{table}`")
            src_rows = {row["id"]: row for row in cursor.fetchall()}
        with dest_conn.cursor() as cursor:
            cursor.execute(
                sql.SQL("SELECT {} FROM {}").format(
                    sql.SQL(", ").join(sql.Identifier(c) for c in cols), sql.Identifier(table)
                )
            )
            dst_rows = {r[0]: dict(zip(cols, r)) for r in cursor.fetchall()}

        missing = sorted(set(src_rows) - set(dst_rows))
        different = [
            i
            for i in src_rows
            if i in dst_rows and any(normalize(src_rows[i][c]) != normalize(dst_rows[i][c]) for c in cols)
        ]
        if missing:
            print(f"  WARN {table}: ids missing in PostgreSQL: {missing[:10]}{'...' if len(missing) > 10 else ''}")
        if different:
            print(f"  note {table}: {len(different)} row(s) differ in content (ids {different[:10]})")
        if not missing and not different:
            print(f"  OK   {table}: {len(src_rows)} row(s) identical")


def migrate_table(
    source_conn,
    dest_conn,
    spec: TableSpec,
    id_maps: dict[str, dict[int, int]],
    update_existing: bool,
    batch_size: int,
) -> TableStats:
    table = spec.name
    stats = TableStats(table=table)

    dest_cols_types = get_dest_columns_and_types(dest_conn, table)
    source_columns = get_source_columns(source_conn, table)
    common = [(name, dtype) for name, dtype in dest_cols_types if name in source_columns]
    names = [name for name, _ in common]
    types = {name: dtype for name, dtype in common}
    index = {name: i for i, name in enumerate(names)}
    for needed in ["id", *spec.fp_cols, *spec.fk_cols]:
        if needed not in index:
            raise RuntimeError(f"{table}: column {needed!r} missing in source or destination")

    # --- read MariaDB ---------------------------------------------------------------
    with source_conn.cursor() as cursor:
        cursor.execute(f"SELECT {', '.join(f'`{c}`' for c in names)} FROM `{table}` ORDER BY id")
        source_rows = cursor.fetchall()
    stats.source_rows = len(source_rows)

    # --- read PostgreSQL ------------------------------------------------------------
    with dest_conn.cursor() as cursor:
        cursor.execute(
            sql.SQL("SELECT id, {} FROM {}").format(
                sql.SQL(", ").join(sql.Identifier(c) for c in spec.fp_cols), sql.Identifier(table)
            )
        )
        dest_existing = cursor.fetchall()
    dest_ids = {int(row[0]) for row in dest_existing}
    stats.dest_rows_before = len(dest_ids)
    fp_index: dict[tuple, list[int]] = {}
    for row in dest_existing:
        fp_index.setdefault(tuple(normalize(v) for v in row[1:]), []).append(int(row[0]))
    for ids in fp_index.values():
        ids.sort()

    saved_map = load_saved_map(dest_conn, table)
    own_map = id_maps.setdefault(table, {})

    taken = set(dest_ids)
    claimed: set[int] = set()
    next_new_id = max(max(dest_ids, default=0), max((int(r["id"]) for r in source_rows), default=0)) + 1

    inserts: list[tuple] = []
    updates: list[tuple[int, tuple]] = []
    map_records: list[tuple[str, int, int, str]] = []

    for row in source_rows:
        source_id = int(row["id"])
        values = [sanitize_value(row[name], types[name]) for name in names]

        # re-point foreign keys to the final ids of the parent rows
        missing_parent = False
        for col, parent in spec.fk_cols.items():
            old = values[index[col]]
            if old is None:
                continue
            new = id_maps.get(parent, {}).get(int(old))
            if new is None:
                missing_parent = True
                break
            values[index[col]] = new
        if missing_parent:
            stats.skipped_missing_parent += 1
            continue

        fingerprint = tuple(normalize(values[index[c]]) for c in spec.fp_cols)

        dest_id: int | None = None
        status = ""
        saved = saved_map.get(source_id)
        if saved is not None and saved in dest_ids and saved not in claimed:
            dest_id, status = saved, "existing"
            stats.already_mapped += 1
        else:
            candidates = [i for i in fp_index.get(fingerprint, []) if i not in claimed]
            if candidates:
                dest_id = source_id if source_id in candidates else candidates[0]
                status = "matched"
                stats.matched_existing += 1
            elif source_id not in taken:
                dest_id = source_id
                status = "inserted"
                stats.inserted_same_id += 1
            else:
                dest_id = next_new_id
                next_new_id += 1
                status = "inserted"
                stats.inserted_new_id += 1

        claimed.add(dest_id)
        own_map[source_id] = dest_id
        map_records.append((table, source_id, dest_id, status))
        if dest_id != source_id:
            stats.remapped.append((source_id, dest_id))

        values[index["id"]] = dest_id
        if status == "inserted":
            taken.add(dest_id)
            inserts.append(tuple(values))
        elif update_existing:
            updates.append((dest_id, tuple(values)))

    # --- write PostgreSQL -----------------------------------------------------------
    with dest_conn.cursor() as cursor:
        if inserts:
            insert_stmt = sql.SQL("INSERT INTO {} ({}) VALUES %s").format(
                sql.Identifier(table), sql.SQL(", ").join(sql.Identifier(c) for c in names)
            ).as_string(dest_conn)
            execute_values(cursor, insert_stmt, inserts, page_size=batch_size)

        if updates:
            set_cols = [c for c in names if c != "id"]
            update_stmt = sql.SQL("UPDATE {} SET {} WHERE id = %s").format(
                sql.Identifier(table),
                sql.SQL(", ").join(sql.SQL("{} = %s").format(sql.Identifier(c)) for c in set_cols),
            ).as_string(dest_conn)
            payload = [
                (*[vals[index[c]] for c in set_cols], dest_id) for dest_id, vals in updates
            ]
            execute_batch(cursor, update_stmt, payload, page_size=batch_size)
            stats.updated = len(payload)

        if map_records:
            map_stmt = sql.SQL(
                "INSERT INTO {} (table_name, source_id, dest_id, status) VALUES %s "
                "ON CONFLICT (table_name, source_id) DO UPDATE "
                "SET dest_id = EXCLUDED.dest_id, status = EXCLUDED.status, imported_at = now()"
            ).format(sql.Identifier(MAP_TABLE)).as_string(dest_conn)
            execute_values(cursor, map_stmt, map_records, page_size=batch_size)

        cursor.execute(sql.SQL("SELECT COUNT(*) FROM {}").format(sql.Identifier(table)))
        stats.dest_rows_after = int(cursor.fetchone()[0])

    return stats


def sync_all_sequences(dest_conn, apply: bool) -> list[SequenceResult]:
    """Set every id sequence in schema public to max(id) + 1 (never lower than it already is)."""
    results: list[SequenceResult] = []
    with dest_conn.cursor() as cursor:
        cursor.execute(
            """
            SELECT c.relname, a.attname
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum > 0 AND NOT a.attisdropped
            WHERE n.nspname = 'public' AND c.relkind = 'r'
            ORDER BY c.relname, a.attnum
            """
        )
        candidates = cursor.fetchall()

        for table, column in candidates:
            cursor.execute(
                "SELECT pg_get_serial_sequence(%s, %s)",
                (f'public."{table}"', column),
            )
            sequence = cursor.fetchone()[0]
            if not sequence:
                continue

            cursor.execute(
                sql.SQL("SELECT COALESCE(MAX({}), 0) FROM {}").format(sql.Identifier(column), sql.Identifier(table))
            )
            max_id = int(cursor.fetchone()[0])

            cursor.execute(f"SELECT last_value, is_called FROM {sequence}")
            last_value, is_called = cursor.fetchone()
            current_next = int(last_value) + (1 if is_called else 0)
            # Never move a sequence backwards: ids of deleted rows must not be handed out again.
            new_next = max(max_id + 1, current_next)

            if apply:
                cursor.execute("SELECT setval(%s, %s, false)", (sequence, new_next))

            results.append(SequenceResult(table, column, sequence, max_id, current_next, new_next))
    return results


def write_report(path: str, all_stats: list[TableStats]) -> None:
    with open(path, "w", newline="") as handle:
        writer = csv.writer(handle)
        writer.writerow(["table", "mariadb_id", "postgres_id"])
        for stats in all_stats:
            for source_id, dest_id in stats.remapped:
                writer.writerow([stats.table, source_id, dest_id])


def print_sequences(sequences: list[SequenceResult], applied: bool) -> None:
    print(f"\nId sequences ({'set' if applied else 'would be set'} to max(id) + 1, never lowered):")
    print(f"  {'table.column':42} {'max id':>10} {'next now':>10} {'next after':>11}")
    for item in sequences:
        flag = "" if item.current_next == item.new_next else "  <- changed"
        print(f"  {item.table + '.' + item.column:42} {item.max_id:>10} {item.current_next:>10} {item.new_next:>11}{flag}")


def main() -> None:
    args = parse_args()

    dry_run = not args.run
    if args.run and not args.yes:
        raise SystemExit("Run mode requires --yes confirmation. Example: --run --yes")

    source_cfg = DbConfig(args.source_host, args.source_port, args.source_db, args.source_user, args.source_password)
    dest_cfg = DbConfig(args.dest_host, args.dest_port, args.dest_db, args.dest_user, args.dest_password)

    print("=" * 78)
    print("MariaDB -> PostgreSQL merge (no truncate, live rows are never overwritten)")
    print(f"Mode: {'DRY RUN (everything is rolled back)' if dry_run else 'EXECUTE (will be committed)'}")
    if args.sequences_only:
        print("Scope: id sequences only")
    else:
        print(f"Tables: {', '.join(spec.name for spec in TABLE_SPECS)}")
        print(f"Replace rows that already exist: {'YES' if args.update_existing else 'no (kept as is)'}")
    print(f"Source:      {source_cfg.database} @ {source_cfg.host}:{source_cfg.port}")
    print(f"Destination: {dest_cfg.database} @ {dest_cfg.host}:{dest_cfg.port}")
    print("=" * 78)

    source_conn = get_mariadb_connection(source_cfg)
    dest_conn = get_postgres_connection(dest_cfg)

    all_stats: list[TableStats] = []
    sequences: list[SequenceResult] = []

    try:
        if not args.sequences_only:
            check_reference_tables(source_conn, dest_conn)

            ensure_map_table(dest_conn)
            if not dry_run:
                # Stop live writes on these tables until we commit, otherwise the application
                # could take an id this script has just chosen.
                with dest_conn.cursor() as cursor:
                    cursor.execute(
                        sql.SQL("LOCK TABLE {} IN SHARE ROW EXCLUSIVE MODE").format(
                            sql.SQL(", ").join(sql.Identifier(spec.name) for spec in TABLE_SPECS)
                        )
                    )

            id_maps: dict[str, dict[int, int]] = {}
            print("\nCopying:")
            for spec in TABLE_SPECS:
                stats = migrate_table(
                    source_conn, dest_conn, spec, id_maps, args.update_existing, args.batch_size
                )
                all_stats.append(stats)
                print(
                    f"  {spec.name}: source={stats.source_rows} "
                    f"already_imported={stats.already_mapped} same_row_found={stats.matched_existing} "
                    f"inserted(same id)={stats.inserted_same_id} inserted(new id)={stats.inserted_new_id} "
                    f"missing_parent={stats.skipped_missing_parent}"
                )

        sequences = sync_all_sequences(dest_conn, apply=not dry_run)

        if dry_run:
            dest_conn.rollback()
        else:
            dest_conn.commit()
    except Exception:
        dest_conn.rollback()
        raise
    finally:
        source_conn.close()
        dest_conn.close()

    if all_stats:
        write_report(args.report, all_stats)

        print("\nReconciliation (every MariaDB row must be accounted for):")
        problems = 0
        for stats in all_stats:
            ok = stats.accounted == stats.source_rows and stats.skipped_missing_parent == 0
            problems += 0 if ok else 1
            print(
                f"  {'OK  ' if ok else 'CHECK'} {stats.table}: source={stats.source_rows} "
                f"accounted={stats.accounted}  PostgreSQL rows {stats.dest_rows_before} -> {stats.dest_rows_after}"
                + (f"  replaced={stats.updated}" if args.update_existing else "")
            )
        print(f"\nRows that got a different id than in MariaDB: {sum(len(s.remapped) for s in all_stats)}")
        print(f"  (list saved to {args.report})")
        print(f"Tables needing attention: {problems}")

    print_sequences(sequences, applied=not dry_run)

    print("\nDRY RUN finished - nothing was saved." if dry_run else "\nDONE - changes committed.")


if __name__ == "__main__":
    main()
