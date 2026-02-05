<?php
include_once '../config/db.php';

$id_cat = $_POST['id_cat'];
$id_aseg = $_POST['id_aseg'];

// Obtener sueldo base
$q1 = mysqli_query($conexion, "SELECT monto_categoria FROM categorias_pago WHERE id_categoria = '$id_cat'");
$sueldo = ($r = mysqli_fetch_assoc($q1)) ? $r['monto_categoria'] : 0;

// Obtener porcentaje seguro
$q2 = mysqli_query($conexion, "SELECT porcentaje_descuento FROM aseguradoras WHERE id_aseguradora = '$id_aseg'");
$porc = ($r2 = mysqli_fetch_assoc($q2)) ? $r2['porcentaje_descuento'] : 0;

echo json_encode(['sueldo' => $sueldo, 'porcentaje' => $porc]);
?>