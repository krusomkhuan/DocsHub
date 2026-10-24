@echo off
rem Docs Hub full backup - schedule this file in Windows Task Scheduler (e.g. daily 18:00)
if not exist "%~dp0..\backups" mkdir "%~dp0..\backups"
"C:\xampp\php\php.exe" "%~dp0backup.php" --full >> "%~dp0..\backups\backup-task.log" 2>&1
