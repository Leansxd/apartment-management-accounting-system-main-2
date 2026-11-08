@echo off
chcp 65001 >nul
setlocal
set "PATH=%USERPROFILE%\.config\herd-lite\bin;%PATH%"
cd /d "%~dp0"

where php >nul 2>nul || (echo [HATA] PHP bulunamadi. PHP 8.3+ kurun: https://php.new & goto fail)
where composer >nul 2>nul || (echo [HATA] Composer bulunamadi: https://getcomposer.org & goto fail)
where npm >nul 2>nul || (echo [HATA] Node.js bulunamadi: https://nodejs.org & goto fail)

echo [1/2] PHP paketleri kuruluyor...
call composer install --no-interaction || goto fail

echo [2/2] Arayuz derleniyor...
call npm install --ignore-scripts || goto fail
call npm run build || goto fail

echo.
echo Hazir. Tarayicida acilan kurulum sihirbazini tamamlayin.
call calistir.bat
exit /b 0

:fail
echo.
echo Kurulum tamamlanamadi. Yukaridaki hatayi duzeltip kurulum.bat dosyasini tekrar calistirin.
pause
exit /b 1
