@echo off
title Sapphire Luxury - USB Attendance Sync
echo ========================================================
echo   Sapphire Luxury Aesthetics - Biometric Auto-Sync
echo ========================================================
echo.
echo Running USB Auto-Detection and Sync to Cloud...
echo Please make sure your USB Pen Drive is inserted.
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0sync_usb.ps1"

exit
