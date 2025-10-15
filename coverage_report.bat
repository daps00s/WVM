@echo off
chcp 65001 >nul
cd /d "C:\xampp\htdocs\WVM(for unit testing)"
cls

echo.
echo =====================================================
echo          WVM TEST COVERAGE REPORT
echo =====================================================
echo.

echo [1/3] Generating HTML coverage report...
if exist coverage (rmdir /s /q coverage)
vendor\bin\phpunit --coverage-html coverage

echo [2/3] Opening coverage report...
if exist coverage\index.html (
    start coverage\index.html
    echo   Coverage report opened in browser
) else (
    echo   ERROR: Coverage report not generated
)

echo [3/3] Quick coverage summary:
vendor\bin\phpunit --coverage-text

echo.
echo =====================================================
echo     Coverage report saved to: coverage\index.html
echo =====================================================
echo.
pause