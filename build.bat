@echo off
rd .\output /s /q
md .\output
rd .\css /s /q
call sass --no-charset --no-source-map scss:css
.\php\php.exe build.php