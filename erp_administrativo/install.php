<?php
declare(strict_types=1);
require_once __DIR__.'/config/config.php';

$ok = false;
$error = '';
$createdAdmin = false;

try {
    // Connect without selecting a database first, so the installer can create it if needed.
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `".str_replace('`', '', DB_NAME)."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `".str_replace('`', '', DB_NAME)."`");

    // Execute schema statement-by-statement; do not drop or truncate existing tables.
    $schema = file_get_contents(__DIR__.'/database.sql');
    if ($schema === false) {
        throw new RuntimeException('No se pudo leer database.sql.');
    }
    $schema = preg_replace('/^\s*CREATE DATABASE IF NOT EXISTS.*?;\s*/ims', '', $schema);
    $schema = preg_replace('/^\s*USE\s+[^;]+;\s*/im', '', $schema);
    $schema = preg_replace('/^\s*--.*$/m', '', $schema);
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    // Create initial admin only if it does not exist. Never reset an existing password silently.
    $check = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1');
    $check->execute(['admin']);
    if (!$check->fetch()) {
        $hash = password_hash('Cambiar123!', PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?, ?, ?, 'Administrador', 1)");
        $insert->execute(['Administrador principal', 'admin', $hash]);
        $createdAdmin = true;
    }

    $ok = true;
} catch (Throwable $ex) {
    $error = $ex->getMessage();
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalación ERP Administrativo</title><link rel="stylesheet" href="assets/app.css">
</head>
<body class="install-body">
<main class="install-card">
<div class="brand-mark">ERP</div><h1>Instalación y reparación</h1>
<?php if ($ok): ?>
  <div class="notice success">La base de datos y las tablas están listas. No se borraron los registros existentes.</div>
  <?php if ($createdAdmin): ?>
    <p>Se creó el usuario administrador inicial:</p>
    <p><b>Usuario:</b> admin<br><b>Contraseña inicial:</b> Cambiar123!</p>
  <?php else: ?>
    <p>El usuario <b>admin</b> ya existe. Por seguridad, no se cambió su contraseña.</p>
    <p>Si no puedes entrar, usa <b>reparar_acceso.php</b> una sola vez para restablecer la contraseña de forma controlada.</p>
  <?php endif; ?>
  <p class="muted">Por seguridad, elimina install.php después de verificar el acceso.</p>
  <a class="btn primary" href="login.php">Ir al inicio de sesión</a>
<?php else: ?>
  <div class="notice danger">No fue posible instalar o verificar el sistema.</div>
  <pre><?=e($error)?></pre>
  <p>Verifica que MySQL esté encendido en XAMPP y las credenciales en config/config.php.</p>
<?php endif; ?>
</main></body></html>
