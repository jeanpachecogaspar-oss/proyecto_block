-- Esquema equivalente en SQLite. Solo para desarrollo rápido y para las pruebas
-- PHPUnit de integración (no requiere servidor MySQL). La base real es MySQL:
-- ver database/quipu_noticias.sql.

PRAGMA foreign_keys = ON;

CREATE TABLE usuarios (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre         TEXT NOT NULL,
    email          TEXT NOT NULL UNIQUE,
    password_hash  TEXT NOT NULL,
    rol            TEXT NOT NULL DEFAULT 'lector' CHECK (rol IN ('admin','autor','lector')),
    activo         INTEGER NOT NULL DEFAULT 1,
    creado_en      TEXT NOT NULL,
    ultimo_acceso  TEXT NULL
);

CREATE TABLE categorias (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre       TEXT NOT NULL UNIQUE,
    slug         TEXT NOT NULL UNIQUE,
    descripcion  TEXT NOT NULL DEFAULT ''
);

CREATE TABLE noticias (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    autor_id         INTEGER NOT NULL REFERENCES usuarios (id) ON DELETE RESTRICT,
    categoria_id     INTEGER NOT NULL REFERENCES categorias (id) ON DELETE RESTRICT,
    titulo           TEXT NOT NULL,
    slug             TEXT NOT NULL UNIQUE,
    resumen          TEXT NOT NULL,
    contenido        TEXT NOT NULL,
    imagen           TEXT NULL,
    imagen_alt       TEXT NULL,
    imagen_ancho     INTEGER NULL,
    imagen_alto      INTEGER NULL,
    estado           TEXT NOT NULL DEFAULT 'borrador' CHECK (estado IN ('borrador','publicada')),
    minutos_lectura  INTEGER NOT NULL DEFAULT 1,
    vistas           INTEGER NOT NULL DEFAULT 0,
    publicado_en     TEXT NULL,
    creado_en        TEXT NOT NULL,
    actualizado_en   TEXT NOT NULL
);
CREATE INDEX idx_noticias_estado_fecha ON noticias (estado, publicado_en);
CREATE INDEX idx_noticias_cat_estado_fecha ON noticias (categoria_id, estado, publicado_en);
CREATE INDEX idx_noticias_estado_vistas ON noticias (estado, vistas);
CREATE INDEX idx_noticias_autor_creado ON noticias (autor_id, creado_en);

CREATE TABLE comentarios (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    noticia_id  INTEGER NOT NULL REFERENCES noticias (id) ON DELETE CASCADE,
    usuario_id  INTEGER NOT NULL REFERENCES usuarios (id) ON DELETE CASCADE,
    contenido   TEXT NOT NULL,
    estado      TEXT NOT NULL DEFAULT 'visible' CHECK (estado IN ('visible','oculto')),
    creado_en   TEXT NOT NULL
);
CREATE INDEX idx_comentarios_noticia ON comentarios (noticia_id, estado, creado_en);
CREATE INDEX idx_comentarios_usuario ON comentarios (usuario_id, creado_en);

CREATE TABLE intentos_login (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL,
    ip         TEXT NOT NULL,
    creado_en  TEXT NOT NULL
);
CREATE INDEX idx_intentos_busqueda ON intentos_login (email, ip, creado_en);
