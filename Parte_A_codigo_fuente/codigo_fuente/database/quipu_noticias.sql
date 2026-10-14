-- =============================================================================
--  QUIPU NOTICIAS - Script de base de datos (MySQL 8 / MariaDB 10.4 o superior)
--  Producto Académico N.º 2 - Pruebas y Calidad de Software
--
--  Qué hace:
--    1. Crea la base "quipu_noticias" (UTF-8 completo, utf8mb4).
--    2. Crea las 5 tablas con claves foráneas e índices para las consultas reales.
--    3. Inserta datos de demostración (3 usuarios, 5 categorías, 9 noticias,
--       6 comentarios).
--
--  ADVERTENCIA: borra y vuelve a crear las tablas del proyecto. Úsalo solo en
--  una base de desarrollo o de pruebas.
--
--  Cómo ejecutarlo:
--    mysql -u root -p < database/quipu_noticias.sql
--    (o pega el contenido en phpMyAdmin > pestaña SQL)
--
--  Cuentas de demostración (cámbialas antes de publicar el sitio):
--    admin@quipunoticias.test   /  Admin#Quipu2026    (rol admin)
--    autor@quipunoticias.test   /  Autor#Quipu2026    (rol autor)
--    lector@quipunoticias.test  /  Lector#Quipu2026   (rol lector)
--  Las contraseñas se guardan como hash bcrypt (password_hash de PHP), nunca en claro.
--
--  Recomendado en producción: usar un usuario de base de datos con privilegios
--  mínimos (no root). Ejemplo, ejecutado una sola vez por el administrador:
--    CREATE USER 'quipu_app'@'localhost' IDENTIFIED BY 'una_clave_larga_y_unica';
--    GRANT SELECT, INSERT, UPDATE, DELETE ON quipu_noticias.* TO 'quipu_app'@'localhost';
-- =============================================================================

