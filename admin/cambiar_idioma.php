<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['idioma'])) {
    $_SESSION['idioma'] = $_POST['idioma'];
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'panel.php'));
exit;
