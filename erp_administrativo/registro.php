<?php
declare(strict_types=1);
require_once __DIR__.'/config/config.php';

if (!empty($_SESSION['user_id'])) redirect('index.php');
$error = '';
$registrationClosed = false;

try {
    $count = (int)db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    $registrationClosed = $count > 0;
} catch (Throwable $ex) {
    $error = 'No se puede conectar con la base de datos. Ejecuta primero install.php.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$registrationClosed && $error === '') {
    check_csrf();
    $nombre = trim($_POST['nombre'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($nombre === '' || $usuario === '' || $password === '') {
        $error = 'Completa todos los campos obligatorios.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $usuario)) {
        $error = 'El usuario debe tener entre 3 y 40 caracteres y usar solo letras, números, punto, guion o guion bajo.';
    } elseif (strlen($password) < 10) {
        $error = 'La contraseña debe tener al menos 10 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            // Recheck inside transaction to avoid opening public registration after first account exists.
            $count = (int)$pdo->query('SELECT COUNT(*) FROM usuarios FOR UPDATE')->fetchColumn();
            if ($count > 0) {
                $pdo->rollBack();
                $registrationClosed = true;
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?, ?, ?, 'Administrador', 1)");
                $stmt->execute([$nombre, $usuario, $hash]);
                $userId = (int)$pdo->lastInsertId();
                $audit = $pdo->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)');
                $audit->execute([$userId, 'Registro inicial', 'Creación del primer administrador de esta instalación', $_SERVER['REMOTE_ADDR'] ?? null]);
                $pdo->commit();

                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['nombre'] = $nombre;
                $_SESSION['rol'] = 'Administrador';
                redirect('index.php');
            }
        } catch (Throwable $ex) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = 'No se pudo crear la cuenta. Verifica que el usuario no exista y que MySQL esté funcionando.';
        }
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Crear administrador · ERP Administrativo</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="login-body"><main class="login-card">
<div class="brand-mark">ERP</div><h1>Crear cuenta de administrador</h1>
<?php if ($registrationClosed): ?>
<div class="notice warning">Esta instalación ya tiene una cuenta registrada. Por seguridad, el registro público está cerrado. Inicia sesión o pide al administrador que cree tu cuenta desde “Usuarios y accesos”.</div>
<a class="btn primary full" href="login.php">Ir a iniciar sesión</a>
<?php else: ?>
<p class="muted">Crea la primera cuenta de administrador para esta instalación del ERP.</p>
<?php if ($error): ?><div class="notice danger"><?=e($error)?></div><?php endif; ?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<label>Nombre completo<input name="nombre" maxlength="120" required value="<?=e($_POST['nombre'] ?? '')?>"></label>
<label>Usuario<input name="usuario" minlength="3" maxlength="40" pattern="[A-Za-z0-9._-]+" required autocomplete="username" value="<?=e($_POST['usuario'] ?? '')?>"></label>
<label>Contraseña<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
<label>Confirmar contraseña<input type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></label>
<button class="btn primary full" type="submit">Crear administrador y entrar</button></form>
<?php endif; ?>
<p class="muted small">Cada empresa debe tener su propia instalación y base de datos si se usa esta versión sin arquitectura multiempresa.</p>
</main></body></html>
