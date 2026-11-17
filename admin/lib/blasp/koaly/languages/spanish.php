<?php
// Lista en español de Blasp + lo que le falta en México + nombres permitidos.

$lista = require __DIR__ . '/../../config/languages/spanish.php';

// Los diminutivos y aumentativos ("putito", "nalgona") no hace falta
// escribirlos: los busca esDerivadaDeGroseria() con koaly/derivables.php.
// Aquí no van palabras que también son nombres, apellidos, lugares o palabras
// normales: "naco" (Naco, Sonora), "chaqueta", "paja", "pepa" (Josefa),
// "lilo", "jota", "wey", "ano" (año).
$mexicanas = [
    'chinga', 'chingas', 'chingadera', 'chingaderas', 'chingon', 'chingona',
    'chingados', 'chingadas', 'chingues', 'chinguen', 'chinguense',
    'joto', 'jotos', 'panocha', 'pucha', 'puñal', 'mamadas', 'mamar',
    'mamona', 'mamones', 'mamahuevo', 'mamahuevos', 'mamaguevo', 'mamavergas',
    'chupavergas', 'chupapitos', 'chupapijas',
    'putas', 'putos', 'putona', 'putero', 'putañero', 'putañera', 'putiza',
    'prostituta', 'prostituto',
    'vergas', 'vergazo', 'vergudo', 'verguda', 'verguero',
    'culeros', 'culera', 'culeras', 'culear', 'culeado', 'culiado',
    'pendejos', 'pendejas', 'pendejada', 'pendejadas', 'pendejear',
    'ojetes', 'cabrones', 'cagon', 'cagona', 'cagada', 'cagadas', 'cagar',
    'nalgas', 'nalgon', 'nalgona', 'pito', 'pitos', 'pinga', 'pingas',
    'pene', 'penes', 'teta', 'tetona', 'tetuda', 'chichis', 'chichona',
    'huevona', 'huevones', 'guevon', 'guevona', 'webon', 'webona', 'webones',
    'pirujo', 'piruja', 'pirujas', 'puñeta', 'puñetas', 'puñetero', 'puñetera',
    'coger', 'cogiendo', 'cogelon', 'cogelona', 'mecos',
    'mayate', 'mayates', 'maricas', 'zorras', 'perras',
    'cachondo', 'cachonda', 'mamacita', 'mamasita',
    'pajero', 'pajera', 'pajeros', 'porno',
    'menso', 'mensa', 'mensos', 'tarado', 'tarada',
];

$lista['profanities'] = array_values(array_unique(array_merge($lista['profanities'], $mexicanas)));
$lista['false_positives'] = array_values(array_unique(array_merge(
    $lista['false_positives'] ?? [],
    require __DIR__ . '/../permitidas.php'
)));

// "pvto": la v hace de u
$lista['substitutions']['/u/'] = array_merge($lista['substitutions']['/u/'] ?? [], ['v']);

return $lista;
