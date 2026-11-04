<?php
require_once __DIR__.'/config/config.php';
if (!empty($_SESSION['user_id'])) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $stmt = db()->prepare('SELECT id,nombre,usuario,password_hash,rol FROM usuarios WHERE usuario=? AND activo=1 LIMIT 1');
        $stmt->execute([trim($_POST['usuario'] ?? '')]);
        $user = $stmt->fetch();
        if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['rol'] = $user['rol'];
            db()->prepare('INSERT INTO auditoria (usuario_id,accion,detalle,ip) VALUES (?,?,?,?)')
                ->execute([$user['id'],'Inicio de sesión','Acceso correcto',$_SERVER['REMOTE_ADDR'] ?? null]);
            redirect('index.php');
        }
        $error = 'Usuario o contraseña incorrectos.';
    } catch (Throwable $ex) {
        $error = 'No se pudo conectar a la base de datos. Ejecuta primero install.php.';
    }
}
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ingresar · ERP Administrativo</title><link rel="stylesheet" href="assets/app.css"></head>
<body class="login-body"><main class="login-card"><div class="brand-mark">ERP</div><h1>ERP Administrativo</h1><p class="muted">Ingresa con tus credenciales para continuar.</p>
<?php if($error): ?><div class="notice danger"><?=e($error)?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><label>Usuario</label><input name="usuario" autocomplete="username" required autofocus><label>Contraseña</label><input type="password" name="password" autocomplete="current-password" required><button class="btn primary full" type="submit">Iniciar sesión</button></form>
<p class="muted small"><a href="registro.php"><b>Crear la primera cuenta de administrador</b></a></p>
<p class="muted small">Después de instalar: usuario <b>admin</b>, contraseña inicial <b>Cambiar123!</b></p></main></body></html>
