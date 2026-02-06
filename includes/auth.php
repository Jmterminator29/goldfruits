<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // Usamos ../index.php asumiendo que este archivo se incluye desde carpeta admin/ o user/
    header("Location: ../index.php");
    exit();
}
?>