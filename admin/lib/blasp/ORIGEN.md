# Blasp (copia del núcleo)

- Origen: https://github.com/Blaspsoft/blasp, tag `v4.0.1`, licencia MIT (`LICENSE.md`).
- Se copió: `src/Core`, `src/Drivers`, `src/Enums`, `config/blasp.php`, `config/languages/{spanish,english}.php`.
- No se copió lo que es de Laravel: service provider, facade, middleware, reglas, eventos, comandos.

## Cambios al código original
- `src/Core/Result.php`: guarda las palabras encontradas en un arreglo en vez de `Illuminate\Support\Collection`. `words()` devuelve `array`.
- `config/blasp.php`: se quitaron las llamadas a `env()` y se dejaron sus valores por defecto.

## Lo de Koaly (no es de Blasp)
- `arranque.php`: autoload y las funciones `config()` y `config_path()` dentro del espacio de nombres `Blaspsoft\Blasp\Core`, para no depender de Laravel.
- `koaly/languages/*.php`: envuelven las listas originales y agregan las groserías mexicanas que faltaban, la `v` como sustituto de la `u` y los nombres permitidos.
- `koaly/pegadas.php`: raíces que se buscan dentro de cada palabra ("tuputamadre", "Cajamierda"), porque Blasp ignora a propósito lo que va pegado a otra palabra. La búsqueda está en `tieneGroseriaPegada()` de `admin/profanity_filter.php`, que también corta el texto en cada mayúscula ("TuPutaMadre" → "Tu Puta Madre").
- `koaly/permitidas.php`: nombres reales que Blasp confundía (Concha, Culiacán, Analí). Solo se permiten escritos tal cual.

## Para actualizar
Descargar los mismos archivos del tag nuevo, volver a aplicar los dos cambios de arriba y correr las pruebas de nombres (bloquear "p3nd3j0", "p-e-n-d-e-j-o"; dejar pasar "Concha", "Culiacán").
