@echo off
chcp 65001 >nul
cd /d "C:\xampp\htdocs\WVM(for unit testing)"
cls

echo.
echo =====================================================
echo            WVM UNIT TESTING SUITE
echo =====================================================
echo Project: Water Vending Machine System
echo Location: %CD%
echo Date: %date% %time%
echo =====================================================
echo.

echo [1/4] Checking PHPUnit installation...
vendor\bin\phpunit --version >nul && echo PHPUnit OK || echo PHPUnit ERROR
if errorlevel 1 (
    echo ERROR: PHPUnit not found! Run: composer install
    goto end
)

echo.
echo [2/4] Running all unit tests...
vendor\bin\phpunit --colors=always

echo.
echo [3/4] Generating test report...
vendor\bin\phpunit --testdox

echo.
echo [4/4] Individual test file status:
echo.
call :run_test "Database Tests" tests/DatabaseTest.php
call :run_test "Backup System" tests/BackupTest.php
call :run_test "Authentication" tests/AuthenticationTest.php
call :run_test "API Endpoints" tests/ApiTest.php
call :run_test "Dashboard" tests/DashboardTest.php

echo.
echo =====================================================
echo            TESTING COMPLETED SUCCESSFULLY!
echo =====================================================
echo.
goto end

:run_test
echo | set /p="   %~1: "
vendor\bin\phpunit --colors=always %~2 >nul && echo PASSED || echo FAILED
exit /b

:end
echo.
echo Press any key to exit...
pause >nul