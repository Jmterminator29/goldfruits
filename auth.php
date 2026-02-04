<?php
session_start();
// Si no hay usuario logueado, mandar al login
if (!isset($_SESSION['user_id'])) {
    // El login del sistema está en index.php
    header("Location: index.php");
    exit();
}
?>