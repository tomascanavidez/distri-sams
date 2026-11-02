# Despliegue: GitHub → Ferozo (DonWeb)

Esquema elegido: el repositorio público de GitHub se vincula al hosting desde el panel de Ferozo y se clona en
`public_html/dev/distri-sams`. Cada vez que haya cambios en GitHub se actualiza desde el panel (pull).

```
GitHub (rama main)  ──(vincular / actualizar desde el panel)──►  public_html/dev/distri-sams/
                                                                  ├── .htaccess  → reenvía todo a /public y bloquea lo interno
                                                                  ├── public/    → lo único que se sirve
                                                                  ├── config/config.php   (lo crea el instalador, no está en git)
                                                                  └── storage/, public/uploads/  (datos del servidor, no están en git)
```

## Qué NO está en el repositorio (y por qué)

| Archivo / carpeta | Motivo |
|---|---|
| `config/config.php` | Tiene la contraseña de la base y la clave de sesión. Lo genera el instalador en el servidor. |
| `storage/logs`, `storage/backups`, `storage/sessions`, `storage/cache`, `storage/instalado.lock` | Datos propios de cada instalación (en el repo sólo viajan las carpetas vacías). |
| `public/uploads/productos`, `public/uploads/empresa` | Fotos y logo subidos desde el sistema. |

Como `.gitignore` los excluye, **actualizar desde GitHub no los pisa ni los borra**. Nunca subas `config/config.php` al
repositorio (es público).

## 1. Publicar el repositorio en GitHub (una sola vez)

1. En github.com › **New repository**: nombre por ejemplo `distri-toro`, **Public**, sin README ni .gitignore (ya existen).
2. En la carpeta del proyecto:
   ```bash
   git remote add origin https://github.com/USUARIO/distri-toro.git
   git push -u origin main
   ```

## 2. Preparar el hosting (una sola vez)

1. **PHP**: Panel Ferozo › *Configuración PHP* → versión **8.1 o superior**; extensiones `pdo_mysql`, `mbstring`, `gd`, `zip`, `zlib`.
2. **Base de datos**: Panel › *Bases de datos* › *MySQL* › crear una base y su usuario. Anotar servidor (normalmente
   `localhost`), nombre, usuario y contraseña (suelen llevar un prefijo como `c1234_`).
3. **Vincular el repositorio**: en la herramienta **Git** del panel, pegar la URL del repositorio
   (`https://github.com/USUARIO/distri-toro.git`), rama `main`, y como carpeta de destino `public_html/dev/distri-sams`.
   Si tu plan tiene SSH en lugar de la herramienta del panel, lo mismo por consola:
   ```bash
   cd ~/public_html/dev && git clone https://github.com/USUARIO/distri-toro.git distri-sams
   ```
4. **Permisos de escritura** (Administrador de archivos): `config/`, `storage/` y sus subcarpetas, y `public/uploads/` con sus
   subcarpetas en **755** (si el instalador marca error, **775**).
5. **SSL**: activar Let's Encrypt para el dominio (y luego para el subdominio). Es necesario para la cámara del celular y para
   instalar la app.

## 3. Instalar

1. Entrar a `https://TU-DOMINIO/dev/distri-sams/` → redirige al **instalador**.
2. Completar la base de datos, los datos de la empresa y el usuario administrador (con una contraseña propia, no `admin123`).
3. **Datos de demostración**: dejarlo destildado en la instalación real. Para probar con datos demo usá una base aparte; las
   contraseñas demo son públicas (están en el README).
4. Al terminar, el instalador escribe `config/config.php` en modo producción (`debug` en `false`) y se bloquea
   (`storage/instalado.lock`).

## 4. Cron de copias de seguridad

Panel › *Cron Jobs* › nuevo, todos los días a las 3:00:
```
/usr/bin/php /home/USUARIO_FEROZO/public_html/dev/distri-sams/cron/tareas_diarias.php
```
(La ruta exacta de PHP y de tu carpeta personal figura en el panel). Además, descargar cada tanto una copia desde
*Administración › Copias de seguridad* y guardarla fuera del servidor.

## 5. Actualizar cuando haya cambios en GitHub

1. *Administración › Copias de seguridad* › **Generar copia ahora** (por las dudas).
2. Panel Ferozo › Git › **Actualizar / Pull** (o `git pull` por SSH dentro de `public_html/dev/distri-sams`).
3. Entrar como administrador a *Administración › Actualizaciones de base*: si hay migraciones pendientes, **Aplicar**
   (o por consola `php cron/migrar.php`).
4. En los celulares no hace falta reinstalar: la app toma la versión nueva al abrirla con conexión.

> No uses opciones del panel del tipo "re-clonar", "borrar y volver a clonar" o `git clean -x`: borrarían `config/config.php`,
> las copias de seguridad y las fotos. Un `pull` normal no los toca.

## 6. Cuando crees el subdominio

Dos opciones, ambas funcionan sin tocar código (la ruta base se detecta sola):

- **Recomendada**: carpeta raíz del subdominio = `public_html/dev/distri-sams/public`. El código queda fuera de la web.
- Alternativa: carpeta raíz = `public_html/dev/distri-sams`. El `.htaccess` de la raíz reenvía a `/public` y bloquea `app`,
  `config`, `storage`, `vendor`, `.git`, etc.

Después:
1. Activar SSL para el subdominio.
2. Entrar por la nueva dirección (`https://gestion.TU-DOMINIO/`). Si en `config/config.php` se había completado `'url'`,
   actualizarla o dejarla vacía.
3. Los preventistas y choferes deben abrir la app en la **nueva** dirección y volver a "Agregar a pantalla de inicio"
   (cada dirección es una app distinta para el celular). Antes de cambiar, conviene que envíen lo pendiente.

## Verificación rápida después de instalar o actualizar

- `https://TU-DOMINIO/dev/distri-sams/login` muestra el ingreso con estilos.
- `https://TU-DOMINIO/dev/distri-sams/config/config.php`, `/app/routes.php`, `/.git/config`, `/storage/` y `/README.md`
  deben responder **403 Prohibido**.
- *Administración › Copias de seguridad* › generar una copia: si funciona, `storage` tiene permisos correctos.
- Desde un celular: ingresar con un usuario preventista y ver "Sincronizado" en la barra verde.

## Problemas típicos en Ferozo

| Problema | Solución |
|---|---|
| Página en blanco o error 500 | Versión de PHP < 8.1 o falta una extensión. Ver `storage/logs/app-AAAA-MM-DD.log` con el Administrador de archivos. |
| 404 en todas las páginas menos el inicio | Falta `mod_rewrite` o no se clonaron los `.htaccess` (verificar que existan `.htaccess` y `public/.htaccess`). |
| El instalador no puede escribir `config.php` | Permisos 775 en `config/` y `storage/`. |
| `.git/config` se ve desde el navegador | El subdominio apunta a otra carpeta o el `.htaccess` raíz no se aplica: revisar el paso 6. |
| Después de un pull aparece un error de base | Faltó aplicar migraciones (paso 5.3). |
