<?php
require_once 'auth_admin.php'; // Solo Admins
require_once 'db_connect.php';

$id = $_GET['id'];

// Consultar datos (Sin restricción de usuario_id porque el admin ve todo)
$stmt = $conn->prepare("SELECT * FROM acopios_cabecera WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) die("No encontrado");

// Pesadas
$stmtDet = $conn->prepare("SELECT * FROM acopios_pesadas WHERE acopio_id = ?");
$stmtDet->execute([$id]);
$pesadas = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Revisión Admin</title>
    <style>
        body { font-family: sans-serif; background: #eee; padding-bottom: 30px; }
        .container { padding: 15px; max-width: 600px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; }
        h3 { margin-top: 0; color: #0d47a1; border-bottom: 1px solid #eee; padding-bottom: 5px; font-size: 1rem; }
        .row { display: flex; gap: 10px; margin-bottom: 10px; }
        .col { flex: 1; }
        label { font-size: 0.75rem; color: #666; display: block; }
        input { width: 100%; border: 1px solid #ccc; padding: 8px; border-radius: 4px; background: #f9f9f9; color: #333; font-weight: bold; }
        .pesada-item { display: flex; align-items: center; border-bottom: 1px solid #eee; padding: 5px 0; }
        .thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px; border: 1px solid #ccc; }
        .app-bar { background: #0d47a1; color: white; padding: 15px; display: flex; align-items: center; justify-content: space-between; }
        a { color: white; text-decoration: none; }
    </style>
</head>
<body>

<div class="app-bar">
    <b>REVISIÓN: <?php echo $data['codigo_unico']; ?></b>
    <a href="admin_panel.php">✕ Cerrar</a>
</div>

<div class="container">
    
    <div class="card">
        <h3>Datos Generales</h3>
        <label>Grupo / Nombre comercial</label><input value="<?php echo htmlspecialchars(($data['proveedor_comercial'] ?? '') ?: $data['proveedor']); ?>" disabled>
        <div class="row" style="margin-top:10px">
            <div class="col"><label>Conductor</label><input value="<?php echo $data['conductor']; ?>" disabled></div>
            <div class="col"><label>Placa</label><input value="<?php echo $data['placa']; ?>" disabled></div>
        </div>
        <label>Flete</label><input value="S/ <?php echo $data['precio_flete']; ?>" disabled>
    </div>

    <div class="card">
        <h3>Pesaje y Fotos</h3>
        <?php foreach($pesadas as $i => $p): ?>
            <div class="pesada-item">
                <a href="<?php echo $p['foto_url']; ?>" target="_blank">
                    <img src="<?php echo $p['foto_url']; ?>" class="thumb">
                </a>
                <div>
                    <div>Tanda #<?php echo $i+1; ?></div>
                    <small><?php echo $p['jabas']; ?> jabas | <b><?php echo $p['peso']; ?> kg</b></small>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="row" style="margin-top:10px;">
            <div class="col"><label>Total Jabas</label><input value="<?php echo $data['total_jabas']; ?>" disabled></div>
            <div class="col"><label>Total Peso</label><input value="<?php echo $data['total_peso_bruto']; ?>" disabled></div>
        </div>
    </div>

    <div class="card">
        <h3>Liquidación</h3>
        <div class="row">
            <div class="col"><label>Neto (Kg)</label><input value="<?php echo $data['total_kilos_neto']; ?>" disabled></div>
            <div class="col"><label>Precio x Kg</label><input value="<?php echo $data['precio_x_kg']; ?>" disabled></div>
        </div>
        <label>Total a Pagar</label>
        <input value="S/ <?php echo $data['importe_total_fruta']; ?>" disabled style="background:#e8f5e9; color:green; font-size:1.2rem; text-align:right;">
    </div>

    <div class="card">
        <h3>Costos Operativos</h3>
        <div class="row"><div class="col"><label>Cosecha</label><input value="S/ <?php echo $data['subtotal_cosecha']; ?>" disabled></div>
        <div class="col"><label>Cargadores</label><input value="S/ <?php echo $data['subtotal_cargadores']; ?>" disabled></div></div>
        <div class="row"><div class="col"><label>Inspectores</label><input value="S/ <?php echo $data['subtotal_inspectores']; ?>" disabled></div>
        <div class="col"><label>Otros</label><input value="S/ <?php echo $data['gastos_operativos']; ?>" disabled></div></div>
    </div>

</div>
</body>
</html>