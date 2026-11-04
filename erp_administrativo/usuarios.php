<?php
declare(strict_types=1);
require_once __DIR__.'/config/config.php';
require_login();
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    exit('Acceso denegado. Solo el administrador puede gestionar usuarios.');
}
$pageTitle = 'Usuarios y accesos';
$active = 'usuarios';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    try {
        $pdo = db();
        if ($action === 'create') {
            $nombre = trim($_POST['nombre'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $rol = $_POST['rol'] ?? 'Consulta';
            if ($nombre === '' || $usuario === '' || $password === '') throw new RuntimeException('Completa nombre, usuario y contraseña.');
            if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $usuario)) throw new RuntimeException('El usuario debe tener entre 3 y 40 caracteres y solo puede incluir letras, números, punto, guion y guion bajo.');
            if (strlen($password) < 10) throw new RuntimeException('La contraseña debe tener al menos 10 caracteres.');
            if (!in_array($rol, ['Administrador', 'Vendedor', 'Consulta'], true)) throw new RuntimeException('Selecciona un rol válido.');
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1');
            $stmt->execute([$usuario]);
            if ($stmt->fetch()) throw new RuntimeException('Ese nombre de usuario ya existe. Elige otro.');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$nombre, $usuario, $hash, $rol]);
            $pdo->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)')->execute([
                $_SESSION['user_id'], 'Creación de usuario', 'Se creó la cuenta '.$usuario.' con rol '.$rol, $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            $success = 'Usuario creado correctamente. Ya puede iniciar sesión.';
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$_SESSION['user_id']) throw new RuntimeException('No puedes desactivar tu propia cuenta mientras tienes la sesión abierta.');
            $stmt = $pdo->prepare('SELECT id, usuario, rol, activo FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if (!$target) throw new RuntimeException('No se encontró el usuario seleccionado.');
            if ((int)$target['activo'] === 1) {
                $activeAdmins = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='Administrador' AND activo=1")->fetchColumn();
                if ($target['rol'] === 'Administrador' && $activeAdmins <= 1) throw new RuntimeException('No puedes desactivar al último administrador activo.');
                $newStatus = 0;
            } else { $newStatus = 1; }
            $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$newStatus, $id]);
            $pdo->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)')->execute([
                $_SESSION['user_id'], 'Cambio de estado de usuario', 'Cuenta '.$target['usuario'].' '.($newStatus ? 'activada' : 'desactivada'), $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            $success = $newStatus ? 'Cuenta activada.' : 'Cuenta desactivada.';
        } elseif ($action === 'reset_password') {
            $id = (int)($_POST['id'] ?? 0);
            $password = (string)($_POST['new_password'] ?? '');
            if (strlen($password) < 10) throw new RuntimeException('La nueva contraseña debe tener al menos 10 caracteres.');
            $stmt = $pdo->prepare('SELECT id, usuario FROM usuarios WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if (!$target) throw new RuntimeException('No se encontró el usuario seleccionado.');
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE usuarios SET password_hash=? WHERE id=?')->execute([$hash, $id]);
            $pdo->prepare('INSERT INTO auditoria (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)')->execute([
                $_SESSION['user_id'], 'Restablecimiento de contraseña', 'Se restableció la contraseña de '.$target['usuario'], $_SERVER['REMOTE_ADDR'] ?? null
            ]);
            $success = 'Contraseña actualizada para '.$target['usuario'].'.';
        } else { throw new RuntimeException('Acción no reconocida.'); }
    } catch (Throwable $ex) {
        if ($ex instanceof RuntimeException) $error = $ex->getMessage();
        elseif ($ex instanceof PDOException && strpos($ex->getMessage(), 'Duplicate') !== false) $error = 'Ese nombre de usuario ya existe. Elige otro.';
        else $error = 'No se pudo completar la operación. Verifica la conexión con MySQL.';
    }
}
$users = [];
try { $users = db()->query('SELECT id, nombre, usuario, rol, activo, creado_en FROM usuarios ORDER BY creado_en DESC, id DESC')->fetchAll(); }
catch (Throwable $ex) { $error = $error ?: 'No se pudo cargar la lista de usuarios.'; }
include __DIR__.'/includes/header.php';
?>
<?php if ($error): ?><div class="notice danger"><?=e($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="notice success"><?=e($success)?></div><?php endif; ?>
<section class="form-panel">
  <h2>Crear cuenta de empleado</h2>
  <p class="muted small">Solo administradores pueden crear cuentas. Las contraseñas se guardan cifradas con el hash seguro de PHP.</p>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
    <input type="hidden" name="action" value="create">
    <div class="form-grid">
      <label>Nombre completo<input name="nombre" maxlength="120" required></label>
      <label>Nombre de usuario<input name="usuario" minlength="3" maxlength="40" pattern="[A-Za-z0-9._-]+" required autocomplete="off"><span class="muted small">Letras, números, punto, guion o guion bajo.</span></label>
      <label>Contraseña temporal<input type="password" name="password" minlength="10" required autocomplete="new-password"><span class="muted small">Mínimo 10 caracteres.</span></label>
      <label>Rol<select name="rol" required>
        <option value="Vendedor">Vendedor</option><option value="Consulta">Consulta</option><option value="Administrador">Administrador</option>
      </select></label>
    </div>
    <button class="btn primary" type="submit">＋ Crear usuario</button>
  </form>
</section>
<section class="table-panel">
  <div class="table-toolbar"><div class="toolbar-left"><b class="toolbar-title">Cuentas registradas</b><span class="muted small"><?=count($users)?> usuarios</span></div><div class="toolbar-right"><input class="quick-search" placeholder="Buscar usuario..." data-table-search="users-table"></div></div>
  <div class="table-scroll"><table id="users-table">
    <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Fecha de creación</th><th>Acciones</th></tr></thead><tbody>
    <?php if (!$users): ?><tr><td colspan="6" class="empty">No hay usuarios registrados.</td></tr>
    <?php else: foreach ($users as $u): ?><tr>
      <td><b><?=e($u['nombre'])?></b></td><td><?=e($u['usuario'])?></td><td><?=e($u['rol'])?></td>
      <td><span class="status <?=$u['activo'] ? 'pagada' : 'anulada'?>"><?=$u['activo'] ? 'Activo' : 'Inactivo'?></span></td>
      <td><?=e(date('d/m/Y H:i', strtotime($u['creado_en'])))?></td><td><div class="toolbar-left">
      <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
      <form method="post" class="inline-form" onsubmit="return confirm('¿Seguro que deseas <?=$u['activo'] ? 'desactivar' : 'activar'?> esta cuenta?');">
        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=(int)$u['id']?>">
        <button class="btn light" type="submit"><?=$u['activo'] ? 'Desactivar' : 'Activar'?></button>
      </form><?php endif; ?>
      <details class="password-reset"><summary class="btn light">Cambiar contraseña</summary><form method="post" class="reset-form" onsubmit="return confirm('¿Cambiar la contraseña de esta cuenta?');">
        <input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?=(int)$u['id']?>">
        <label>Nueva contraseña<input type="password" name="new_password" minlength="10" required autocomplete="new-password"></label><button class="btn primary" type="submit">Guardar contraseña</button>
      </form></details></div></td>
    </tr><?php endforeach; endif; ?>
    </tbody></table></div>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>
