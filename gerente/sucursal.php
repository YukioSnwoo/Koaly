<?php
require_once __DIR__ . '/guardia.php';

$stmt = $pdo->prepare("
    SELECT id_sucursal, nombre, direccion, telefono, email_contacto,
           estado, fecha_inicio_estado, fecha_fin_estado, creado_en
    FROM Sucursales
    WHERE id_sucursal = ?
    LIMIT 1
");
$stmt->execute([$idSucursalGerente]);
$sucursal = $stmt->fetch();

$estadoBadge = [
    'Activa'            => 'badge-activo',
    'Mantenimiento'     => 'badge-warning',
    'Cierre_Temporal'   => 'badge-warning',
    'Cierre_Definitivo' => 'badge-danger',
];
$claseBadge  = $estadoBadge[$sucursal['estado'] ?? ''] ?? 'badge-inactivo';
$estadoTexto = str_replace('_', ' ', $sucursal['estado'] ?? 'Desconocido');

$pageTitle    = 'Mi Sucursal';
$showBackLink = true;
require __DIR__ . '/header.php';
?>

<div class="page-header">
    <div>
        <h1>Información de mi sucursal</h1>
        <p>Datos de la sucursal a la que estás asignado</p>
    </div>
</div>

<?php if (!$sucursal): ?>
    <div class="panel">
        <div class="empty-state">
            No se encontró información de tu sucursal. Contacta al administrador.
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="info-grid">

            <div class="info-field info-field--full">
                <span class="info-label">Nombre</span>
                <span class="info-value info-value--strong">
                    <?= htmlspecialchars($sucursal['nombre']) ?>
                </span>
            </div>

            <div class="info-field">
                <span class="info-label">Estado</span>
                <span>
                    <span class="badge <?= $claseBadge ?>">
                        <?= htmlspecialchars($estadoTexto) ?>
                    </span>
                </span>
            </div>

            <div class="info-field">
                <span class="info-label">Gerente responsable</span>
                <span class="info-value">
                    <?= htmlspecialchars($gerente['nombre']) ?>
                </span>
            </div>

            <div class="info-field info-field--full">
                <span class="info-label">Dirección</span>
                <span class="info-value <?= empty($sucursal['direccion']) ? 'info-value--muted' : '' ?>">
                    <?= htmlspecialchars($sucursal['direccion'] ?: 'No registrada') ?>
                </span>
            </div>

            <div class="info-field">
                <span class="info-label">Teléfono</span>
                <span class="info-value <?= empty($sucursal['telefono']) ? 'info-value--muted' : '' ?>">
                    <?= htmlspecialchars($sucursal['telefono'] ?: 'No registrado') ?>
                </span>
            </div>

            <div class="info-field">
                <span class="info-label">Email de contacto</span>
                <span class="info-value <?= empty($sucursal['email_contacto']) ? 'info-value--muted' : '' ?>">
                    <?php if (!empty($sucursal['email_contacto'])): ?>
                        <a href="mailto:<?= htmlspecialchars($sucursal['email_contacto']) ?>" style="color:#7c3aed; text-decoration:none;">
                            <?= htmlspecialchars($sucursal['email_contacto']) ?>
                        </a>
                    <?php else: ?>
                        No registrado
                    <?php endif; ?>
                </span>
            </div>

            <div class="info-field">
                <span class="info-label">Fecha de alta</span>
                <span class="info-value">
                    <?= htmlspecialchars(date('d/m/Y', strtotime($sucursal['creado_en']))) ?>
                </span>
            </div>

        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/footer.php'; ?>