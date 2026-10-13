# SweetPOS

## Despliegue con Docker en Dokploy

El contenedor usa la imagen oficial `php:8.4.26-apache-bookworm`, ejecuta Apache como `www-data` y escucha en `0.0.0.0:8080`. El `HEALTHCHECK` consulta `http://127.0.0.1:8080/`. Dokploy debe usar el puerto interno 8080.

La aplicación usa SQLite y guarda la base en `/data/database.sqlite`. El Dockerfile declara `/data` como volumen; configura un volumen persistente escribible por UID 33 (`www-data`) en Dokploy. Esta aplicación no consume `DATABASE_URL`, así que no selecciones PostgreSQL automático. Si Hexper Ops no permite asignar un volumen persistente, detén el despliegue hasta migrar la aplicación a PostgreSQL o confirmar una alternativa de almacenamiento persistente.

Al arrancar, el contenedor aplica las migraciones de SQLite y crea las cuentas iniciales antes de iniciar Apache. En una base nueva, configura estas variables como secretos de ejecución en Dokploy:

- `SWEETPOS_BOOTSTRAP_ADMIN_PASSWORD`
- `SWEETPOS_BOOTSTRAP_GERENTE_PASSWORD`
- `SWEETPOS_BOOTSTRAP_VENDEDOR_PASSWORD`

Cada valor debe tener al menos 12 caracteres. No los pongas en Git, archivos `.env` dentro del repositorio, el Dockerfile ni argumentos de compilación. Cuando la base persistente ya tenga las cuentas, elimina esos tres secretos de la configuración de ejecución; la aplicación no los necesita después de inicializar usuarios.

La primera ejecución local de `iniciar_sistema.bat` también necesita esas variables en el entorno de Windows. Las instalaciones existentes con usuarios ya creados no necesitan volver a configurarlas.

El Docker context excluye la base SQLite local, archivos `.env`, credenciales comunes, pruebas, documentación y configuración local de Vercel. No incluye scripts de inicialización manual en la imagen.

## Compilar y ejecutar localmente

Crea un archivo de entorno fuera del repositorio con los tres secretos de inicialización y úsalo solo al ejecutar el contenedor. No lo pases como argumento de compilación.

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
php tests/test_pdf.php
```

## GitHub y Hexper Ops

En Hexper Ops, selecciona el proveedor GitHub asignado, el repositorio y la rama autorizada. Usa `/` como ruta de compilación y deja que Hexper Ops configure el puerto interno 8080. Verifica en Dokploy que el volumen `/data` persista antes de confirmar el despliegue. El proveedor GitHub, la asignación a usuarios y el disparador de despliegues automáticos se configuran fuera de este repositorio.
## Seguridad de cuentas existentes

Versiones anteriores del repositorio contenían contraseñas iniciales en el código. El código actual ya no las incluye, pero eliminar archivos actuales no borra commits anteriores. Si este repositorio se compartió o esas cuentas se inicializaron antes, cambia las contraseñas de las tres cuentas antes de producción y no reutilices las anteriores. La limpieza del historial Git requiere un cambio coordinado del historial remoto.
