# Distribuidora Toro — Sistema de gestión

Sistema integral para una distribuidora mayorista: productos y precios, stock con lotes, clientes y cuentas
corrientes, preventa con app para celular (funciona sin señal), ventas y facturación, reparto con app del chofer,
cobranzas, caja, bancos, cheques, compras, comisiones, reportes y administración.

- **PHP 8.1 / 8.2 / 8.3** puro (MVC propio, sin frameworks ni Composer) · **MySQL 5.7+ / MariaDB 10.4+** (InnoDB, utf8mb4)
- **Bootstrap 5 + Alpine.js + Chart.js** incluidos en el proyecto (sin CDN, sin Node en el servidor)
- Pensado para **DonWeb / Ferozo**: se sube por FTP y se instala desde el navegador.

## Contenido

```
distri-toro/
├── .htaccess            reenvía todo a /public y bloquea carpetas internas (si no se puede cambiar la raíz del dominio)
├── app/                 código (protegido)
│   ├── Core/            router, base de datos, sesión, autenticación, CSRF, vistas, validación, errores
│   ├── Controllers/     un controlador por módulo
│   ├── Services/        lógica de negocio: stock, precios, ventas, pedidos, reparto, cobranzas, compras, caja…
│   │   └── Facturacion/ interfaz ProveedorFacturacion (interna hoy, ARCA a futuro)
│   ├── Support/         menú, permisos, reportes, exportación CSV/XLSX/PDF, instalación, datos demo
│   ├── Views/           pantallas (PHP + Bootstrap + Alpine)
│   ├── helpers.php      formatos argentinos, escapado, URLs
│   └── routes.php       todas las rutas con su permiso
├── config/              config.example.php (el instalador genera config.php)
├── cron/                tareas_diarias.php (backup + bloqueo de morosos), backup.php, migrar.php
├── database/            schema.sql, datos_iniciales.sql, seed_demo.sql, migrations/
├── docs/                FACTURACION_ELECTRONICA.md
├── public/              raíz web: index.php, install/, assets/ (css, js, img, vendor), sw.js, uploads/
├── storage/             logs, backups, sesiones, caché (protegido, con permiso de escritura)
├── tests/               pruebas automatizadas (php tests/run.php) y de navegador (tests/navegador)
├── tools/               instalación por consola, generador de SQL, router para el servidor embebido
├── vendor/fpdf/         generación de PDF
└── *.md                 esta guía, manual de usuario, modelo de datos, decisiones y plan
```

## Requisitos

- PHP 8.1 o superior con `pdo_mysql`, `mbstring`, `gd`, `json`, `zlib` (opcional `zip` para exportar a Excel).
- MySQL 5.7+ o MariaDB 10.4+.
- Apache con `mod_rewrite` (Ferozo lo trae). HTTPS recomendado (necesario para la cámara del celular y para instalar la app).
- Permiso de escritura en `config/`, `storage/` (y subcarpetas) y `public/uploads/`.

## Instalación local (desarrollo)

Con XAMPP/LAMPP u otro Apache:

1. Copiar la carpeta en `htdocs` (por ejemplo `htdocs/distri-toro`).
2. Abrir `http://localhost/distri-toro/` → redirige al **instalador**. Completar la base (se crea si el usuario tiene permiso),
   empresa y administrador. Tildar "Cargar datos de demostración" para probar.

Sin Apache, con el servidor embebido de PHP:

```bash
php tools/instalar_dev.php --db=distri_toro --usuario=root --clave= --demo --debug   # crea base, datos demo y config
php -S localhost:8080 -t public tools/router_dev.php                                # http://localhost:8080
```

## Despliegue en DonWeb / Ferozo paso a paso

> **Despliegue desde GitHub** (vincular el repositorio en el panel de Ferozo y actualizar con pull): ver **[DEPLOY.md](DEPLOY.md)**.
> Abajo, la instalación subiendo los archivos a mano.

1. **Crear la base**: Panel Ferozo › *Bases de datos* › *MySQL* › *Nueva base*. Anotar **nombre, usuario y contraseña**
   (el servidor suele ser `localhost`).
