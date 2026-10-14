# SweetPOS

## Despliegue en Hexper Ops y Dokploy

La imagen usa PHP 8.4 con Apache, ejecuta el proceso como `www-data` y escucha en `0.0.0.0:8080`. Declara solo `EXPOSE 8080`; el healthcheck consulta `http://127.0.0.1:8080/`. En Hexper Ops selecciona la ruta `/` y deja que configure el puerto interno 8080.

La aplicación detecta `DATABASE_URL` al iniciar. Cuando Dokploy la inyecta para PostgreSQL, SweetPOS conecta a esa base, aplica las migraciones pendientes y prepara las cuentas iniciales antes de iniciar Apache. No requiere migraciones manuales posteriores al despliegue. La aplicación no publica un puerto para PostgreSQL ni administra sus usuarios, contraseñas o reglas de acceso.

Si `DATABASE_URL` no está configurada, la aplicación usa SQLite en `SWEETPOS_DB_PATH` (por defecto `/data/database.sqlite`). Esta alternativa es para desarrollo o instalaciones que tengan almacenamiento persistente configurado; para PostgreSQL automático no hace falta montar `/data`.

### Variables de ejecución

- `DATABASE_URL`: la inyecta Dokploy al seleccionar PostgreSQL automático. No la copies al repositorio ni la compartas en solicitudes o registros.
- `SWEETPOS_BOOTSTRAP_ADMIN_PASSWORD`
- `SWEETPOS_BOOTSTRAP_GERENTE_PASSWORD`
- `SWEETPOS_BOOTSTRAP_VENDEDOR_PASSWORD`

Las tres contraseñas de inicialización solo se requieren cuando la base no tiene usuarios. Configúralas como secretos de ejecución en el administrador autorizado, con al menos 12 caracteres. Tras crear las cuentas, puedes retirar esos tres secretos de la configuración. Si usas SQLite, configura también `SWEETPOS_DB_PATH` si necesitas guardar la base en otra ruta persistente.

En Dokploy, selecciona PostgreSQL automático para la aplicación y confirma que `DATABASE_URL` está disponible dentro del contenedor. Cada push a la rama configurada inicia un despliegue; confirma la rama antes de subir cambios.

## Compilar y ejecutar localmente

El contenedor puede probarse con SQLite. Configura las tres contraseñas de inicialización en un archivo de entorno fuera del repositorio y móntalo solo al ejecutar:

```sh
docker build -t sweetpos:local .
docker run --rm --name sweetpos -p 8080:8080 -v sweetpos-data:/data --env-file /ruta/segura/fuera-del-repositorio/sweetpos.env sweetpos:local
curl --fail http://127.0.0.1:8080/
docker inspect --format '{{.State.Health.Status}}' sweetpos
```

El estado de salud esperado es `healthy`. El volumen `sweetpos-data` conserva la base SQLite entre recreaciones del contenedor.

## Verificaciones disponibles

```sh
php tests/test_bootstrap_users.php
php tests/test_db_connection.php
php tests/test_pdf.php
```

## Proveedor GitHub

El proveedor de GitHub, la asignación de proveedor a usuarios y el disparador automático de despliegues se configuran fuera de este repositorio. Usa una invitación restringida de Dokploy y autoriza solamente los repositorios necesarios. Nunca pegues tokens, contraseñas ni claves privadas en Hexper Ops o en herramientas de IA.

## Seguridad de cuentas existentes

Versiones anteriores del repositorio contenían contraseñas iniciales en el código. El código actual ya no las incluye, pero eliminar archivos actuales no borra commits anteriores. Si este repositorio se compartió o esas cuentas se inicializaron antes, cambia las contraseñas de las tres cuentas antes de producción y no reutilices las anteriores. La limpieza del historial Git requiere un cambio coordinado del historial remoto.
