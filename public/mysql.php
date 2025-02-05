<?php
$mysqli = new mysqli($_ENV['MYSQL_SERVER'], $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD'], $_ENV['MYSQL_DB']);
$mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, TRUE);
$mysqli->set_charset('utf8mb4');

define('MYSQL_BOOKS_TABLE', $_ENV['MYSQL_BOOKS_TABLE'] ?? 'books');
?>