2. **PHP**: Panel › *Configuración PHP*: elegir **8.1 o superior** y verificar que estén activas `pdo_mysql`, `mbstring`, `gd`, `zip`.
3. **Subir los archivos** por FTP (FileZilla) o *Administrador de archivos*. Dos opciones:
   - **A) Recomendada — subdominio o dominio apuntando a `/public`**: subir todo a una carpeta fuera de la web, por ejemplo
     `/home/USUARIO/distri-toro/`, y en Panel › *Dominios/Subdominios* configurar la carpeta raíz del sitio como
     `distri-toro/public` (ej. `gestion.midominio.com.ar`).
   - **B) Sin poder cambiar la raíz**: subir **todo el contenido** del proyecto dentro de `public_html/` (o de la carpeta
     del subdominio). El `.htaccess` de la raíz reenvía todo a `/public` y bloquea `app`, `config`, `storage`, `vendor`,
     `database`, `cron`, `tests`, `tools` y `docs` (además cada una tiene su propio `.htaccess` "Require all denied").
4. **Permisos**: `config/`, `storage/` (con `logs`, `backups`, `sessions`, `cache`) y `public/uploads/` en **755** (o 775 si el
   instalador indica que no puede escribir).
5. **SSL**: Panel › *SSL* › activar **Let's Encrypt** para el dominio. Necesario para la cámara (lector de códigos) y para
   "Agregar a pantalla de inicio".
6. **Instalar**: entrar a `https://tu-dominio/` (redirige a `/install/`), completar los datos y listo. El instalador crea las
   tablas, los datos iniciales, el administrador, escribe `config/config.php` (modo producción) y **se bloquea** creando
   `storage/instalado.lock`. Opcional: borrar `public/install`.
   - *Alternativa manual (phpMyAdmin)*: importar `database/schema.sql` y luego **uno** de: `database/datos_iniciales.sql`
     (instalación limpia, usuario `admin` / `admin123`) o `database/seed_demo.sql` (demo completa). Copiar
     `config/config.example.php` como `config/config.php`, completar la base y la `clave`, y crear el archivo vacío
     `storage/instalado.lock`.
7. **Cron de backups**: Panel › *Cron Jobs* › nuevo, diario a las 3:00:
   ```
   /usr/bin/php /home/USUARIO/RUTA/distri-toro/cron/tareas_diarias.php
   ```
   (genera la copia en `storage/backups`, conserva las últimas 15 y aplica el bloqueo automático de morosos si está activo).
   Descargar periódicamente una copia desde *Administración › Copias de seguridad* y guardarla fuera del servidor.
8. **Primer uso**: ingresar como administrador, cambiar la contraseña (*Mi clave*), completar *Administración › Empresa y
   parámetros* (CUIT, logo, condición de IVA), cargar usuarios y asociar preventistas y choferes a sus usuarios.

### Actualizar a una nueva versión

1. Hacer una copia de seguridad. 2. Subir los archivos nuevos **sin pisar** `config/config.php`, `storage/` ni `public/uploads/`.
3. Aplicar migraciones pendientes en *Administración › Actualizaciones de base* (o `php cron/migrar.php`).

## Usuarios de demostración

Cargados con "datos de demostración" (o `seed_demo.sql`):

| Usuario | Contraseña | Rol | Entra a |
|---|---|---|---|
| `admin` | `admin123` | Administrador | Panel principal |
| `oficina` | `oficina123` | Encargado / Oficina | Panel principal |
| `mostrador` | `mostrador123` | Vendedor de mostrador | Mostrador (POS) |
| `preventista1` | `preventista123` | Preventista (Centro y Norte) | App "Mi ruta de hoy" |
| `preventista2` | `preventista123` | Preventista (Sur y Oeste) | App "Mi ruta de hoy" |
| `deposito` | `deposito123` | Depósito | Stock |
| `chofer1` | `chofer123` | Chofer / Repartidor | App "Mis entregas de hoy" |
| `chofer2` | `chofer123` | Chofer / Repartidor | App "Mis entregas de hoy" |
| `contador` | `contador123` | Contador (sólo reportes) | Reportes |

