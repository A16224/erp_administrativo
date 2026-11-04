<?php
require_once __DIR__.'/config/config.php'; require_login();
$pageTitle='Panel principal'; $active='dashboard';
$stats = ['ventas'=>0,'impuestos'=>0,'pagado'=>0,'cartera'=>0,'ganancia'=>0,'gastos'=>0];
try {
 $d=db();
 $row=$d->query("SELECT COALESCE(SUM(CASE WHEN estado<>'Anulada' THEN subtotal ELSE 0 END),0) subtotal, COALESCE(SUM(CASE WHEN estado<>'Anulada' THEN impuestos ELSE 0 END),0) impuestos, COALESCE(SUM(CASE WHEN estado<>'Anulada' THEN total ELSE 0 END),0) total, COALESCE(SUM(CASE WHEN estado<>'Anulada' THEN abonado ELSE 0 END),0) abonado FROM ventas WHERE fecha >= CURDATE() AND fecha < CURDATE()+INTERVAL 1 DAY")->fetch();
 $stats['ventas']=$row['subtotal']; $stats['impuestos']=$row['impuestos']; $stats['pagado']=$row['abonado']; $stats['cartera']=max(0,$row['total']-$row['abonado']);
 $stats['ganancia']=(float)$d->query("SELECT COALESCE(SUM(vd.total_linea - vd.cantidad*vd.costo_unitario),0) FROM venta_detalles vd JOIN ventas v ON v.id=vd.venta_id WHERE v.estado<>'Anulada' AND v.fecha >= CURDATE() AND v.fecha < CURDATE()+INTERVAL 1 DAY")->fetchColumn();
 $stats['gastos']=(float)$d->query("SELECT COALESCE(SUM(valor),0) FROM gastos WHERE fecha=CURDATE()")->fetchColumn();
 $recent=$d->query("SELECT v.*, c.nombre cliente, u.nombre vendedor FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id JOIN usuarios u ON u.id=v.usuario_id ORDER BY v.fecha DESC LIMIT 8")->fetchAll();
} catch(Throwable $ex) { $recent=[]; $dbError='No se ha instalado la base de datos. Abre primero install.php.'; }
include __DIR__.'/includes/header.php';
?>
<?php if(isset($dbError)): ?><div class="notice warning"><?=e($dbError)?> <a href="install.php">Instalar ahora</a></div><?php endif; ?>
<section class="filter-panel"><div class="section-caption">Resumen de ventas</div><form method="get" action="ventas.php" class="filter-form"><label>Fecha inicial<input type="date" name="desde" value="<?=date('Y-m-01')?>"></label><label>Fecha final<input type="date" name="hasta" value="<?=date('Y-m-d')?>"></label><button class="btn primary" type="submit">⌕ Consultar transacciones</button></form></section>
<section class="stats-grid">
<div class="stat-card"><div class="stat-icon">▣</div><div><div class="stat-label">TOTAL SIN IMPUESTOS · HOY</div><div class="stat-value"><?=money($stats['ventas'])?></div><div class="stat-foot">Ventas no anuladas</div></div></div>
<div class="stat-card"><div class="stat-icon">▦</div><div><div class="stat-label">TOTAL IMPUESTOS · HOY</div><div class="stat-value"><?=money($stats['impuestos'])?></div><div class="stat-foot">Impuestos registrados</div></div></div>
<div class="stat-card"><div class="stat-icon">▤</div><div><div class="stat-label">TOTAL FACTURADO · HOY</div><div class="stat-value"><?=money($stats['ventas']+$stats['impuestos'])?></div><div class="stat-foot">Valor de ventas</div></div></div>
<div class="stat-card"><div class="stat-icon">↶</div><div><div class="stat-label">TOTAL CARTERA · HOY</div><div class="stat-value"><?=money($stats['cartera'])?></div><div class="stat-foot">Saldo pendiente calculado</div></div></div>
<div class="stat-card"><div class="stat-icon">$</div><div><div class="stat-label">TOTAL ABONADO · HOY</div><div class="stat-value"><?=money($stats['pagado'])?></div><div class="stat-foot">Pagos registrados</div></div></div>
<div class="stat-card stat-highlight"><div class="stat-icon">↗</div><div><div class="stat-label">UTILIDAD BRUTA · HOY</div><div class="stat-value"><?=money($stats['ganancia'])?></div><div class="stat-foot">Ventas menos costo de productos</div></div></div>
<div class="stat-card"><div class="stat-icon">▧</div><div><div class="stat-label">GASTOS · HOY</div><div class="stat-value"><?=money($stats['gastos'])?></div><div class="stat-foot">Gastos registrados</div></div></div>
<div class="stat-card"><div class="stat-icon">=</div><div><div class="stat-label">UTILIDAD MENOS GASTOS</div><div class="stat-value"><?=money($stats['ganancia']-$stats['gastos'])?></div><div class="stat-foot">Estimación operativa</div></div></div>
</section>
<section class="table-panel"><div class="table-toolbar"><div class="toolbar-left"><a class="btn light" href="ventas.php">▤ Historial de ventas</a><a class="btn light" href="productos.php">＋ Productos</a></div><div class="toolbar-right"><input class="quick-search" placeholder="Buscar en la tabla..." data-table-search="recent-table"></div></div>
<div class="table-scroll"><table id="recent-table"><thead><tr><th>N.º factura</th><th>Fecha</th><th>Estado</th><th>Medio</th><th>Vendedor</th><th>Cliente</th><th class="numeric">Facturado</th><th class="numeric">Abonado</th><th class="numeric">Pendiente</th></tr></thead><tbody>
<?php if(!$recent): ?><tr><td colspan="9" class="empty">Todavía no hay ventas registradas.</td></tr><?php else: foreach($recent as $v): ?><tr><td><b><?=e($v['numero'])?></b></td><td><?=e(date('d/m/Y H:i',strtotime($v['fecha'])))?></td><td><span class="status <?=strtolower($v['estado'])?>"><?=e($v['estado'])?></span></td><td><?=e($v['medio_pago'])?></td><td><?=e($v['vendedor'])?></td><td><?=e($v['cliente'] ?: 'Consumidor final')?></td><td class="numeric"><?=money($v['total'])?></td><td class="numeric"><?=money($v['abonado'])?></td><td class="numeric"><?=money(max(0,$v['total']-$v['abonado']))?></td></tr><?php endforeach; endif; ?>
</tbody></table></div></section>
<?php include __DIR__.'/includes/footer.php'; ?>
