<?php
// db_connect.php
$host = "localhost";
$db_name = "acopio";
$username = "administrador";
$password = "{ezue?Tu=^yO"; 

try {
    $conn = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("set names utf8");
} catch(PDOException $exception) {
    die("Error de conexión: " . $exception->getMessage());
}
?>