CREATE DATABASE IF NOT EXISTS quipu_noticias
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE quipu_noticias;
SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS intentos_login;
DROP TABLE IF EXISTS comentarios;
DROP TABLE IF EXISTS noticias;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- usuarios: cuentas con rol (admin, autor, lector).
-- El correo es único (índice uq_usuarios_email) y se guarda en minúsculas.
-- -----------------------------------------------------------------------------
CREATE TABLE usuarios (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre         VARCHAR(60)  NOT NULL,
    email          VARCHAR(120) NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    rol            ENUM('admin','autor','lector') NOT NULL DEFAULT 'lector',
    activo         TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en      DATETIME     NOT NULL,
    ultimo_acceso  DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_rol (rol, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- categorias: clasificación de las noticias. nombre y slug son únicos.
-- -----------------------------------------------------------------------------
CREATE TABLE categorias (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(50)  NOT NULL,
    slug         VARCHAR(60)  NOT NULL,
    descripcion  VARCHAR(200) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_nombre (nombre),
    UNIQUE KEY uq_categorias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- noticias: contenido principal. "contenido" es texto plano con sintaxis mínima
-- (se escapa al mostrar). Una noticia no puede quedar sin autor ni categoría
-- (RESTRICT): primero se reasignan o se eliminan las noticias.
--
-- Índices (eficiencia):
--   idx_noticias_estado_fecha      listado de portada:  WHERE estado + ORDER BY publicado_en
--   idx_noticias_cat_estado_fecha  listado por categoría
--   idx_noticias_estado_vistas     bloque "Lo más leído"
--   idx_noticias_autor_creado      panel del autor
-- -----------------------------------------------------------------------------
CREATE TABLE noticias (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    autor_id         INT UNSIGNED NOT NULL,
    categoria_id     INT UNSIGNED NOT NULL,
    titulo           VARCHAR(150) NOT NULL,
    slug             VARCHAR(190) NOT NULL,
    resumen          VARCHAR(300) NOT NULL,
    contenido        MEDIUMTEXT   NOT NULL,
    imagen           VARCHAR(80)  NULL,
    imagen_alt       VARCHAR(160) NULL,
    imagen_ancho     SMALLINT UNSIGNED NULL,
    imagen_alto      SMALLINT UNSIGNED NULL,
    estado           ENUM('borrador','publicada') NOT NULL DEFAULT 'borrador',
    minutos_lectura  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    vistas           INT UNSIGNED NOT NULL DEFAULT 0,
    publicado_en     DATETIME NULL,
    creado_en        DATETIME NOT NULL,
    actualizado_en   DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_noticias_slug (slug),
    KEY idx_noticias_estado_fecha (estado, publicado_en),
    KEY idx_noticias_cat_estado_fecha (categoria_id, estado, publicado_en),
    KEY idx_noticias_estado_vistas (estado, vistas),
    KEY idx_noticias_autor_creado (autor_id, creado_en),
    CONSTRAINT fk_noticias_autor FOREIGN KEY (autor_id) REFERENCES usuarios (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_noticias_categoria FOREIGN KEY (categoria_id) REFERENCES categorias (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- comentarios: al borrar una noticia se borran sus comentarios (CASCADE).
-- "estado" permite ocultar un comentario sin perderlo (moderación reversible).
-- -----------------------------------------------------------------------------
CREATE TABLE comentarios (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    noticia_id  INT UNSIGNED NOT NULL,
    usuario_id  INT UNSIGNED NOT NULL,
    contenido   VARCHAR(1000) NOT NULL,
    estado      ENUM('visible','oculto') NOT NULL DEFAULT 'visible',
    creado_en   DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_comentarios_noticia (noticia_id, estado, creado_en),
    KEY idx_comentarios_usuario (usuario_id, creado_en),
    CONSTRAINT fk_comentarios_noticia FOREIGN KEY (noticia_id) REFERENCES noticias (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_comentarios_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- intentos_login: registro de intentos fallidos para limitar la fuerza bruta
-- (5 fallos en 15 minutos por correo + IP).
-- -----------------------------------------------------------------------------
CREATE TABLE intentos_login (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(120) NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    creado_en  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_intentos_busqueda (email, ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==== DATOS DE DEMOSTRACIÓN ====
-- (Todo lo que sigue usa INSERT estándar; database/instalar_sqlite.php lo reutiliza.)

INSERT INTO usuarios (id, nombre, email, password_hash, rol, activo, creado_en) VALUES
(1, 'Marisol Quispe', 'admin@quipunoticias.test', '$2y$10$452LPEDKk2C8slM3rIzQ5OItYKRVNhDJmKeHvvihNwn8seIsDhnrK', 'admin', 1, '2026-09-01 08:00:00'),
(2, 'Lucía Paredes', 'autor@quipunoticias.test', '$2y$10$ZMDyux1.qhMhtNqma2flwub3E0x8waIwJtzw8L9ZnWMt9xbdJy3sy', 'autor', 1, '2026-09-01 08:10:00'),
(3, 'Diego Ñahui', 'lector@quipunoticias.test', '$2y$10$PeL9yivr5Vn59NO/K.YeRehO3gDDS/63ynGTldFiEHssvroBI2JSO', 'lector', 1, '2026-09-02 19:30:00');

INSERT INTO categorias (id, nombre, slug, descripcion) VALUES
(1, 'Comunidad', 'comunidad', 'Vida cotidiana, vecinos y servicios del valle.'),
(2, 'Economía', 'economia', 'Producción, mercados y economía familiar.'),
(3, 'Cultura', 'cultura', 'Tradiciones, ferias, talleres y creadores locales.'),
(4, 'Deporte', 'deporte', 'Ligas barriales, clubes y deporte escolar.'),
(5, 'Ambiente', 'ambiente', 'Agua, residuos, biodiversidad y acciones vecinales.');

INSERT INTO noticias (id, autor_id, categoria_id, titulo, slug, resumen, contenido, imagen, imagen_alt, imagen_ancho, imagen_alto, estado, minutos_lectura, vistas, publicado_en, creado_en, actualizado_en) VALUES
(1, 2, 1, 'Villa Chaska estrena su primer mercado de trueque mensual', 'villa-chaska-estrena-su-primer-mercado-de-trueque-mensual', 'Cada tercer sábado, familias del valle intercambiarán semillas, tejidos y conservas sin usar dinero, en la plaza principal.', 'La plaza principal de Villa Chaska amaneció este sábado con unos cuarenta puestos de tela blanca y una regla sencilla: aquí no se paga con billetes. Quien llega con un costal de papa nativa puede volver a casa con un abrigo de lana, y quien trae frascos de mermelada se lleva semillas de habas para la próxima siembra.

## Cómo funciona

Las organizadoras, un grupo de vecinas de la junta comunal, entregaron a cada participante una libreta para anotar lo que ofrece y lo que busca. Una mesa de apoyo ayudó a equilibrar los intercambios cuando el valor de los productos no coincidía.

- Fecha fija: tercer sábado de cada mes, de 8 a 13 horas.
- Se aceptan semillas, tejidos, conservas y herramientas en buen estado.
- No se admiten productos que necesiten refrigeración.

Según el registro de la jornada, se concretaron 112 intercambios. La próxima edición incluirá un rincón para reparar herramientas de manera gratuita.', 'demo_1.webp', 'Ilustración de cordones claros con nudos de colores sobre un fondo azul petróleo', 1200, 630, 'publicada', 1, 128, '2026-10-10 09:30:00', '2026-10-09 17:00:00', '2026-10-10 09:30:00'),
(2, 2, 2, 'Productores de quinua de Ankarí cierran su mejor campaña en diez años', 'productores-de-quinua-de-ankari-cierran-su-mejor-campana-en-diez-anos', 'La asociación agrícola reporta más toneladas por hectárea tras adoptar riego por goteo y un calendario de siembra compartido.', 'La Asociación de Productores Sol del Valle informó que el rendimiento promedio de quinua subió de 1,4 a 2,1 toneladas por hectárea en la campaña que acaba de cerrar. Es la cifra más alta que registran sus libros desde hace una década.

## Qué cambió en el campo

Los socios atribuyen la mejora a tres decisiones tomadas hace dos temporadas:

- Riego por goteo en las parcelas más secas, financiado con un fondo rotatorio.
- Un calendario de siembra compartido para evitar que todas las parcelas maduren al mismo tiempo.
- Compra conjunta de costales y herramientas para bajar el costo por familia.

## Lo que todavía pesa

El transporte hasta el centro de acopio sigue siendo el gasto más alto. La directiva evalúa alquilar un camión entre varias comunidades para repartir ese costo y negociar mejores precios con los compradores.', 'demo_2.webp', 'Ilustración de cordones con nudos naranjas sobre un fondo marrón oscuro', 1200, 630, 'publicada', 1, 96, '2026-10-07 08:15:00', '2026-10-06 16:20:00', '2026-10-07 08:15:00'),
(3, 2, 3, 'La feria del tejido de Tambo Alto reúne a cuarenta familias artesanas', 'la-feria-del-tejido-de-tambo-alto-reune-a-cuarenta-familias-artesanas', 'Durante tres días se mostraron mantas, ponchos y chalinas teñidas con plantas del valle, además de demostraciones en vivo.', 'Tambo Alto vistió sus calles con mantas, ponchos y chalinas durante la feria anual del tejido. Cuarenta familias artesanas montaron sus puestos a lo largo de la calle principal y, por primera vez, también en el patio de la escuela.

Una de las zonas más visitadas fue la de tintes naturales, donde las tejedoras mostraron cómo el nogal, la cochinilla y varias plantas de la zona producen tonos que van del marrón profundo al rojo intenso. Cada demostración terminaba con una prueba de color sobre lana todavía húmeda.

## Para los más jóvenes

La feria incluyó un taller de iniciación al telar de cintura. Participaron veintitrés niñas y niños que aprendieron a urdir y a trenzar flecos. Las organizadoras quieren repetirlo durante las vacaciones escolares.', 'demo_3.webp', 'Ilustración de cordones con nudos verdes y amarillos sobre un fondo verde bosque', 1200, 630, 'publicada', 1, 143, '2026-10-04 10:00:00', '2026-10-03 12:40:00', '2026-10-04 10:00:00'),
(4, 2, 4, 'Juventud Ayllu gana la liguilla de vóley de verano', 'juventud-ayllu-gana-la-liguilla-de-voley-de-verano', 'El equipo del barrio remontó un set en contra y venció por 3 a 2 en una final que se definió por dos puntos.', 'Con el coliseo techado lleno, Juventud Ayllu se coronó campeón de la liguilla de vóley de verano al vencer por 3 sets a 2 a Amigos del Puente. El partido se definió en el quinto set por 15 a 13, después de que el conjunto ganador perdiera el segundo set con claridad.

La capitana, Rocío Ccori, destacó el trabajo de la defensa en los últimos minutos. El entrenador recordó que el plantel entrena tres veces por semana en una losa prestada por la parroquia, y agradeció a las familias que costearon los uniformes.

La liguilla reunió a ocho equipos de cuatro barrios. La organización anunció que el próximo torneo tendrá una categoría para menores de quince años.', 'demo_4.webp', 'Ilustración de cordones con nudos rosados y azules sobre un fondo violeta oscuro', 1200, 630, 'publicada', 1, 57, '2026-10-02 21:10:00', '2026-10-02 20:30:00', '2026-10-02 21:10:00'),
(5, 2, 5, 'Vecinos de Ayllu Sur limpian ocho kilómetros del río Yaku Mayu', 'vecinos-de-ayllu-sur-limpian-ocho-kilometros-del-rio-yaku-mayu', 'Más de sesenta voluntarios retiraron bolsas de residuos y llantas de las orillas durante una jornada de limpieza.', 'Más de sesenta voluntarios, entre ellos escolares y comerciantes del barrio, recorrieron ocho kilómetros de las orillas del río Yaku Mayu para retirar residuos. Al final de la mañana se habían llenado cuarenta bolsas grandes y se rescataron once llantas viejas.

## Qué se encontró

- Plásticos y envases de un solo uso, que forman la mayor parte del volumen.
- Restos de construcción acumulados cerca del puente.
- Llantas y objetos metálicos que la municipalidad recogió con su camión.

Los vecinos proponen colocar tachos con tapa en tres puntos del recorrido y repetir la jornada cada dos meses. También pidieron que se señalice la prohibición de arrojar desmonte en la ribera.', 'demo_5.webp', 'Ilustración de cordones con nudos dorados sobre un fondo ocre oscuro', 1200, 630, 'publicada', 1, 74, '2026-09-28 11:45:00', '2026-09-27 18:00:00', '2026-09-28 11:45:00'),
(6, 2, 1, 'La posta de Tambo Alto amplía su atención hasta las ocho de la noche', 'la-posta-de-tambo-alto-amplia-su-atencion-hasta-las-ocho-de-la-noche', 'El establecimiento atenderá de lunes a sábado en horario extendido para quienes trabajan en el campo durante el día.', 'La posta de salud de Tambo Alto atenderá de lunes a sábado hasta las 8 de la noche, dos horas más que antes. El cambio responde a un pedido de los vecinos que no podían acudir por la mañana porque trabajan en el campo.

El personal informó que las consultas de la tarde se darán por orden de llegada, mientras que los controles de niños y gestantes se mantienen con cita previa. Para reservar, las personas pueden acudir al módulo de admisión o llamar al número que la posta publica en su puerta.

La nueva jornada comenzó esta semana y será evaluada dentro de tres meses para decidir si se mantiene.', 'demo_6.webp', 'Ilustración de cordones con nudos turquesa y durazno sobre un fondo azul marino', 1200, 630, 'publicada', 1, 88, '2026-09-24 07:50:00', '2026-09-23 15:10:00', '2026-09-24 07:50:00'),
(7, 2, 2, 'Cinco datos de tu recibo de luz que conviene revisar cada mes', 'cinco-datos-de-tu-recibo-de-luz-que-conviene-revisar-cada-mes', 'Un repaso sencillo de las líneas del recibo que más confusión generan, para detectar errores a tiempo.', 'Revisar el recibo de luz toma menos de cinco minutos y puede evitar cobros equivocados. Estos son los datos que conviene mirar con atención:

- Lectura anterior y lectura actual: la resta debe coincidir con el consumo en kilovatios hora (kWh) que aparece impreso.
- Días facturados: un periodo muy largo o muy corto cambia el monto sin que haya cambiado tu consumo.
- Cargo fijo: se cobra aunque no uses energía; compáralo con el de meses anteriores.
- Tarifa aplicada: verifica que corresponda al tipo de conexión que tienes.
- Fecha de vencimiento: pagar después puede sumar intereses.

## Si algo no cuadra

Guarda una foto del medidor el día de la lectura y presenta tu reclamo por escrito en la oficina de la empresa distribuidora. Pide siempre un número de registro.', NULL, NULL, NULL, NULL, 'publicada', 1, 211, '2026-09-20 09:00:00', '2026-09-19 19:45:00', '2026-09-20 09:00:00'),
(8, 2, 3, 'Taller de lectura en voz alta abre inscripciones para adultos mayores', 'taller-de-lectura-en-voz-alta-abre-inscripciones-para-adultos-mayores', 'La biblioteca municipal ofrece doce sesiones gratuitas con cuentos, leyendas del valle y ejercicios de memoria.', 'La biblioteca municipal de Villa Chaska abrió las inscripciones para un taller gratuito de lectura en voz alta dirigido a personas mayores de sesenta años. Habrá doce sesiones, una por semana, con cuentos breves, leyendas del valle y juegos para ejercitar la memoria.

Cada encuentro durará una hora y media y terminará con un espacio para conversar sobre lo leído. Los textos se entregarán en letra grande, y quienes lo necesiten podrán pedir ayuda para llegar a la sala, que está en el primer piso con rampa de acceso.

Las inscripciones se reciben en el mostrador de la biblioteca hasta completar veinte cupos.', NULL, NULL, NULL, NULL, 'publicada', 1, 39, '2026-09-15 10:30:00', '2026-09-14 14:00:00', '2026-09-15 10:30:00'),
(9, 2, 3, 'Crónica de la fiesta patronal: lo que se prepara en el barrio', 'cronica-de-la-fiesta-patronal-lo-que-se-prepara-en-el-barrio', 'Borrador de la crónica sobre los preparativos de la fiesta patronal, pendiente de revisar datos y fotografías.', 'Este borrador reúne las primeras notas sobre los preparativos de la fiesta patronal: la banda ya ensaya por las tardes y las familias empezaron a acomodar los arcos de flores. Falta confirmar las fechas con la hermandad y recoger testimonios de los vecinos.', NULL, NULL, NULL, NULL, 'borrador', 1, 0, NULL, '2026-10-11 18:20:00', '2026-10-11 18:20:00');

INSERT INTO comentarios (id, noticia_id, usuario_id, contenido, estado, creado_en) VALUES
(1, 1, 3, 'Qué buena iniciativa. ¿Se pueden llevar plantas aromáticas o solo semillas?', 'visible', '2026-10-10 12:05:00'),
(2, 1, 2, 'Sí, las plantas pequeñas en maceta están permitidas. Lo anotamos para la próxima edición.', 'visible', '2026-10-10 14:20:00'),
(3, 2, 3, 'Ojalá la municipalidad apoye con el transporte, es lo que más encarece todo.', 'visible', '2026-10-07 20:40:00'),
(4, 3, 3, 'Fui el domingo y los tintes naturales se veían espectaculares.', 'visible', '2026-10-05 18:15:00'),
(5, 4, 3, 'Gran final, se jugó hasta el último punto.', 'visible', '2026-10-03 09:00:00'),
(6, 5, 3, 'Compra seguidores baratos escribiendo a mi cuenta de mensajería.', 'oculto', '2026-09-29 03:12:00');
