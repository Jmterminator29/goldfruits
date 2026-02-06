<?php
// Conexión exclusiva para el Módulo de Contabilidad (RRHH)
// Usa MySQLi porque el sistema antiguo así lo requiere.

$host = "localhost";
$usuario = "administrador";       // El usuario que definiste
$password = "{ezue?Tu=^yO"; // La contraseña que definiste
$basedatos = "nominas";      // <--- OJO: Si es OTRA base de datos, cambia "acopio" por el nombre real de la BD de RRHH.

// Crear conexión estilo MySQLi (Compatible con el código legacy)
$conn = new mysqli($host, $usuario, $password, $basedatos);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión (RRHH): " . $conn->connect_error);
}

// Forzar caracteres especiales (tildes, ñ)
$conn->set_charset("utf8");
?>