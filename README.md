
## Respaldo de la base de datos en Google Drive

Este proyecto utiliza SQLite (`database/database.sqlite`) como base de datos por defecto. Para mantener una copia en Google Drive mediante OAuth:

1. En Google Cloud Console crea un proyecto, habilita la API de Drive y genera un cliente OAuth (Web o Desktop). Descarga el archivo JSON resultante, comparte la carpeta/copia maestra con la cuenta autorizada y consigue un refresh token con el alcance `https://www.googleapis.com/auth/drive.file` (puedes usar [OAuth Playground](https://developers.google.com/oauthplayground)).
2. Define las variables en `.env`:
   - `GOOGLE_DRIVE_CREDENTIALS`: ruta/JSON/base64 del archivo de credenciales OAuth.
   - `GOOGLE_DRIVE_FOLDER_ID`: ID de la carpeta de Drive que recibirá los respaldos históricos (opcional; si lo omites, se subirán a Mi unidad).
   - `GOOGLE_DRIVE_REFRESH_TOKEN`: refresh token emitido para el usuario autorizado. Puedes generarlo ejecutando `php artisan drive:oauth:init`, que imprime el enlace de autorización y te devuelve el token listo para copiarlo.
   - `GOOGLE_DRIVE_MASTER_FILE_ID`: ID del archivo “maestro” (el SQLite original) que deseas sincronizar en cada arranque.
   - `GOOGLE_DRIVE_SYNC_ON_BOOT`: establece `true` si quieres que Laravel sincronice automáticamente contra el archivo maestro al arrancar.
   - Si aún no tienes archivo maestro en Drive, ejecuta `php artisan db:init-drive-master` para subir el SQLite actual y escribir automáticamente el ID en tu `.env`.
   - También puedes ir a **Dashboard → Google Drive** (visible solo para administradores) y presionar “Generar / renovar refresh token” para lanzar el mismo flujo desde la interfaz; al finalizar se actualizará `GOOGLE_DRIVE_REFRESH_TOKEN` automáticamente.
3. Ejecuta `php artisan db:sync-drive` para forzar una sincronización inmediata: descargará el archivo maestro, insertará en local cualquier fila que falte y actualizará el archivo maestro con el contenido local (útil al inicio o tras cambios manuales).
4. Usa `php artisan db:backup-drive` cuando quieras conservar snapshots con nombre único en la carpeta de respaldos.
5. Si deseas automatizar los comandos, agrégalos al cron o al proceso de arranque del servicio que prefieras.

Mientras `GOOGLE_DRIVE_SYNC_ON_BOOT=true`, la aplicación ejecutará el sincronizador al iniciar (se ignora durante las pruebas). Los registros nunca se eliminan definitivamente: los modelos principales ahora usan “soft deletes”, así que los `delete()` solo marcan `deleted_at` sin perder la información histórica.

## WebSocket de terminal (xterm.js + phpseclib)

La app expone un terminal SSH en vivo a través de un WebSocket levantado por un comando Artisan. En producción debes correrlo como servicio separado.

### Pasos para desplegar el WS con Supervisor
1. Define en `.env` el endpoint interno del WS y el proxy externo:
   - `WS_PORT=7001`
   - `WS_URL=wss://tu-dominio/ws-terminal/` (ruta proxied por Nginx/Apache)
2. Genera el archivo de Supervisor:
   ```
   php artisan terminal:ws:supervisor --port=7001 --user=www-data --program=terminal-ws
   ```
   Esto crea `storage/app/terminal-ws.conf` con `command=/usr/bin/php /ruta/proyecto/artisan terminal:ws --port=7001`.
3. Copia el archivo a Supervisor y arranca el servicio:
   ```
   sudo cp storage/app/terminal-ws.conf /etc/supervisor/conf.d/
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start terminal-ws
   ```
4. Configura el proxy WSS (ejemplo Nginx):
   ```
   location /ws-terminal/ {
       proxy_pass         http://127.0.0.1:7001;
       proxy_http_version 1.1;
       proxy_set_header   Upgrade $http_upgrade;
       proxy_set_header   Connection "Upgrade";
       proxy_set_header   Host $host;
       proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header   X-Forwarded-Proto $scheme;
       proxy_read_timeout 3600;
   }
   ```
5. Reinicia la app con las cachés regeneradas (`php artisan config:cache && php artisan route:cache`) para que el frontend use `WS_URL`.
6. Limpieza de claves temporales: si quedan entradas `core-session` en `authorized_keys`, ejecuta
   ```
   php artisan terminal:ws:cleanup
   ```
   (opcionalmente `php artisan terminal:ws:cleanup {serverId}`) para limpiar en todos o un servidor específico.

Con esto el WS queda siempre levantado leyendo el mismo `.env`/APP_KEY que la app y accesible vía WSS a través del proxy.
