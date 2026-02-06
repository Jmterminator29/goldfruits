<?php
// contabilidad/controllers/config_controller.php

// 1. CONEXIÓN SEGURA (La solución al error visual)
// Usamos __DIR__ para que no dependa de $_SERVER['DOCUMENT_ROOT']
require_once __DIR__ . '/../config/db.php';

// Verificar conexión antes de seguir
if (!isset($conexion)) {
    die("Error Crítico: No se pudo conectar a la base de datos.");
}

// ==========================================================================
// ELIMINAR REGISTROS
// ==========================================================================
if (isset($_GET['eliminar']) && isset($_GET['tabla'])) {
    $id = intval($_GET['eliminar']);
    $tabla = mysqli_real_escape_string($conexion, $_GET['tabla']);
    
    // Lógica de redirección inteligente
    $redirect_view = 'config'; 
    if ($tabla == 'categorias_pago') $redirect_view = 'tarifas';

    // Mapeo seguro de columnas ID
    $col_id = '';
    if ($tabla == 'areas') $col_id = 'id_area';
    if ($tabla == 'puestos') $col_id = 'id_puesto';
    if ($tabla == 'bancos') $col_id = 'id_banco';
    if ($tabla == 'aseguradoras') $col_id = 'id_aseguradora';
    if ($tabla == 'tipos_documento') $col_id = 'id_tipo_doc';
    if ($tabla == 'categorias_pago') $col_id = 'id_categoria';

    if ($col_id != '') {
        $sql = "DELETE FROM $tabla WHERE $col_id = $id";
        if (!mysqli_query($conexion, $sql)) {
            die("Error al eliminar: " . mysqli_error($conexion));
        }
    }
    
    // Redirección Limpia
    header("Location: ../index.php?view=$redirect_view&status=deleted");
    exit();
}

// ==========================================================================
// CREAR REGISTROS
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tabla = mysqli_real_escape_string($conexion, $_POST['tabla']);
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre']);
    
    // Lógica de redirección inteligente
    $redirect_view = 'config';
    if ($tabla == 'categorias_pago') $redirect_view = 'tarifas';

    $sql = "";

    // 1. Categorías de Pago (Sueldos)
    if ($tabla == 'categorias_pago') {
        $monto = floatval($_POST['monto']);
        $sql = "INSERT INTO categorias_pago (nombre_categoria, monto_categoria) VALUES ('$nombre', '$monto')";
    } 
    // 2. Aseguradoras (Con porcentaje)
    else if ($tabla == 'aseguradoras') {
        $porcentaje = floatval($_POST['porcentaje']);
        $sql = "INSERT INTO aseguradoras (nombre_aseguradora, porcentaje_descuento) VALUES ('$nombre', '$porcentaje')";
    } 
    // 3. Tablas simples
    else {
        // Lista blanca de tablas permitidas por seguridad
        $tablas_validas = ['areas', 'puestos', 'bancos', 'tipos_documento'];
        
        if (in_array($tabla, $tablas_validas)) {
            if ($tabla == 'areas') $sql = "INSERT INTO areas (nombre_area) VALUES ('$nombre')";
            if ($tabla == 'puestos') $sql = "INSERT INTO puestos (nombre_puesto) VALUES ('$nombre')";
            if ($tabla == 'bancos') $sql = "INSERT INTO bancos (nombre_banco) VALUES ('$nombre')";
            if ($tabla == 'tipos_documento') $sql = "INSERT INTO tipos_documento (nombre_tipo) VALUES ('$nombre')";
        } else {
            die("Error: Tabla no válida o no permitida.");
        }
    }

    if ($sql != "" && mysqli_query($conexion, $sql)) {
        // Redirección exitosa
        header("Location: ../index.php?view=$redirect_view&status=success");
        exit(); // IMPORTANTE: Detener el script después de redirigir
    } else {
        echo "Error SQL: " . mysqli_error($conexion);
    }
}
?>