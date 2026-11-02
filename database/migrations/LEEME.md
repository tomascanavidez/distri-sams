# Migraciones

Para modificar la base en una actualización:

1. Crear un archivo con el siguiente número correlativo, por ejemplo `002_agrega_campo_x.sql`.
2. Escribir las sentencias SQL (cada una terminada en `;` al final de la línea).
3. Reflejar el mismo cambio en `database/schema.sql` (para instalaciones nuevas).
4. Subir el archivo al servidor y aplicar desde **Administración › Actualizaciones de base**
   o por consola: `php cron/migrar.php`.

También se aceptan migraciones `.php` que devuelven `function (PDO $pdo) { ... }`.
