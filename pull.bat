@echo off

IF EXIST .\scss.old2 rd .\scss.old2 /s /q
IF EXIST .\scss move>nul 2>nul .\scss.old1 .\scss.old2

IF EXIST .\scss.old1 rd .\scss.old1 /s /q
IF EXIST .\scss move>nul 2>nul .\scss.old .\scss.old1

IF NOT EXIST .\scss md .\scss
xcopy>nul 2>nul .\scss .\scss.old /e /i /q

.\php\php.exe pull.php %1 %2 %3 %4 %5 %6 %7 %8 %9
