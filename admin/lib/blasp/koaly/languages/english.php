<?php
// Lista en inglés de Blasp + nombres permitidos.

$lista = require __DIR__ . '/../../config/languages/english.php';

$lista['false_positives'] = array_values(array_unique(array_merge(
    $lista['false_positives'] ?? [],
    require __DIR__ . '/../permitidas.php'
)));

return $lista;
