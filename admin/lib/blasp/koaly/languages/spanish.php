<?php
// Lista en español de Blasp + lo que le falta en México + nombres permitidos.

$lista = require __DIR__ . '/../../config/languages/spanish.php';

$mexicanas = [
    'chinga', 'chingas', 'chingadera', 'chingaderas', 'chingon', 'chingona',
    'joto', 'jotos', 'panocha', 'pucha', 'puñal', 'mamadas', 'mamar',
    'putas', 'putos', 'vergas', 'vergazo', 'culeros', 'pendejos', 'pendejas',
    'pendejada', 'ojetes', 'cabrones', 'cagon', 'nalgas', 'pito', 'pitos',
];

$lista['profanities'] = array_values(array_unique(array_merge($lista['profanities'], $mexicanas)));
$lista['false_positives'] = array_values(array_unique(array_merge(
    $lista['false_positives'] ?? [],
    require __DIR__ . '/../permitidas.php'
)));

// "pvto": la v hace de u
$lista['substitutions']['/u/'] = array_merge($lista['substitutions']['/u/'] ?? [], ['v']);

return $lista;
