<?php
// Raíces que se buscan DENTRO de cada palabra, para groserías pegadas a otra
// ("tuputamadre", "Cajamierda"), que Blasp deja pasar a propósito.
// Solo raíces que no aparecen en nombres o palabras normales:
// no van "puta" (diputado, disputa), "verga" (Vergara), "marica" (Maricarmen),
// "culo" (vehículo), "cunt" (Scunthorpe).
// En minúsculas, sin acentos y sin letras repetidas.

return [
    'pendej', 'chinga', 'chingon', 'putamadre', 'hijodeputa', 'hijueputa',
    'putisima', 'putita', 'mierd', 'cabron', 'culero', 'culera', 'maricon',
    'pinche', 'panocha', 'mamada', 'mamon', 'ojete', 'vergota', 'vergaz',
    'nalga', 'cojones', 'malparid', 'gonorea', 'fuck', 'bitch',
];
