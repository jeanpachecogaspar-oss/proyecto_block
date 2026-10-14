<?php
/**
 * Crea una base SQLite de desarrollo con el esquema y los datos de demostración,
 * para probar el sitio SIN instalar MySQL.
 *
 * Uso:   php database/instalar_sqlite.php [ruta/archivo.sqlite]
 * Luego: en .env pon  DB_DRIVER=sqlite  y  DB_SQLITE_PATH=database/quipu_dev.sqlite
 *        y arranca    php -S localhost:8000 -t public
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('Ejecuta este script desde la línea de comandos.');
}

$raiz = dirname(__DIR__);
$archivo = $argv[1] ?? $raiz . '/database/quipu_dev.sqlite';

$sqlMysql = file_get_contents($raiz . '/database/quipu_noticias.sql');
$marca = '-- ==== DATOS DE DEMOSTRACIÓN ====';
$posicion = $sqlMysql === false ? false : strpos($sqlMysql, $marca);
if ($posicion === false) {
    exit("No se encontró la sección de datos de demostración en quipu_noticias.sql\n");
}
$datos = substr($sqlMysql, $posicion + strlen($marca));
$esquema = (string) file_get_contents($raiz . '/database/esquema_sqlite.sql');

if (is_file($archivo)) {
    unlink($archivo);
}
$pdo = new PDO('sqlite:' . $archivo);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec($esquema);
$pdo->exec($datos);

printf("Base SQLite creada en %s con %d noticias.\n", $archivo, (int) $pdo->query('SELECT COUNT(*) FROM noticias')->fetchColumn());
