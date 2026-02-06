<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// ELIMINAR
if (isset($_GET['eliminar']) && isset($_GET['tabla'])) {
    $id = intval($_GET['eliminar']);
    $tabla = mysqli_real_escape_string($conexion, $_GET['tabla']);
    
    // Lógica de redirección inteligente
    $redirect_view = 'config'; 
    if ($tabla == 'categorias_pago') $redirect_view = 'tarifas';

    $col_id = '';
    if ($tabla == 'areas') $col_id = 'id_area';
    if ($tabla == 'puestos') $col_id = 'id_puesto';
    if ($tabla == 'bancos') $col_id = 'id_banco';
    if ($tabla == 'aseguradoras') $col_id = 'id_aseguradora';
    if ($tabla == 'tipos_documento') $col_id = 'id_tipo_doc';
    if ($tabla == 'categorias_pago') $col_id = 'id_categoria';

    if ($col_id != '') {
        mysqli_query($conexion, "DELETE FROM $tabla WHERE $col_id = $id");
    }
    header("Location: ../index.php?view=$redirect_view&status=deleted");
    exit();
}

// CREAR
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tabla = $_POST['tabla'];
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre']);
    
    // Lógica de redirección inteligente
    $redirect_view = 'config';
    if ($tabla == 'categorias_pago') $redirect_view = 'tarifas';

    $sql = "";

    // 1. Lógica para Categorías de Pago (Sueldos)
    if ($tabla == 'categorias_pago') {
        $monto = floatval($_POST['monto']);
        $sql = "INSERT INTO categorias_pago (nombre_categoria, monto_categoria) VALUES ('$nombre', '$monto')";
    } 
    // 2. Lógica específica para ASEGURADORAS (Porcentaje)
    else if ($tabla == 'aseguradoras') {
        $porcentaje = floatval($_POST['porcentaje']); // Recibimos el porcentaje del formulario
        $sql = "INSERT INTO aseguradoras (nombre_aseguradora, porcentaje_descuento) VALUES ('$nombre', '$porcentaje')";
    } 
    // 3. Tablas simples de un solo campo
    else {
        if ($tabla == 'areas') $sql = "INSERT INTO areas (nombre_area) VALUES ('$nombre')";
        if ($tabla == 'puestos') $sql = "INSERT INTO puestos (nombre_puesto) VALUES ('$nombre')";
        if ($tabla == 'bancos') $sql = "INSERT INTO bancos (nombre_banco) VALUES ('$nombre')";
        if ($tabla == 'tipos_documento') $sql = "INSERT INTO tipos_documento (nombre_tipo) VALUES ('$nombre')";
    }

    if ($sql != "" && mysqli_query($conexion, $sql)) {
        header("Location: ../index.php?view=$redirect_view&status=success");
    } else {
        echo "Error SQL: " . mysqli_error($conexion);
    }
}
?>