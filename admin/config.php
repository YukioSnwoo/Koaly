<?php require 'header.php'; ?>
<?php require 'sidebar.php'; ?>

<?php
// Guardar configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['idioma'])) {
        $_SESSION['idioma'] = $_POST['idioma'];
    }
    header('Location: config.php');
    exit;
}

// Último acceso
$ultimoAcceso = $pdo->prepare("SELECT MAX(fecha_hora) AS ultima FROM Ventas WHERE id_cajero = ?");
$ultimoAcceso->execute([$admin['id_usuario']]);
$acceso = $ultimoAcceso->fetch();
?>

<div class="page-header">
    <div>
        <h1><?= htmlspecialchars($t['configuracion']) ?></h1>
        <p>Ajustes generales del panel</p>
    </div>
</div>

<div style="margin-top:1.25rem;max-width:480px">
    <form method="POST">
        <div class="stat-card" style="margin-bottom:12px">
            <p class="stat-card__label">Idioma del panel</p>
            <select name="idioma" class="select" style="margin-top:6px;width:100%" onchange="this.form.submit()">
                <option value="es" <?= $idioma === 'es' ? 'selected' : '' ?>>Español</option>
                <option value="en" <?= $idioma === 'en' ? 'selected' : '' ?>>English</option>
            </select>
        </div>
    </form>

    <div class="stat-card" style="margin-bottom:12px">
        <p class="stat-card__label">Zona horaria</p>
        <select class="select" style="margin-top:6px;width:100%">
            <option>America/Mexico_City (UTC-6)</option>
            <option>America/New_York (UTC-5)</option>
        </select>
    </div>

    <div class="stat-card">
        <p class="stat-card__label">Sesión activa</p>
        <p style="font-size:13px;color:var(--text-secondary);margin-top:6px">
            <?= htmlspecialchars($admin['nombre']) ?> · <?= htmlspecialchars($admin['email']) ?>
        </p>
    </div>
</div>

<?php require 'footer.php'; ?>
