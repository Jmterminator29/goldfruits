<?php
require_once 'auth_admin.php';
require_once 'db_connect.php';

if (isset($_GET['id']) && isset($_GET['accion'])) {
    $id = $_GET['id'];
    $nuevo_estado = ($_GET['accion'] == 'cerrar') ? 'terminado' : 'abierto';

    $stmt = $conn->prepare("UPDATE acopios_cabecera SET estado = ? WHERE id = ?");
    $stmt->execute([$nuevo_estado, $id]);
}

header("Location: admin_panel.php");
exit();
?>