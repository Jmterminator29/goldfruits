<?php
// 1. SEGURIDAD
require_once '../includes/auth_admin.php';
require_once '../includes/db_connect.php';

// 2. VALIDAR PARÁMETROS
if (isset($_GET['id']) && isset($_GET['accion'])) {
    
    $id = intval($_GET['id']);
    $accion = $_GET['accion'];
    
    // Determinar el nuevo estado
    $nuevo_estado = '';
    if ($accion === 'cerrar') {
        $nuevo_estado = 'terminado';
    } elseif ($accion === 'abrir') {
        $nuevo_estado = 'abierto';
    }

    // 3. ACTUALIZAR EN BASE DE DATOS
    if ($nuevo_estado !== '') {
        try {
            $sql = "UPDATE acopios_cabecera SET estado = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nuevo_estado, $id]);
        } catch (PDOException $e) {
            // Si hay error, podrías manejarlo aquí, pero por ahora solo redirigimos
        }
    }
}

// 4. REDIRECCIÓN CORRECTA
// Aquí estaba el error. Ahora redirige al LISTADO DE ACOPIOS, no al panel general.
header("Location: admin_panel_acopios.php");
exit();
?>