@echo off
set PATH=C:\Users\PC\.config\herd-lite\bin;%PATH%
cd /d "%~dp0"
start "" http://localhost:8000
php artisan serve --port=8000
