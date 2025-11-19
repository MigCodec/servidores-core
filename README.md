
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

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

