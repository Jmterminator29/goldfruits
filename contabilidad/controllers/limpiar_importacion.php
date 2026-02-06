<?php
session_start();
unset($_SESSION['importacion_asistencia']);
unset($_SESSION['mensaje_importacion']);
header("Location: ../index.php?view=nomina");
exit();
?>