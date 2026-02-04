<?php
session_start();
session_unset();   // Libera todas las variables de sesión
session_destroy(); // Destruye la sesión completamente

// REDIRECCIÓN CORRECTA:
// Si renombraste tu login a "index.php", usa esta línea:
header("Location: index.php"); 

// Si tu archivo todavía se llama "login.php", usa esta otra (borra la anterior):
// header("Location: login.php");

exit();
?>