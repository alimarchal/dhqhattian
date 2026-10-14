@echo off
setlocal EnableExtensions
:: ==================================================================================
:: AIMS HOSPITAL - PostgreSQL Restore (Import) Script for Windows
::
:: What it does:
::   1. Picks a .backup file (the one you pass, or the NEWEST one in the backup folder)
::   2. Asks you to type YES  (the old database will be DELETED)
::   3. Disconnects everyone, DROPs dhq_hattian_pg and creates it again (empty)
::   4. Restores the backup into it with pg_restore (parallel, with progress)
::
:: Usage:
::   import_db.bat                                   -> uses newest backup in BACKUP_DIR
::   import_db.bat "D:\path\to\file.backup"          -> uses that file
::
:: Made for backups created with:  pg_dump -Fc   (custom format, .backup)
:: ==================================================================================

:: ---------------- settings (change here if needed) ----------------
set PG_BIN=C:\Program Files\PostgreSQL\18\bin
set PG_HOST=127.0.0.1
set PG_PORT=5432
set PG_USER=postgres
set DB_NAME=dhq_hattian_pg
set BACKUP_DIR=D:\DHQ_HATTIAN_BACKUP
set JOBS=4
:: -------------------------------------------------------------------

if "%PGPASSWORD%"=="" set /p PGPASSWORD=Enter PostgreSQL password for user %PG_USER%: 

if not exist "%PG_BIN%\pg_restore.exe" (
    echo [ERROR] PostgreSQL not found at "%PG_BIN%". Edit PG_BIN in this file.
    goto :end
)

:: ---- choose backup file ----
set "BACKUP_FILE=%~1"
if "%BACKUP_FILE%"=="" (
    for /f "delims=" %%F in ('dir /b /a-d /o-d "%BACKUP_DIR%\*.backup" 2^>nul') do (
        if not defined BACKUP_FILE set "BACKUP_FILE=%BACKUP_DIR%\%%F"
    )
)
if "%BACKUP_FILE%"=="" (
    echo [ERROR] No .backup file found in "%BACKUP_DIR%". Pass the file path as argument.
    goto :end
)
if not exist "%BACKUP_FILE%" (
    echo [ERROR] File not found: "%BACKUP_FILE%"
    goto :end
)

echo.
echo ==============================================================
echo   Database : %DB_NAME%  (%PG_HOST%:%PG_PORT%)
echo   Backup   : %BACKUP_FILE%
echo ==============================================================
echo   WARNING: the current "%DB_NAME%" database will be DELETED
echo   and replaced with the backup above.
echo.
set /p CONFIRM=Type YES to continue:
if /i not "%CONFIRM%"=="YES" (
    echo Cancelled. Nothing was changed.
    goto :end
)

set PATH=%PG_BIN%;%PATH%

echo.
echo [1/4] Checking the backup file ...
pg_restore -l "%BACKUP_FILE%" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] This is not a valid pg_dump custom-format backup. Nothing was changed.
    goto :end
)

echo [2/4] Disconnecting users and dropping database %DB_NAME% ...
psql -h %PG_HOST% -p %PG_PORT% -U %PG_USER% -d postgres -v ON_ERROR_STOP=1 -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '%DB_NAME%' AND pid <> pg_backend_pid();" >nul
dropdb -h %PG_HOST% -p %PG_PORT% -U %PG_USER% --if-exists %DB_NAME%
if errorlevel 1 (
    echo [ERROR] Could not drop the database. Close pgAdmin/the app and try again.
    goto :end
)

echo [3/4] Creating empty database %DB_NAME% ...
createdb -h %PG_HOST% -p %PG_PORT% -U %PG_USER% %DB_NAME%
if errorlevel 1 (
    echo [ERROR] Could not create the database.
    goto :end
)

echo [4/4] Restoring backup (this can take a few minutes) ...
pg_restore -h %PG_HOST% -p %PG_PORT% -U %PG_USER% -d %DB_NAME% -j %JOBS% --no-owner -v "%BACKUP_FILE%"
if errorlevel 1 (
    echo.
    echo [WARNING] pg_restore finished with warnings/errors. Check the messages above.
) else (
    echo.
    echo [DONE] Database %DB_NAME% restored successfully.
)

echo.
echo Row counts check:
psql -h %PG_HOST% -p %PG_PORT% -U %PG_USER% -d %DB_NAME% -c "SELECT 'patients' t, count(*) FROM patients UNION ALL SELECT 'chits', count(*) FROM chits UNION ALL SELECT 'invoices', count(*) FROM invoices UNION ALL SELECT 'patient_tests', count(*) FROM patient_tests;"

:end
set PGPASSWORD=
echo.
pause
endlocal
