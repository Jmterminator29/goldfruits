<?php
// contabilidad/config/db.php
$host = "localhost";
$usuario = "administrador";
$password = "{ezue?Tu=^yO";
$basedatos = "nominas"; // O 'acopio', según tu BD real

$conexion = new mysqli($host, $usuario, $password, $basedatos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8");
