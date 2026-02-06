<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/contabilidad/config/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Eliminamos (Los hijos se borran solos si configuraste ON DELETE CASCADE en SQL, 
    // si no, borramos manualmente por seguridad)
    mysqli_query($conexion, "DELETE FROM hijos_detalles WHERE id_trabajador = $id");
    
    $sql = "DELETE FROM trabajadores WHERE id_trabajador = $id";
    
    if (mysqli_query($conexion, $sql)) {
        header("Location: ../index.php?view=lista&status=deleted");
    } else {
        echo "Error al eliminar: " . mysqli_error($conexion);
    }
}
?>