@echo off
rem ============================================================
rem  INICIAR LA LIBRERIA
rem ============================================================
rem  Doble clic aca. Prende el servidor de PHP que viene en la carpeta
rem  php\ y abre el sistema en el navegador. No instala nada.
rem
rem  Mientras se usa el sistema, ESTA VENTANA TIENE QUE QUEDAR ABIERTA:
rem  es el servidor. Cerrarla apaga el sistema.
rem ============================================================
cd /d "%~dp0"
title Libreria - servidor (no cerrar)
set PUERTO=8642

rem Se usa 127.0.0.1 y no "localhost" a proposito. "localhost" puede ser
rem dos direcciones (la vieja, 127.0.0.1, y la nueva, ::1), y si otro
rem programa de la PC ya ocupa una de las dos con el mismo puerto, el
rem navegador puede terminar hablando con ESE programa. Con 127.0.0.1 el
rem servidor y el navegador apuntan siempre al mismo lugar. Y el puerto
rem USB del Arduino lo acepta igual: para el navegador, 127.0.0.1 es
rem tan seguro como localhost.
set DIRECCION=127.0.0.1:%PUERTO%

echo.
echo   LIBRERIA
echo   --------
echo   Abriendo http://%DIRECCION%/ en el navegador...
echo.
echo   Usuarios de prueba:   admin / admin123     cliente / cliente123
echo.
echo   NO CIERRES ESTA VENTANA mientras uses el sistema: es el servidor.
echo   Para apagarlo, cerrala.
echo.

rem El navegador se abre 2 segundos despues, cuando el servidor ya esta
rem escuchando. Se pide Edge porque el boton "Conectar Arduino" necesita
rem Edge o Chrome: Firefox no tiene Web Serial.
start "" /b cmd /c "ping -n 3 127.0.0.1 >nul & start msedge http://%DIRECCION%/"

rem El servidor web que trae PHP adentro. -t libreria dice que carpeta
rem se publica. La ruta de las extensiones va completa (%~dp0 es la
rem carpeta de este .bat) porque una ruta relativa se mediria desde donde
rem se abrio la consola.
php\php.exe -c php\php.ini -d extension_dir="%~dp0php\ext" -S %DIRECCION% -t libreria

echo.
echo   El servidor se apago. Si fue por un error, el motivo esta arriba.
echo   (El mas comun: el puerto %PUERTO% lo esta usando otro programa.)
pause
