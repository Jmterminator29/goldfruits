<?php
require_once 'auth_admin.php';
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_panel.php");
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Validación
if (empty($_POST['liq'])) {
    die("<div style='font-family:sans-serif; padding:20px; color:#b71c1c; background:#ffcdd2; border:1px solid #e57373; border-radius:8px; max-width:600px; margin:50px auto;'>
            <h2>⚠️ Error: Datos no recibidos</h2>
            <p>No se encontraron datos para guardar.</p>
            <a href='admin_panel.php' style='text-decoration:none; background:#d32f2f; color:white; padding:10px 15px; border-radius:5px;'>Volver</a>
         </div>");
}

$liquidaciones = $_POST['liq'];
$gastos_ops = isset($_POST['gastos_operativos']) ? (float)$_POST['gastos_operativos'] : 0;

try {
    $conn->beginTransaction();

    // 1. ELIMINAR LIQUIDACIONES PREVIAS (Limpieza)
    $stmtDel = $conn->prepare("DELETE FROM acopios_liquidaciones WHERE acopio_id = ?");
    $stmtDel->execute([$id]);

    // 2. INSERTAR DETALLE EN LA TABLA NUEVA (Guardamos la info real de pago)
    $sqlInsert = "INSERT INTO acopios_liquidaciones 
                  (acopio_id, origen_id, kg_campo_neto, importe_campo, porc_merma, precio_merma, kg_merma, kg_util, pago_merma, pago_util, pago_total) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtIns = $conn->prepare($sqlInsert);

    foreach ($liquidaciones as $origen_id => $liq) {
        // Datos base (Originales)
        $kg_neto_original = (float)$liq['kg_campo_neto'];
        $importe_original = (float)$liq['importe_campo'];
        
        // Ajustes (Lo que editaste)
        $pct_merma = (float)$liq['porc_merma'];
        $precio_merma = (float)$liq['precio_merma'];

        // Recálculo seguro en servidor
        $kg_merma = $kg_neto_original * ($pct_merma / 100);
        $kg_util  = $kg_neto_original - $kg_merma;

        $precio_promedio_orig = ($kg_neto_original > 0) ? ($importe_original / $kg_neto_original) : 0;

        $pago_por_merma = $kg_merma * $precio_merma;
        $pago_por_util  = $kg_util * $precio_promedio_orig;
        $pago_total_linea = $pago_por_merma + $pago_por_util;

        // Guardamos todo el detalle
        $stmtIns->execute([
            $id,
            $origen_id,
            $kg_neto_original,
            $importe_original,
            $pct_merma,
            $precio_merma,
            $kg_merma,
            $kg_util,
            $pago_por_merma,
            $pago_por_util,
            $pago_total_linea
        ]);
    }

    // 3. ACTUALIZAR ESTADO Y GASTOS (SIN TOCAR KILOS NI IMPORTE ORIGINALES)
    // La cabecera mantiene la "foto" de lo que llegó del campo.
    $sqlCab = "UPDATE acopios_cabecera SET 
               gastos_operativos = ?,
               estado = 'terminado' 
               WHERE id = ?";
               
    $stmtCab = $conn->prepare($sqlCab);
    $stmtCab->execute([$gastos_ops, $id]);

    $conn->commit();
    
    header("Location: admin_ver.php?id=" . $id . "&status=saved");
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    die("<h1 style='color:red'>Error Crítico:</h1> " . $e->getMessage());
}
?>