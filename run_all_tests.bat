@echo off
cd /d "C:\xampp\htdocs\WVM(for unit testing)"
cls
echo.
echo =====================================================
echo           WVM COMPLETE TEST SUITE
echo =====================================================
echo.

echo [1/9] DATABASE TESTS:
call vendor\bin\phpunit tests/DatabaseTest.php --testdox --colors=always

echo.
echo [2/9] BACKUP TESTS:
call vendor\bin\phpunit tests/BackupTest.php --testdox --colors=always

echo.
echo [3/9] AUTHENTICATION TESTS:
call vendor\bin\phpunit tests/AuthenticationTest.php --testdox --colors=always

echo.
echo [4/9] API TESTS:
call vendor\bin\phpunit tests/ApiTest.php --testdox --colors=always

echo.
echo [5/9] DASHBOARD TESTS:
call vendor\bin\phpunit tests/DashboardTest.php --testdox --colors=always

echo.
echo [6/9] ALL PAGES TESTS:
call vendor\bin\phpunit tests/MachinesTest.php --testdox --colors=always
call vendor\bin\phpunit tests/LocationsTest.php --testdox --colors=always
call vendor\bin\phpunit tests/TransactionsTest.php --testdox --colors=always
call vendor\bin\phpunit tests/CoinCollectionsTest.php --testdox --colors=always
call vendor\bin\phpunit tests/WaterLevelsTest.php --testdox --colors=always

echo.
echo =====================================================
echo            ALL TESTS COMPLETED!
echo =====================================================
echo.
pause