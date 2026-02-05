<?php
$host = "sql310.infinityfree.com";
$user = "if0_40786255";
$pass = "MesaIGS22a"; // Tu contraseña visible en la captura
$db   = "if0_40786255_zqtime";

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
mysqli_set_charset($conexion, "utf8");
?>