<?php
require_once __DIR__.'/config/config.php'; require_login();
$pageTitle='Productos e inventario'; $active='productos'; $error=''; $success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 try{
  if(($_POST['action']??'')==='stock'){
   $id=(int)$_POST['producto_id'];$qty=(float)$_POST['cantidad'];$type=$_POST['tipo']??'Entrada';$reason=trim($_POST['motivo']??'Ajuste manual');
   if($qty<=0||!in_array($type,['Entrada','Salida'],true))throw new RuntimeException('Revisa el tipo y la cantidad.');
   $pdo=db();$pdo->beginTransaction();$st=$pdo->prepare('SELECT existencia FROM productos WHERE id=? FOR UPDATE');$st->execute([$id]);$p=$st->fetch();if(!$p)throw new RuntimeException('Producto no encontrado.');
   if($type==='Salida'&&(float)$p['existencia']<$qty)throw new RuntimeException('No hay suficiente existencia.');
   $delta=$type==='Entrada'?$qty:-$qty;$pdo->prepare('UPDATE productos SET existencia=existencia+? WHERE id=?')->execute([$delta,$id]);$pdo->prepare('INSERT INTO movimientos_inventario(producto_id,usuario_id,tipo,cantidad,motivo) VALUES(?,?,?,?,?)')->execute([$id,$_SESSION['user_id'],$type,$qty,$reason]);$pdo->commit();$success='Movimiento de inventario guardado.';
  }else{
   $codigo=trim($_POST['codigo']??'');$nombre=trim($_POST['nombre']??'');$categoria=trim($_POST['categoria']??'');$costo=(float)($_POST['costo']??0);$precio=(float)($_POST['precio']??0);$stock=(float)($_POST['existencia']??0);
   if($codigo===''||$nombre===''||$costo<0||$precio<0||$stock<0)throw new RuntimeException('Completa los campos y usa valores no negativos.');
   db()->prepare('INSERT INTO productos(codigo,nombre,categoria,costo,precio,existencia) VALUES(?,?,?,?,?,?)')->execute([$codigo,$nombre,$categoria,$costo,$precio,$stock]);$success='Producto creado correctamente.';
  }
 }catch(Throwable $ex){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error=$ex instanceof RuntimeException?$ex->getMessage():'No se pudo guardar. Comprueba que el código no esté repetido.';}
}
$productos=[];try{$productos=db()->query('SELECT * FROM productos ORDER BY nombre')->fetchAll();}catch(Throwable $ex){}
include __DIR__.'/includes/header.php';
?>
<?php if($error): ?><div class="notice danger"><?=e($error)?></div><?php endif; if($success): ?><div class="notice success"><?=e($success)?></div><?php endif; ?>
<div class="two-panels"><section class="form-panel"><h2>Nuevo producto</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><div class="form-grid"><label>Código / referencia<input name="codigo" required></label><label>Nombre del producto<input name="nombre" required></label><label>Categoría<input name="categoria"></label><label>Costo unitario<input type="number" name="costo" min="0" step=".01" value="0" required></label><label>Precio de venta<input type="number" name="precio" min="0" step=".01" value="0" required></label><label>Existencia inicial<input type="number" name="existencia" min="0" step=".001" value="0" required></label></div><button class="btn primary" type="submit">Guardar producto</button></form></section>
<section class="form-panel"><h2>Movimiento de inventario</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="stock"><label>Producto<select name="producto_id" required><?php foreach($productos as $p): ?><option value="<?=$p['id']?>"><?=e($p['codigo'].' · '.$p['nombre'].' (stock '.$p['existencia'].')')?></option><?php endforeach; ?></select></label><div class="form-grid"><label>Tipo<select name="tipo"><option>Entrada</option><option>Salida</option></select></label><label>Cantidad<input type="number" name="cantidad" min=".001" step=".001" required></label></div><label>Motivo<input name="motivo" value="Movimiento manual" required></label><button class="btn secondary" type="submit">Registrar movimiento</button></form></section></div>
<section class="table-panel"><div class="table-toolbar"><b>Catálogo de productos</b><input class="quick-search" placeholder="Buscar producto..." data-table-search="products-table"></div><div class="table-scroll"><table id="products-table"><thead><tr><th>Código</th><th>Producto</th><th>Categoría</th><th class="numeric">Costo</th><th class="numeric">Precio</th><th class="numeric">Existencia</th><th>Estado</th></tr></thead><tbody><?php foreach($productos as $p): ?><tr><td><?=e($p['codigo'])?></td><td><b><?=e($p['nombre'])?></b></td><td><?=e($p['categoria'])?></td><td class="numeric"><?=money($p['costo'])?></td><td class="numeric"><?=money($p['precio'])?></td><td class="numeric"><?=e($p['existencia'])?></td><td><span class="status <?=$p['existencia']<=0?'pendiente':'pagada'?>"><?=$p['existencia']<=0?'Sin stock':($p['existencia']<5?'Stock bajo':'Disponible')?></span></td></tr><?php endforeach; if(!$productos): ?><tr><td colspan="7" class="empty">Aún no hay productos.</td></tr><?php endif; ?></tbody></table></div></section>
<?php include __DIR__.'/includes/footer.php'; ?>
