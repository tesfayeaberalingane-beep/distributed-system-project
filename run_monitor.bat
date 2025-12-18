@echo off
:: Navigate to the directory where the script is (Optional but good practice)
cd /d "C:\xampp\htdocs\distributed-jobs-scheduler\server\api"

:: Execute the PHP script using the PHP interpreter
"C:\xampp\php\php.exe" monitor.php

:: Pause is optional; remove it once you confirm the task scheduler works
:: pause