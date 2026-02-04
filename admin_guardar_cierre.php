<?php
require_once 'auth_admin.php';
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Validar ID
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) die("Error: ID de acopio inválido.");

    // Recibir datos del formulario
    $liquidaciones = $_POST['liq'] ?? []; // Array con el detalle por proveedor
    $gastos_ops = isset($_POST['gastos_operativos']) ? (float)$_POST['gastos_operativos'] : 0;
    
    // Totales Globales (Calculados en el Frontend y enviados para actualizar cabecera)
    $total_kilos_netos_global = isset($_POST['total_kilos_neto']) ? (float)$_POST['total_kilos_neto'] : 0;
    $importe_total_global = isset($_POST['importe_total_fruta']) ? (float)$_POST['importe_total_fruta'] : 0;

    try {
        // Iniciar Transacción (Todo o nada)
        $conn->beginTransaction();

        // ---------------------------------------------------------
        // PASO 1: LIMPIAR Y GUARDAR DETALLE EN NUEVA TABLA
        // ---------------------------------------------------------
        
        // Primero borramos liquidaciones previas de este acopio para evitar duplicados al re-guardar
        $stmtDel = $conn->prepare("DELETE FROM acopios_liquidaciones WHERE acopio_id = ?");
        $stmtDel->execute([$id]);

        // Preparamos la inserción masiva
        $sqlInsert = "INSERT INTO acopios_liquidaciones 
                      (acopio_id, origen_id, kg_campo_neto, importe_campo, porc_merma, precio_merma, kg_merma, kg_util, pago_merma, pago_util, pago_total) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtIns = $conn->prepare($sqlInsert);

        foreach ($liquidaciones as $origen_id => $liq) {
            // Asegurar tipos de datos
            $kg_campo     = (float)($liq['kg_campo_neto'] ?? 0);
            $imp_campo    = (float)($liq['importe_campo'] ?? 0);
            $pct_merma    = (float)($liq['porc_merma'] ?? 0);
            $prc_merma    = (float)($liq['precio_merma'] ?? 0);
            $kg_merma     = (float)($liq['kg_merma'] ?? 0);
            $kg_util      = (float)($liq['kg_util'] ?? 0);
            $pago_merma   = (float)($liq['pago_merma'] ?? 0);
            $pago_util    = (float)($liq['pago_util'] ?? 0);
            $pago_total   = (float)($liq['pago_total'] ?? 0);

            $stmtIns->execute([
                $id,
                $origen_id,
                $kg_campo,
                $imp_campo,
                $pct_merma,
                $prc_merma,
                $kg_merma,
                $kg_util,
                $pago_merma,
                $pago_util,
                $pago_total
            ]);
        }

        // ---------------------------------------------------------
        // PASO 2: ACTUALIZAR CABECERA (RESUMEN GLOBAL)
        // ---------------------------------------------------------
        // Actualizamos los totales en la cabecera para que coincidan con la suma de las liquidaciones
        // Y guardamos los gastos administrativos adicionales.
        $sqlCab = "UPDATE acopios_cabecera SET 
                   total_kilos_neto = ?, 
                   importe_total_fruta = ?, 
                   gastos_operativos = ? 
                   WHERE id = ?";
                   
        $stmtCab = $conn->prepare($sqlCab);
        $stmtCab->execute([
            $total_kilos_netos_global, 
            $importe_total_global, 
            $gastos_ops, 
            $id
        ]);

        // Confirmar cambios
        $conn->commit();

        // Redirigir con éxito
        header("Location: admin_ver.php?id=" . $id . "&status=saved");
        exit;

    } catch (Exception $e) {
        // Si algo falla, revertir todo
        $conn->rollBack();
        die("Error crítico al guardar la liquidación: " . $e->getMessage());
    }

} else {
    // Si intentan entrar directo sin POST
    header("Location: admin_panel.php");
    exit;
}
?>