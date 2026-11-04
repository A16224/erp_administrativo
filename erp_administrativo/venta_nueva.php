<?php
require_once __DIR__.'/config/config.php'; require_login();
$pageTitle='Registrar venta'; $active='ventas'; $error=''; $clientes=[]; $productos=[];
try {$clientes=db()->query('SELECT id,nombre,documento FROM clientes ORDER BY nombre')->fetchAll();$productos=db()->query('SELECT id,codigo,nombre,precio,costo,existencia FROM productos WHERE activo=1 AND existencia>0 ORDER BY nombre')->fetchAll();} catch(Throwable $ex){}
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 try {
  $pdo=db(); $items=$_POST['producto']??[]; $cantidades=$_POST['cantidad']??[];
  if(!is_array($items)||!count($items)) throw new RuntimeException('Agrega al menos un producto.');
  $pdo->beginTransaction(); $subtotal=0; $costoTotal=0; $lineas=[];
  foreach($items as $i=>$pid){
   $qty=(float)($cantidades[$i]??0); if($qty<=0) continue;
   $st=$pdo->prepare('SELECT * FROM productos WHERE id=? AND activo=1 FOR UPDATE'); $st->execute([(int)$pid]); $p=$st->fetch();
   if(!$p) throw new RuntimeException('Uno de los productos no existe.');
   if((float)$p['existencia']<$qty) throw new RuntimeException('Existencia insuficiente para '.$p['nombre'].'.');
   $line=$qty*(float)$p['precio']; $subtotal+=$line; $costoTotal+=$qty*(float)$p['costo'];
   $lineas[]=['p'=>$p,'q'=>$qty,'line'=>$line];
  }
  if(!$lineas) throw new RuntimeException('Las cantidades deben ser mayores que cero.');
  $taxRate=max(0,min(100,(float)($_POST['impuesto']??0))); $tax=round($subtotal*$taxRate/100,2); $total=$subtotal+$tax;
  $paid=max(0,min($total,(float)($_POST['abonado']??$total))); $state=$paid >= $total ? 'Pagada':'Pendiente';
  $numero='VEN-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
  $client=($_POST['cliente_id']??'')!==''?(int)$_POST['cliente_id']:null;
  $st=$pdo->prepare('INSERT INTO ventas(numero,cliente_id,usuario_id,medio_pago,estado,subtotal,impuestos,total,abonado,observaciones) VALUES(?,?,?,?,?,?,?,?,?,?)');
  $st->execute([$numero,$client,$_SESSION['user_id'],$_POST['medio_pago']??'Efectivo',$state,$subtotal,$tax,$total,$paid,trim($_POST['observaciones']??'')]);
  $saleId=(int)$pdo->lastInsertId();
  $detail=$pdo->prepare('INSERT INTO venta_detalles(venta_id,producto_id,descripcion,cantidad,costo_unitario,precio_unitario,impuesto_porcentaje,total_linea) VALUES(?,?,?,?,?,?,?,?)');
  $stock=$pdo->prepare('UPDATE productos SET existencia=existencia-? WHERE id=?');
  $move=$pdo->prepare("INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,cantidad,motivo) VALUES(?,?,'Salida',?,?)");
  foreach($lineas as $ln){$detail->execute([$saleId,$ln['p']['id'],$ln['p']['nombre'],$ln['q'],$ln['p']['costo'],$ln['p']['precio'],$taxRate,$ln['line']]);$stock->execute([$ln['q'],$ln['p']['id']]);$move->execute([$ln['p']['id'],$_SESSION['user_id'],$ln['q'],'Venta '.$numero]);}
  $pdo->prepare('INSERT INTO auditoria(usuario_id,accion,detalle,ip) VALUES(?,?,?,?)')->execute([$_SESSION['user_id'],'Registro de venta',$numero,$_SERVER['REMOTE_ADDR']??null]);
  $pdo->commit(); redirect('ventas.php?desde='.date('Y-m-01').'&hasta='.date('Y-m-d'));
 } catch(Throwable $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error=$ex instanceof RuntimeException?$ex->getMessage():'No se pudo guardar la venta. Verifica los datos.';}
}
include __DIR__.'/includes/header.php';
?>
<?php if($error): ?><div class="notice danger"><?=e($error)?></div><?php endif; ?>
<form method="post" class="form-panel" id="sale-form"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>">
<div class="form-grid"><label>Cliente<select name="cliente_id"><option value="">Consumidor final</option><?php foreach($clientes as $c): ?><option value="<?=$c['id']?>"><?=e($c['nombre'].' '.($c['documento']?'· '.$c['documento']:''))?></option><?php endforeach; ?></select></label><label>Medio de pago<select name="medio_pago"><option>Efectivo</option><option>Transferencia</option><option>Tarjeta</option><option>Crédito</option><option>Otro</option></select></label><label>Impuesto (%)<input type="number" name="impuesto" min="0" max="100" step=".01" value="0"></label><label>Abono inicial<input type="number" name="abonado" min="0" step=".01" value="0"></label></div>
<div class="table-toolbar"><b>Productos de la venta</b><button type="button" class="btn light" id="add-line">＋ Agregar producto</button></div>
<div class="table-scroll"><table><thead><tr><th>Producto</th><th>Existencia</th><th>Cantidad</th><th>Precio unitario</th><th>Subtotal</th><th></th></tr></thead><tbody id="sale-lines"></tbody></table></div>
<label class="block-label">Observaciones<textarea name="observaciones" rows="2"></textarea></label>
<div class="sale-total">Subtotal estimado: <strong id="subtotal-preview">$ 0,00</strong></div>
<div class="form-actions"><a class="btn light" href="ventas.php">Cancelar</a><button class="btn primary" type="submit">Guardar venta y descontar inventario</button></div>
</form>
<script>window.ERP_PRODUCTS=<?=json_encode(array_map(fn($p)=>['id'=>(int)$p['id'],'name'=>$p['codigo'].' · '.$p['nombre'],'price'=>(float)$p['precio'],'stock'=>(float)$p['existencia']],$productos),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>;</script>
<?php include __DIR__.'/includes/footer.php'; ?>
