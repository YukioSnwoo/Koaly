<?php
/**
 * Notificaciones del menú de perfil.
 *   GET   lista de los últimos 30 días: {ok, nuevas, items}
 *   POST  (con csrf_token) las marca como vistas: {ok}
 */
require_once __DIR__ . '/api.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        perfilError(403, 'csrf');
    }
    try {
        $pdo->prepare("UPDATE Usuarios SET notificaciones_vistas_en = NOW() WHERE id_usuario = ?")
            ->execute([$usuario['id_usuario']]);
    } catch (PDOException $e) {
        perfilError(500, 'sin_migracion');
    }
    perfilResponder(200, ['ok' => true]);
}

$items = perfilNotificaciones($pdo, $usuario);
perfilResponder(200, [
    'ok'     => true,
    'nuevas' => count(array_filter($items, fn($n) => $n['nueva'])),
    'items'  => $items,
]);
