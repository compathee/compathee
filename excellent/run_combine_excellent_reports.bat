@echo off
setlocal

cd /d "%~dp0"

set "PYTHON_CMD=python"
where py >nul 2>nul
if %ERRORLEVEL% EQU 0 set "PYTHON_CMD=py -3"

echo Installing Python dependencies...
%PYTHON_CMD% -m pip install -r requirements.txt
if %ERRORLEVEL% NEQ 0 (
  echo.
  echo Failed to install dependencies. Make sure Python 3 is installed.
  pause
  exit /b 1
)

echo.
echo Starting Excellent Books report combiner...
%PYTHON_CMD% scripts\combine_excellent_reports.py --interactive

echo.
pause