Si se instaló con el instalador web, el administrador es el que se eligió allí. **Cambiar todas las contraseñas antes de usar
el sistema en serio.**

La demo incluye 74 productos de 8 rubros con presentaciones (unidad, display, pack, caja), 5 proveedores, 25 clientes en 4 zonas
de Salta Capital, 2 preventistas, 2 choferes, 1 camión y unas 4 semanas de operación: compras, pedidos, hojas de ruta rendidas,
una en reparto hoy, ventas de mostrador, cobranzas, cheques, gastos, una NC, una anulación, comisiones y un inventario.

## App del celular (preventistas y choferes)

- Ingresar desde el celular a la dirección del sistema; cada rol entra a su app (`/movil` o `/chofer`).
- Menú del navegador › **Agregar a pantalla de inicio** para instalarla (abre a pantalla completa con el ícono de la empresa).
- Abrirla **una vez con conexión** para descargar datos. Después funciona sin señal: los pedidos, visitas y entregas quedan en
  el celular y se envían solos al volver la conexión (la barra superior muestra "Sin conexión", "X pendientes" o "Sincronizado").

## Pruebas

```bash
php tests/run.php                 # 49 pruebas: precios e IVA, presentaciones, stock y reservas, lotes, cuenta corriente,
                                  # anulaciones, compras y costos, reparto y rendición, seguridad, ciclo completo
cd tests/navegador && npm install && node todas.js   # navegador (Chrome): celular 360/390/412 px, preventista y chofer
                                                      # con corte de conexión. Variables: DT_URL, CHROME
```
Las pruebas PHP crean y borran su propia base (`<base>_test`). Las de navegador usan la base con datos demo.
`tests/` y `tools/` no son necesarios en el hosting.

## Solución de problemas

| Problema | Solución |
|---|---|
| Error 500 apenas se entra | Verificar la versión de PHP (8.1+) en el panel. Ver `storage/logs/app-AAAA-MM-DD.log`. Para ver el detalle temporalmente, `'debug' => true` en `config/config.php` (volver a `false`). |
| "No se encontró la página" en todas las URLs | `mod_rewrite` no está activo o no se subió el `.htaccess` (son archivos ocultos: activar "mostrar ocultos" en FileZilla). |
| Estilos rotos (página sin diseño) | Se subió sólo `public/` en un lugar y el resto en otro sin ajustar la raíz; revisar el paso 3. Si el sistema está en una subcarpeta rara, completar `'url'` en `config/config.php`. |
| El instalador dice "sin permiso de escritura" | Poner 755/775 en `config`, `storage` y `public/uploads` desde el administrador de archivos. |
| No conecta a la base | En Ferozo el servidor es `localhost`; el nombre y el usuario suelen llevar un prefijo (`c1234_`). |
| "Demasiados intentos fallidos" | Esperar 15 minutos o borrar los registros de la tabla `login_intentos` desde phpMyAdmin. |
| Olvidé la contraseña del admin | En phpMyAdmin: generar un hash con `php -r "echo password_hash('nueva', PASSWORD_DEFAULT);"` y pegarlo en `usuarios.password_hash`. |
| La cámara no lee códigos | Requiere **HTTPS** y permiso de cámara en el navegador. También se puede tipear o usar un lector USB (funciona como teclado). |
| La app del celular no muestra datos nuevos | Tocar la barra de estado o *Más › Actualizar precios y stock*. Si la sesión venció, la barra lo indica: volver a ingresar (lo pendiente no se pierde). |
| "La caja está cerrada" al cobrar en efectivo | Abrirla en *Finanzas › Caja*. Cada usuario cobra en la caja asignada en su usuario. |
| El cron no genera copias | Usar la ruta completa de PHP que indica el panel (`/usr/bin/php` o `/usr/local/bin/php8.1`) y la ruta absoluta del script. |

Más información: `MANUAL_USUARIO.md` (uso de cada módulo), `DATABASE.md` (modelo de datos), `DECISIONES.md` (criterios adoptados),
`docs/FACTURACION_ELECTRONICA.md` (cómo integrar ARCA).
