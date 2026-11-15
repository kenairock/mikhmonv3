@echo off
rem Starts PHP 8.5 FastCGI for nginx on 127.0.0.1:9000 (Mikhmon).
rem Run at logon/startup (e.g. Task Scheduler "At startup"), or double-click.
rem The loop restarts php-cgi if it ever exits.

set PHP_DIR=E:\Applications\php-8.5.12
set PHP_INI=E:\Applications\nginx-1.30.6\php-data\php.ini

rem 0 = never recycle the process (default 500 would make php-cgi exit and nginx return 502)
set PHP_FCGI_MAX_REQUESTS=0

rem Account / audit database, outside the web root (nginx.conf passes the same path)
set MIKHMON_DB=E:\Applications\nginx-1.30.6\php-data\mikhmon.sqlite

:loop
"%PHP_DIR%\php-cgi.exe" -b 127.0.0.1:9000 -c "%PHP_INI%"
timeout /t 2 /nobreak >nul
goto loop
