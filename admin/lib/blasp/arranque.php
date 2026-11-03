<?php
// arranque.php — Carga el núcleo de Blasp (src/) sin Laravel.
// Blasp solo pide dos funciones de Laravel: config() y config_path(). Aquí
// se definen dentro de su propio espacio de nombres, así no chocan con nada
// global del proyecto. Las listas de Koaly (koaly/languages/*.php) envuelven
// las originales de config/languages/ y les agregan lo nuestro.
// Ver ORIGEN.md para la versión copiada y los cambios hechos al código.

namespace Blaspsoft\Blasp\Core;

spl_autoload_register(function (string $clase): void {
    $prefijo = 'Blaspsoft\\Blasp\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $ruta = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});

// Dictionary busca primero aquí las listas de cada idioma.
function config_path(string $ruta = ''): string
{
    return __DIR__ . '/koaly/' . $ruta;
}

// config('blasp.separators') → $config['separators'] de config/blasp.php
function config(string $clave, mixed $porDefecto = null): mixed
{
    static $config = null;
    $config ??= ['blasp' => require __DIR__ . '/config/blasp.php'];

    $valor = $config;
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $porDefecto;
        }
        $valor = $valor[$parte];
    }
    return $valor;
}
