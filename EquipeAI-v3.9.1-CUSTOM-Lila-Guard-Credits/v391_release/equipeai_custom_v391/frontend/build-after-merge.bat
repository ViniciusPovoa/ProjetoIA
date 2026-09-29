@echo off
cd /d "%~dp0"
echo Gerando novo frontend com Proposal Studio...
call npm run build
if errorlevel 1 (
  echo.
  echo O build falhou. Leia BUILD_REQUIRED_AFTER_MERGE.txt.
  pause
  exit /b 1
)
echo.
echo Build concluido. Publique o conteudo da pasta dist.
pause
