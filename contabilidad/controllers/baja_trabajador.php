<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/contabilidad/config/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $fecha_salida = date('Y-m-d'); // Fecha de hoy
    
    // Actualizamos estado y fecha de salida
    $sql = "UPDATE trabajadores SET estado = 'INACTIVO', fecha_salida = '$fecha_salida' WHERE id_trabajador = $id";
    
    if (mysqli_query($conexion, $sql)) {
        header("Location: ../index.php?view=lista&status=baja");
    } else {
        echo "Error al dar de baja: " . mysqli_error($conexion);
    }
}
?>