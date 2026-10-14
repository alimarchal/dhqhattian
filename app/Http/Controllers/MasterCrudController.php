<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared list / add / edit / delete screens for the simple master-data modules
 * (departments, government departments, fee types, permissions, roles ...).
 *
 * A module only describes itself in config(): title, route prefix, permission
 * words, list columns and form fields. The screens (resources/views/crud/*)
 * are the same "ak" look as the Users module.
 */
abstract class MasterCrudController extends Controller
{
    /** Rows-per-page choices on the list ("all" shows every matching row on one page). */
    public const PER_PAGE = [10, 50, 100, 500, 'all'];

    /** @return class-string<Model> */
    abstract protected function model(): string;

    /**
     * title, singular, route (resource name), permission (words used in "view departments"),
     * columns [key,label,sortable?,value?(callable),align?], fields [name,label,type,rules,...],
     * search [columns], optional: filters, with, withCount, defaultSort, lede, formView, indexView.
     *
     * @return array<string, mixed>
     */
    abstract protected function config(): array;

    public function __construct()
    {
        $this->middleware(fn ($request, $next) => $this->authorizeAction($request, $next));
    }

    private function authorizeAction(Request $request, \Closure $next)
    {
        $word = $this->config()['permission'];
        $action = $request->route()->getActionMethod();
        $verb = match ($action) {
            'create', 'store' => 'create',
            'edit', 'update' => 'edit',
            'destroy' => 'delete',
            default => 'view',
        };
        $permission = $this->permissionName($verb, $word);
        abort_unless($request->user()->can($permission), 403, 'User does not have the right permissions.');

        return $next($request);
    }

    /** "view departments" style name; modules with other wording override this. */
    protected function permissionName(string $verb, string $word): string
    {
        return $verb.' '.$word;
    }

    protected function query(Request $request): Builder
    {
        $config = $this->config();
        $query = ($this->model())::query();
        if (! empty($config['with'])) {
            $query->with($config['with']);
        }
        if (! empty($config['withCount'])) {
            $query->withCount($config['withCount']);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $config = $this->config();
        $filters = array_filter((array) $request->input('filter', []), fn ($v) => $v !== null && $v !== '');

        $query = $this->query($request);
        $this->applyFilters($query, $filters, $config);

        $sortable = collect($config['columns'])->filter(fn ($c) => $c['sortable'] ?? false)->pluck('key')->all();
        [$defaultField, $defaultDir] = $config['defaultSort'] ?? ['id', 'asc'];
        $sort = (string) $request->get('sort', ($defaultDir === 'desc' ? '-' : '').$defaultField);
        $field = ltrim($sort, '-');
        if (! in_array($field, $sortable, true) && $field !== $defaultField) {
            $sort = ($defaultDir === 'desc' ? '-' : '').$defaultField;
            $field = $defaultField;
        }

        $requested = (string) $request->get('per_page', '10');
        $perPage = in_array($requested, array_map('strval', self::PER_PAGE), true) ? $requested : '10';

        $total = (clone $query)->count();
        $query->orderBy($field, str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id');
        $records = $query->paginate($perPage === 'all' ? max(1, $total) : (int) $perPage)->appends($request->query());

        return view($config['indexView'] ?? 'crud.index', [
            'crud' => $config,
            'records' => $records,
            'filters' => $filters,
            'sort' => $sort,
            'perPage' => $perPage,
            'total' => $total,
            'extra' => $this->indexExtra($request),
        ]);
    }

    /** @return array<string, mixed> extra data for the list view (counts, option lists ...) */
    protected function indexExtra(Request $request): array
    {
        return [];
    }

    protected function applyFilters(Builder $query, array $filters, array $config): void
    {
        if (filled($filters['search'] ?? null) && ! empty($config['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($config, $term) {
                foreach ($config['search'] as $column) {
                    $q->orWhere(DB::raw($column.'::text'), 'ilike', $term);
                }
            });
        }
        foreach ($config['filters'] ?? [] as $filter) {
            if (filled($filters[$filter['name']] ?? null)) {
                $query->where($filter['column'] ?? $filter['name'], $filters[$filter['name']]);
            }
        }
    }

    public function create()
    {
        $config = $this->config();

        return view($config['formView'] ?? 'crud.form', $this->formData($config, $this->newRecord()));
    }

    public function show($id)
    {
        return redirect()->route($this->config()['route'].'.edit', $id);
    }

    public function edit($id)
    {
        $config = $this->config();

        return view($config['formView'] ?? 'crud.form', $this->formData($config, $this->findRecord($id)));
    }

    protected function newRecord(): Model
    {
        $model = new ($this->model());
        foreach ($this->config()['fields'] as $field) {
            if (array_key_exists('default', $field)) {
                $model->{$field['name']} = $field['default'];
            }
        }

        return $model;
    }

    protected function findRecord($id): Model
    {
        return ($this->model())::findOrFail($id);
    }

    /** @return array<string, mixed> */
    protected function formData(array $config, Model $record): array
    {
        foreach ($config['fields'] as &$field) {
            if (is_callable($field['options'] ?? null)) {
                $field['options'] = ($field['options'])();
            }
        }
        unset($field);

        return ['crud' => $config, 'record' => $record, 'isCreate' => ! $record->exists];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        $rules = [];
        foreach ($this->config()['fields'] as $field) {
            $rules[$field['name']] = $field['rules'];
        }

        return $rules;
    }

    /** Last chance to change the validated values before they are saved. */
    protected function prepare(array $data, ?Model $record): array
    {
        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->prepare($request->validate($this->rules($request, null)), null);
        $this->saveRecord(null, $data);

        return redirect()->route($this->config()['route'].'.index')
            ->with('success', $this->config()['singular'].' created successfully.');
    }

    public function update(Request $request, $id)
    {
        $record = $this->findRecord($id);
        $data = $this->prepare($request->validate($this->rules($request, $record)), $record);
        $this->saveRecord($record, $data);

        return redirect()->route($this->config()['route'].'.index')
            ->with('success', $this->config()['singular'].' updated successfully.');
    }

    protected function saveRecord(?Model $record, array $data): Model
    {
        if ($record) {
            $record->update($data);

            return $record;
        }

        return ($this->model())::create($data);
    }

    public function destroy($id)
    {
        $record = $this->findRecord($id);

        if ($message = $this->cannotDelete($record)) {
            return redirect()->route($this->config()['route'].'.index')->with('error', $message);
        }

        try {
            $this->deleteRecord($record);
        } catch (QueryException $e) {
            return redirect()->route($this->config()['route'].'.index')
                ->with('error', $this->config()['singular'].' is in use and cannot be deleted.');
        }

        return redirect()->route($this->config()['route'].'.index')
            ->with('success', $this->config()['singular'].' deleted successfully.');
    }

    /**
     * Return a message to refuse the delete, or null to allow it. A module lists the tables that
     * point at its rows in config()['usedBy'] = [[table, column, label], ...]; the row is kept
     * while any of them still uses it (works even where the database has no foreign key).
     */
    protected function cannotDelete(Model $record): ?string
    {
        foreach ($this->config()['usedBy'] ?? [] as [$table, $column, $label]) {
            if (DB::table($table)->whereRaw("{$column}::text = ?", [(string) $record->getKey()])->exists()) {
                return $this->config()['singular'].' is used by '.$label.' and cannot be deleted.';
            }
        }

        return null;
    }

    protected function deleteRecord(Model $record): void
    {
        $record->delete();
    }
}
