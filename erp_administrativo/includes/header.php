<?php
require_once __DIR__.'/../config/config.php';
require_login();
$pageTitle = $pageTitle ?? 'Panel';
$active = $active ?? 'dashboard';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($pageTitle)?> · <?=e(APP_NAME)?></title><link rel="stylesheet" href="assets/app.css"></head><body>
<div class="app-shell">
<aside class="sidebar" id="sidebar"><div class="side-brand"><span class="brand-mark small-mark">ERP</span><span class="brand-name">Administrativo</span></div>
<nav class="side-nav">
<a class="<?=$active==='dashboard'?'active':''?>" href="index.php"><span>▦</span> Panel principal</a>
<a class="<?=$active==='ventas'?'active':''?>" href="ventas.php"><span>▣</span> Ventas / Ingresos</a>
<a class="<?=$active==='productos'?'active':''?>" href="productos.php"><span>▤</span> Productos e inventario</a>
<a class="<?=$active==='clientes'?'active':''?>" href="clientes.php"><span>♙</span> Clientes</a>
<a class="<?=$active==='gastos'?'active':''?>" href="gastos.php"><span>▧</span> Gastos</a>
<a class="<?=$active==='informes'?'active':''?>" href="informes.php"><span>▥</span> Informes</a>
<?php if (($_SESSION['rol'] ?? '') === 'Administrador'): ?><a class="<?=$active==='usuarios'?'active':''?>" href="usuarios.php"><span>♙</span> Usuarios y accesos</a><?php endif; ?>
</nav><div class="side-bottom"><a href="logout.php"><span>⇥</span> Cerrar sesión</a></div></aside>
<div class="main-area"><header class="topbar"><button class="icon-btn mobile-menu" onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Abrir menú">☰</button><div class="breadcrumbs"><b>Venta</b><span>⌄</span><span>Informes de Ventas</span><span>⌄</span><span>Informes de CxC</span></div><div class="top-actions"><span class="connection"><i></i> Sistema activo</span><span class="user-chip">♙ <?=e($_SESSION['nombre'] ?? 'Usuario')?> <small><?=e($_SESSION['rol'] ?? '')?></small></span></div></header>
<main class="content"><div class="page-heading"><div><div class="eyebrow">GESTIÓN EMPRESARIAL</div><h1><?=e($pageTitle)?></h1></div><div class="page-date"><?=date('d/m/Y')?></div></div>
