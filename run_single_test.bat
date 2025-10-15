@echo off
chcp 65001 >nul
cd /d "C:\xampp\htdocs\WVM(for unit testing)"
cls

echo.
echo =====================================================
echo          RUN INDIVIDUAL TEST FILE
echo =====================================================
echo.
echo Available test files:
echo   1. DatabaseTest.php
echo   2. BackupTest.php  
echo   3. AuthenticationTest.php
echo   4. ApiTest.php
echo   5. DashboardTest.php
echo   6. Run ALL tests
echo.
set /p choice="Enter choice (1-6): "

if "%choice%"=="1" (
    echo Running Database Tests...
    vendor\bin\phpunit tests/DatabaseTest.php --colors=always
) else if "%choice%"=="2" (
    echo Running Backup Tests...
    vendor\bin\phpunit tests/BackupTest.php --colors=always
) else if "%choice%"=="3" (
    echo Running Authentication Tests...
    vendor\bin\phpunit tests/AuthenticationTest.php --colors=always
) else if "%choice%"=="4" (
    echo Running API Tests...
    vendor\bin\phpunit tests/ApiTest.php --colors=always
) else if "%choice%"=="5" (
    echo Running Dashboard Tests...
    vendor\bin\phpunit tests/DashboardTest.php --colors=always
) else if "%choice%"=="6" (
    echo Running ALL Tests...
    vendor\bin\phpunit --colors=always
) else (
    echo Invalid choice!
)

echo.
pause