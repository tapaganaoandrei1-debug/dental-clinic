@echo off
setlocal
set "PHP_EXE=%~dp0..\..\php\php.exe"
set "SETUP_SCRIPT=%~dp0dental-clinic\scripts\setup-accounts.php"

if not exist "%PHP_EXE%" (
    echo XAMPP PHP was not found at "%PHP_EXE%".
    echo Put this project folder inside your XAMPP htdocs folder.
    pause
    exit /b 1
)

"%PHP_EXE%" "%SETUP_SCRIPT%"
if errorlevel 1 (
    echo Account setup failed. Check that MySQL is running and the database was imported.
    pause
    exit /b 1
)

echo.
echo Setup complete.
pause
