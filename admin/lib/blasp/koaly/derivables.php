<?php
// Groserías a las que también se les buscan diminutivos, aumentativos y
// plurales ("putito", "penesito", "nalgona", "vergazo", "pendejotes").
// La búsqueda está en esDerivadaDeGroseria() de admin/profanity_filter.php.
//
// Es una lista corta a propósito: con la lista completa de Blasp, "Peñón"
// daría "pene", "Gordon" daría "gordo", "Chuchito" daría "chucha" y
// "Maricita" daría "marica". Antes de agregar una palabra, revisar que sus
// derivados no sean nombres, apellidos o lugares.
// En minúsculas, sin acentos (la ñ como n).

return [
    'puto', 'puta', 'pene', 'culo', 'pito', 'joto', 'verga', 'pinga',
    'pendejo', 'pendeja', 'cabron', 'cabrona', 'chingon', 'chingona',
    'mamon', 'mamona', 'mamada', 'culero', 'culera', 'ojete', 'panocha',
    'nalga', 'teta', 'huevon', 'huevona', 'guevon', 'webon', 'pinche',
    'zorra', 'maricon', 'mierda', 'cagon', 'cagona', 'mayate', 'piruja',
    'pirujo', 'puneta',
];
