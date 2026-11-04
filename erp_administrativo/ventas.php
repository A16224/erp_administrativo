<?php
require_once __DIR__.'/config/config.php'; require_login();
$pageTitle='Historial de Ventas / Ingresos'; $active='ventas';
$desde=$_GET['desde']??date('Y-m-01'); $hasta=$_GET['hasta']??date('Y-m-d'); $estado=$_GET['estado']??''; $q=trim($_GET['q']??'');
$ventas=[]; $tot=['subtotal'=>0,'impuestos'=>0,'total'=>0,'abonado'=>0,'costo'=>0,'utilidad'=>0]; $error='';
try {
 $where=['DATE(v.fecha) BETWEEN ? AND ?']; $params=[$desde,$hasta];
 if(in_array($estado,['Pagada','Pendiente','Anulada'],true)){ $where[]='v.estado=?'; $params[]=$estado; }
 if($q!==''){ $where[]='(v.numero LIKE ? OR c.nombre LIKE ? OR c.documento LIKE ?)'; array_push($params,"%$q%","%$q%","%$q%");}
 $sqlWhere=implode(' AND ',$where);
 $stmt=db()->prepare("SELECT v.*,c.nombre cliente,u.nombre vendedor,COALESCE((SELECT SUM(d.cantidad*d.costo_unitario) FROM venta_detalles d WHERE d.venta_id=v.id),0) costo FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id JOIN usuarios u ON u.id=v.usuario_id WHERE $sqlWhere ORDER BY v.fecha DESC LIMIT 500");
 $stmt->execute($params); $ventas=$stmt->fetchAll();
 $sum=db()->prepare("SELECT COALESCE(SUM(v.subtotal),0) subtotal,COALESCE(SUM(v.impuestos),0) impuestos,COALESCE(SUM(v.total),0) total,COALESCE(SUM(v.abonado),0) abonado,COALESCE(SUM((SELECT SUM(d.cantidad*d.costo_unitario) FROM venta_detalles d WHERE d.venta_id=v.id)),0) costo FROM ventas v LEFT JOIN clientes c ON c.id=v.cliente_id WHERE $sqlWhere AND v.estado<>'Anulada'");
 $sum->execute($params); $tot=$sum->fetch(); $tot['utilidad']=$tot['subtotal']-$tot['costo'];
} catch(Throwable $ex) { $error='No se pudo consultar. Verifica que hayas ejecutado install.php.'; }
include __DIR__.'/includes/header.php';
?>
<?php if($error): ?><div class="notice warning"><?=e($error)?></div><?php endif; ?>
<section class="filter-panel"><form method="get" class="filter-form wide"><label>Sucursal<select disabled><option>Sucursal principal</option></select></label><label>Fecha inicial<input type="date" name="desde" value="<?=e($desde)?>" required></label><label>Fecha final<input type="date" name="hasta" value="<?=e($hasta)?>" required></label><label>Filtro estado<select name="estado"><option value="">Todos</option><?php foreach(['Pagada','Pendiente','Anulada'] as $s): ?><option <?=$estado===$s?'selected':''?>><?=e($s)?></option><?php endforeach; ?></select></label><label>Buscar<input name="q" value="<?=e($q)?>" placeholder="Factura o cliente"></label><button class="btn primary" type="submit">⌕ Consultar transacciones</button></form></section>
<section class="stats-grid compact-stats">
<?php foreach([['TOTAL SIN IMPUESTOS',$tot['subtotal'],'▣'],['TOTAL IMPUESTOS',$tot['impuestos'],'▦'],['TOTAL FACTURADO',$tot['total'],'▤'],['TOTAL CARTERA',max(0,$tot['total']-$tot['abonado']),'↶'],['TOTAL PAGADO',$tot['abonado'],'$'],['UTILIDAD BRUTA',$tot['utilidad'],'↗']] as $i=>$s): ?><div class="stat-card <?=$i===5?'stat-highlight':''?>"><div class="stat-icon"><?=e($s[2])?></div><div><div class="stat-label"><?=e($s[0])?></div><div class="stat-value"><?=money($s[1])?></div></div></div><?php endforeach; ?>
</section>
<section class="table-panel"><div class="table-toolbar"><div class="toolbar-left"><span class="toolbar-title">Transacciones</span><span class="muted small"><?=count($ventas)?> registros (máximo 500)</span></div><div class="toolbar-right"><button class="btn light" onclick="window.print()">▣ Imprimir informe</button><a class="btn light" href="venta_nueva.php">＋ Nueva venta</a></div></div>
<div class="table-scroll"><table><thead><tr><th>#</th><th>Tipo</th><th>Medio</th><th># Factura</th><th>Fecha</th><th>Estado</th><th>Vendedor</th><th>Cliente</th><th class="numeric">T. facturado</th><th class="numeric">T. costo</th><th class="numeric">Utilidad</th><th class="numeric">T. abonado</th><th class="numeric">T. pendiente</th></tr></thead><tbody>
<?php if(!$ventas): ?><tr><td colspan="13" class="empty">No hay transacciones para los filtros seleccionados.</td></tr><?php else: foreach($ventas as $v): ?><tr><td><?=e($v['id'])?></td><td>Ventas</td><td><?=e($v['medio_pago'])?></td><td><b><?=e($v['numero'])?></b></td><td><?=e(date('d/m/Y H:i',strtotime($v['fecha'])))?></td><td><span class="status <?=strtolower($v['estado'])?>"><?=e($v['estado'])?></span></td><td><?=e($v['vendedor'])?></td><td><?=e($v['cliente']?:'Consumidor final')?></td><td class="numeric"><?=money($v['total'])?></td><td class="numeric"><?=money($v['costo'])?></td><td class="numeric"><?=money($v['subtotal']-$v['costo'])?></td><td class="numeric"><?=money($v['abonado'])?></td><td class="numeric"><?=money(max(0,$v['total']-$v['abonado']))?></td></tr><?php endforeach; endif; ?>
</tbody><tfoot><tr><th colspan="8">TOTALES DEL PERÍODO (sin anuladas en tarjetas)</th><th class="numeric"><?=money($tot['total'])?></th><th class="numeric"><?=money($tot['costo'])?></th><th class="numeric"><?=money($tot['utilidad'])?></th><th class="numeric"><?=money($tot['abonado'])?></th><th class="numeric"><?=money(max(0,$tot['total']-$tot['abonado']))?></th></tr></tfoot></table></div></section>
<?php include __DIR__.'/includes/footer.php'; ?>
