<?php
declare(strict_types=1);
require_once __DIR__.'/config/config.php';

$message = '';
$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $confirmation = trim($_POST['confirmacion'] ?? '');
    if ($confirmation !== 'REPARAR ADMIN') {
        $error = 'Escribe exactamente REPARAR ADMIN para confirmar.';
    } else {
        try {
            $pdo = db();
            $hash = password_hash('Cambiar123!', PASSWORD_DEFAULT);
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1 FOR UPDATE');
            $stmt->execute(['admin']);
            $row = $stmt->fetch();

            if ($row) {
                $update = $pdo->prepare("UPDATE usuarios SET password_hash = ?, rol = 'Administrador', activo = 1 WHERE id = ?");
                $update->execute([$hash, $row['id']]);
                $adminId = (int)$row['id'];
                $message = 'Se restableció la contraseña del usuario admin.';
            } else {
                $insert = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?, 'admin', ?, 'Administrador', 1)");
                $insert->execute(['Administrador principal', $hash]);
                $adminId = (int)$pdo->lastInsertId();
                $message = 'Se creó el usuario administrador admin.';
            }

            $audit = $pdo->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)');
            $audit->execute([$adminId, 'Reparación de acceso', 'Restablecimiento local del usuario administrador', $_SERVER['REMOTE_ADDR'] ?? null]);
            $pdo->commit();
            $done = true;
        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = 'No se pudo reparar el acceso. Comprueba que instalaste la base de datos primero. Detalle: '.$ex->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reparar acceso ERP</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="install-body"><main class="install-card">
<div class="brand-mark">ERP</div><h1>Reparar acceso administrador</h1>
<p class="muted">Esta herramienta solo modifica la cuenta admin; no elimina ventas, productos, clientes ni otras tablas.</p>
<?php if ($error): ?><div class="notice danger"><?=e($error)?></div><?php endif; ?>
<?php if ($done): ?>
<div class="notice success"><?=e($message)?></div>
<p><b>Usuario:</b> admin<br><b>Contraseña temporal:</b> Cambiar123!</p>
<p class="muted">Ahora abre el inicio de sesión. Después de comprobar el acceso, elimina este archivo (reparar_acceso.php) de la carpeta del proyecto y cambia la contraseña temporal cuando el módulo de cambio de contraseña esté disponible.</p>
<a class="btn primary" href="login.php">Ir al inicio de sesión</a>
<?php else: ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<label>Confirmación requerida<input name="confirmacion" placeholder="REPARAR ADMIN" required autocomplete="off"></label>
<p class="muted">Al confirmar, la contraseña de admin quedará como Cambiar123!.</p>
<button class="btn primary full" type="submit">Crear / reparar usuario admin</button>
</form>
<p class="muted small">Ejecuta esta herramienta solo desde tu computador local y elimínala después de usarla.</p>
<?php endif; ?>
</main></body></html>
