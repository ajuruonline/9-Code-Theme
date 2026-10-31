@echo off
cd /d "%~dp0"
if not exist ".venv\Scripts\python.exe" (
  py -3 -m venv .venv
  .venv\Scripts\python.exe -m pip install pywebview==5.4
)
.venv\Scripts\python.exe secure_scholar.py
