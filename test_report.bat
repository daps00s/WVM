@echo off
chcp 65001 >nul
cd /d "C:\xampp\htdocs\WVM(for unit testing)"
cls

echo.
echo =====================================================
echo          WVM DETAILED TEST REPORT
echo =====================================================
echo.

echo [1/5] PHP ENVIRONMENT CHECK:
echo   PHP Version: 
php --version | findstr "PHP"
echo   PHPUnit Version:
vendor\bin\phpunit --version >nul && echo PHPUnit OK || echo PHPUnit ERROR

echo.
echo [2/5] TEST FILES CHECK:
if exist tests\DatabaseTest.php (echo   DatabaseTest.php - ✓ FOUND) else (echo   DatabaseTest.php - ✗ MISSING)
if exist tests\BackupTest.php (echo   BackupTest.php - ✓ FOUND) else (echo   BackupTest.php - ✗ MISSING)
if exist tests\AuthenticationTest.php (echo   AuthenticationTest.php - ✓ FOUND) else (echo   AuthenticationTest.php - ✗ MISSING)
if exist tests\ApiTest.php (echo   ApiTest.php - ✓ FOUND) else (echo   ApiTest.php - ✗ MISSING)
if exist tests\DashboardTest.php (echo   DashboardTest.php - ✓ FOUND) else (echo   DashboardTest.php - ✗ MISSING)

echo.
echo [3/5] RUNNING ALL TESTS:
vendor\bin\phpunit --colors=always

echo.
echo [4/5] GENERATING TESTDOX REPORT:
vendor\bin\phpunit --testdox --colors=always

echo.
echo [5/5] TEST COVERAGE SUMMARY:
echo.
for /f "tokens=*" %%i in ('vendor\bin\phpunit ^| findstr "Tests:"') do echo   %%i
for /f "tokens=*" %%i in ('vendor\bin\phpunit ^| findstr "Time:"') do echo   %%i

echo.
echo =====================================================
echo           REPORT GENERATION COMPLETE
echo =====================================================
echo.
pause