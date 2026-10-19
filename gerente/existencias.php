<?php
// Existencias y catálogo se unificaron en productos.php. Se conserva la URL
// para no romper marcadores ni enlaces (p. ej. existencias.php?stock=alerta).
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: productos.php' . ($query !== '' ? '?' . $query : ''), true, 301);
exit;
