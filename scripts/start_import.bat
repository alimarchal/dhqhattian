@echo off
setlocal EnableExtensions
:: ==================================================================================
:: AIMS HOSPITAL - SAFE IMPORT for PRODUCTION (Windows)
::
:: Order of work (it stops at the first problem and never drops anything before step 2 passes):
::   1. Checks the file you want to import is a valid pg_dump custom-format backup
::   2. Takes a SAFETY BACKUP of the current production database (same -Fc format
::      as your daily backup) into D:\DHQ_HATTIAN_BACKUP\before_import_YYYYMMDD_HHMMSS.backup
::      -> if this backup fails or is empty, NOTHING is deleted
::   3. Asks you to type YES
::   4. Disconnects everyone, DROPs dhq_hattian_pg, creates it again empty
::   5. Restores the file with pg_restore (parallel + progress) and shows row counts
::
:: Usage:
::   start_import.bat                              -> asks you for the import file path
::   start_import.bat "D:\any\folder\file.backup"  -> imports that file
::   (folders D:\DHQ_HATTIAN_BACKUP and D:\DHQ_HATTIAN_IMPORT are created if missing)
::
:: To go back if something is wrong, run it again with the before_import_*.backup file.
:: ==================================================================================

:: ---------------- settings ----------------
set PG_BIN=C:\Program Files\PostgreSQL\18\bin
set PG_HOST=127.0.0.1
set PG_PORT=5432
set PG_USER=postgres
set DB_NAME=dhq_hattian_pg
set SAFETY_DIR=D:\DHQ_HATTIAN_BACKUP
set IMPORT_DIR=D:\DHQ_HATTIAN_IMPORT
set JOBS=4
:: ------------------------------------------

if "%PGPASSWORD%"=="" set /p PGPASSWORD=Enter PostgreSQL password for user %PG_USER%: 

if not exist "%PG_BIN%\pg_restore.exe" (
    echo [ERROR] PostgreSQL not found at "%PG_BIN%". Edit PG_BIN in this file.
    goto :end
)
set PATH=%PG_BIN%;%PATH%

if not exist "%SAFETY_DIR%" (
    echo Creating folder "%SAFETY_DIR%" ...
    mkdir "%SAFETY_DIR%"
)
if not exist "%IMPORT_DIR%" (
    echo Creating folder "%IMPORT_DIR%" ...
    mkdir "%IMPORT_DIR%"
)

set "IMPORT_FILE=%~1"
if "%IMPORT_FILE%"=="" (
    echo.
    echo Put the backup file in "%IMPORT_DIR%" or type its full path below.
    echo Example: D:\DHQ_HATTIAN_IMPORT\dhq_hattian_pg.backup
    set /p IMPORT_FILE=Import file path: 
)
:: remove quotes if the path was pasted with quotes
if defined IMPORT_FILE set "IMPORT_FILE=%IMPORT_FILE:"=%"
if "%IMPORT_FILE%"=="" (
    echo [ERROR] No file given. Nothing was changed.
    goto :end
)
if not exist "%IMPORT_FILE%" (
    echo [ERROR] Import file not found: "%IMPORT_FILE%"
    goto :end
)

:: timestamp YYYYMMDD_HHMMSS (independent of Windows regional settings)
for /f %%T in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set STAMP=%%T
set "SAFETY_FILE=%SAFETY_DIR%\before_import_%STAMP%.backup"

echo.
echo ==============================================================
echo   PRODUCTION DATABASE : %DB_NAME%  (%PG_HOST%:%PG_PORT%)
echo   Will import         : %IMPORT_FILE%
echo   Safety backup       : %SAFETY_FILE%
echo ==============================================================

echo.
echo [1/5] Checking the import file ...
pg_restore -l "%IMPORT_FILE%" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] "%IMPORT_FILE%" is not a valid pg_dump custom-format backup. Nothing was changed.
    goto :end
)
echo        OK

echo [2/5] Taking safety backup of the CURRENT database ...
pg_dump -h %PG_HOST% -p %PG_PORT% -U %PG_USER% -d %DB_NAME% -Fc -f "%SAFETY_FILE%"
if errorlevel 1 (
    echo [ERROR] Safety backup FAILED. Nothing was deleted or changed.
    goto :end
)
for %%S in ("%SAFETY_FILE%") do set SAFETY_SIZE=%%~zS
if "%SAFETY_SIZE%"=="" set SAFETY_SIZE=0
if %SAFETY_SIZE% LSS 100000 (
    echo [ERROR] Safety backup looks empty ^(%SAFETY_SIZE% bytes^). Nothing was deleted or changed.
    goto :end
)
echo        Saved: %SAFETY_FILE%  ^(%SAFETY_SIZE% bytes^)

echo.
echo   WARNING: the current "%DB_NAME%" will now be DELETED and replaced
echo   with the contents of the import file. Close the application and pgAdmin first.
echo.
set /p CONFIRM=Type YES to continue:
if /i not "%CONFIRM%"=="YES" (
    echo Cancelled. The database was NOT changed. ^(Safety backup was kept.^)
    goto :end
)

echo.
echo [3/5] Disconnecting users and dropping %DB_NAME% ...
psql -h %PG_HOST% -p %PG_PORT% -U %PG_USER% -d postgres -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '%DB_NAME%' AND pid <> pg_backend_pid();" >nul
dropdb -h %PG_HOST% -p %PG_PORT% -U %PG_USER% --if-exists %DB_NAME%
if errorlevel 1 (
    echo [ERROR] Could not drop the database ^(still in use?^). Close the app/pgAdmin and run again.
    goto :end
)

echo [4/5] Creating empty database %DB_NAME% ...
createdb -h %PG_HOST% -p %PG_PORT% -U %PG_USER% %DB_NAME%
if errorlevel 1 (
    echo [ERROR] Could not create the database. Restore the safety backup: start_import.bat "%SAFETY_FILE%"
    goto :end
)

echo [5/5] Restoring (this can take a few minutes) ...
pg_restore -h %PG_HOST% -p %PG_PORT% -U %PG_USER% -d %DB_NAME% -j %JOBS% --no-owner -v "%IMPORT_FILE%"
if errorlevel 1 (
    echo.
    echo [WARNING] pg_restore finished with warnings/errors - read the messages above.
    echo           To go back: start_import.bat "%SAFETY_FILE%"
) else (
    echo.
    echo [DONE] %DB_NAME% restored successfully from "%IMPORT_FILE%".
)

echo.
echo Row counts:
psql -h %PG_HOST% -p %PG_PORT% -U %PG_USER% -d %DB_NAME% -c "SELECT 'patients' t, count(*) FROM patients UNION ALL SELECT 'chits', count(*) FROM chits UNION ALL SELECT 'invoices', count(*) FROM invoices UNION ALL SELECT 'patient_tests', count(*) FROM patient_tests UNION ALL SELECT 'users', count(*) FROM users;"

:end
set PGPASSWORD=
echo.
pause
endlocal
