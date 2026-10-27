@echo off
REM ==== Runs the website on THIS computer only (nothing goes online) ====
cd /d "%~dp0"
set "PHPEXE="
if exist "C:\xampp\php\php.exe" set "PHPEXE=C:\xampp\php\php.exe"
if not defined PHPEXE (
  where php >nul 2>nul && set "PHPEXE=php"
)
if not defined PHPEXE (
  echo.
  echo  PHP is not installed yet.
  echo  Install XAMPP once: double-click xampp-windows-x64-8.2.12-0-VS16-installer.exe
  echo  in your Downloads folder and click Next until it finishes. Then run this again.
  echo.
  pause
  exit /b
)
echo.
echo  ===============================================================
echo   Website is running.  Ctrl+Click a link below to open it:
echo     Home ........ http://localhost:8000/index.html
echo     Donate ...... http://localhost:8000/donations/
echo     Admin ....... http://localhost:8000/donations/admin/
echo   Keep this window open. Press Ctrl+C to stop.
echo  ===============================================================
echo.
"%PHPEXE%" -S localhost:8000 router.php
pause
