$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$projectRoot = Split-Path $PSScriptRoot -Parent
py -3.12 -m venv .build-venv
if ($LASTEXITCODE -ne 0) { throw 'Python environment creation failed.' }
$taskPython = Join-Path $PSScriptRoot '.build-venv\Scripts\python.exe'
& $taskPython -m pip install --upgrade pip
& $taskPython -m pip install -r requirements.txt
if ($LASTEXITCODE -ne 0) { throw 'Build dependencies could not be installed.' }
& $taskPython -m PyInstaller --noconfirm --clean --onefile --windowed `
    --name OpenScholarSecurePlayer `
    --add-data "$projectRoot\dist;dist" `
    --collect-all webview `
    secure_scholar.py
if ($LASTEXITCODE -ne 0) { throw 'The Windows executable build failed.' }
& .\dist\OpenScholarSecurePlayer.exe --self-test
if ($LASTEXITCODE -ne 0) { throw 'The built executable failed its self-test.' }
Write-Host 'Built desktop\dist\OpenScholarSecurePlayer.exe'
Write-Host 'Test the executable on a clean Windows 10/11 computer before distribution, then code-sign it.'
