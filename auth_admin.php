<?php
session_start();
// Si no hay sesión o el rol NO es admin, fuera.
if (!isset($_SESSION['user_id']) || $_SESSION['user_rol'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>