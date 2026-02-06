<?php
session_start();
// Verifica logueo Y que sea admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_rol']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
?>