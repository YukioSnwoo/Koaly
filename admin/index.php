<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] !== 1) {
    header('Location: ../index.php');
    exit;
}
header('Location: panel.php');
exit;
