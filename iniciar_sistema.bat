@echo off
title Sweet POS - Iniciador de Sistema

echo ====================================================
echo             INICIANDO SWEET POS
echo ====================================================
echo.

rem 1. Comprobar si PHP esta instalado
echo [1/4] Comprobando entorno de ejecucion [PHP]...
where php >nul 2>&1
if %errorlevel% equ 0 goto php_ok

echo [ERROR] PHP no esta instalado o no se encuentra en el PATH.
echo.
echo Por favor, instala PHP y agregalo al PATH del sistema.
echo.
pause
exit /b 1

:php_ok
echo [OK] PHP detectado correctamente.
echo.

rem 2. Inicializar y actualizar la base de datos SQLite
echo [2/4] Verificando e inicializando base de datos SQLite...
php init_db.php
php update_db.php
php update_db_2.php
php update_db_3.php
php update_db_4.php
php api\migrate.php
echo [OK] Base de datos verificada y actualizada.
echo.

rem 3. Abrir la aplicacion en el navegador
echo [3/4] Abriendo la aplicacion en tu navegador...
start "" "http://localhost:8000"
echo.

rem 4. Iniciar el servidor web local PHP
echo [4/4] Iniciando el servidor local en http://localhost:8000...
echo.
echo ====================================================
echo  SISTEMA INICIADO CORRECTAMENTE
echo.
echo  * El sistema funciona de forma 100%% OFFLINE.
echo  * Para detener el sistema, cierra esta ventana.
echo ====================================================
echo.
php -S localhost:8000
