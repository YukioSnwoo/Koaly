<?php
session_start();
$_SESSION = [];
// Borrar también la cookie: sin esto el navegador seguía enviando el id de sesión
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: index.php');
exit;
