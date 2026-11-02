<?php
/**
 * Configuración de ejemplo de Distribuidora Toro.
 *
 * El instalador web (/install) crea automáticamente config/config.php.
 * Si preferís configurarlo a mano: copiá este archivo como config.php, completá los
 * datos de la base y creá el archivo storage/instalado.lock.
 */

return [
    'app' => [
        'nombre' => 'Distribuidora Toro',
        // true sólo para desarrollo: muestra el detalle de los errores en pantalla.
        // En producción dejar en false (los errores se registran en storage/logs).
        'debug' => false,
        // URL pública (opcional). Vacío = se detecta sola. Ej.: 'https://gestion.distribuidoratoro.com.ar'
        'url' => '',
        // Clave aleatoria para firmar la sesión. Cambiarla cierra todas las sesiones.
        'clave' => 'cambiar-por-una-cadena-larga-y-aleatoria',
    ],
    'db' => [
        'host' => 'localhost',
        'puerto' => 3306,
        'nombre' => 'nombre_de_la_base',
        'usuario' => 'usuario_mysql',
        'clave' => 'clave_mysql',
    ],
    'sesion' => [
        // Minutos de inactividad para cerrar la sesión (personal de oficina).
        'inactividad_min' => 60,
        // Preventistas y choferes trabajan todo el día con el celular.
        'inactividad_movil_min' => 720,
    ],
    'backup' => [
        // Cantidad de copias de seguridad que se conservan en storage/backups.
        'conservar' => 15,
    ],
];
