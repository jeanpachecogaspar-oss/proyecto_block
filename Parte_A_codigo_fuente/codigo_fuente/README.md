# Quipu Noticias

Blog de noticias autoadministrable en PHP 8 + MySQL (Producto Académico N.º 2, Pruebas y Calidad de Software).

Requisitos: PHP 8.1 o superior con extensiones pdo_mysql, mbstring, fileinfo y gd (con WebP); MySQL 8 o MariaDB 10.4+.

## Instalación con MySQL (XAMPP o similar)

1. Copia la carpeta del proyecto, por ejemplo a `htdocs/quipu`.
2. Importa la base: `mysql -u root -p < database/quipu_noticias.sql` (o pega el script en phpMyAdmin, pestaña SQL).
3. Copia `.env.example` como `.env` y ajusta:
   - `DB_USER` y `DB_PASS` (con XAMPP: `root` y vacío).
   - `APP_BASE=/quipu/public` si usas `htdocs/quipu`.
4. Abre `http://localhost/quipu/public/`.

Alternativa sin Apache: `php -S localhost:8000 -t public` (deja `APP_BASE` vacío) y abre `http://localhost:8000`.

Asegúrate de que `public/uploads` tenga permiso de escritura.

## Probar sin MySQL (SQLite)

    php database/instalar_sqlite.php
    # en .env: DB_DRIVER=sqlite  y  DB_SQLITE_PATH=database/quipu_dev.sqlite
    php -S localhost:8000 -t public

## Cuentas de demostración

| Rol | Correo | Contraseña |
|---|---|---|
| admin | admin@quipunoticias.test | Admin#Quipu2026 |
| autor | autor@quipunoticias.test | Autor#Quipu2026 |
| lector | lector@quipunoticias.test | Lector#Quipu2026 |

Cámbialas antes de publicar el sitio.

## Pruebas PHPUnit

    composer install
    composer test

## Estructura

- `public/`: páginas web, `assets/` (CSS, JS), `uploads/` (imágenes) y `admin/` (panel).
- `src/`: núcleo (`Core`), validación y utilidades (`Support`) y acceso a datos (`Repository`).
- `templates/`: cabecera, pie y parciales.
- `database/`: script SQL, esquema SQLite e instaladores.
- `tests/`: pruebas unitarias y de integración.

## Funciones extra propias

Modo oscuro persistente (botón de la cabecera) y tiempo de lectura estimado en cada noticia.
