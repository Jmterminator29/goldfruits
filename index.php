<?php
session_start();
require_once 'includes/db_connect.php';

// 1. Si ya tiene sesión, redirigir
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin') {
        header("Location: admin/admin_panel.php");
    } else {
        header("Location: user/mis_solicitudes.php");
    }
    exit();
}

// 2. Procesar Login (Self-Post)
$error = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['usuario']);
    $pass = trim($_POST['password']);

    // Buscamos usuario y rol
    $stmt = $conn->prepare("SELECT id, nombre_completo, password, rol FROM usuarios WHERE usuario = ? LIMIT 1");
    $stmt->execute([$user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $pass == $row['password']) {
        // Login Exitoso
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_nombre'] = $row['nombre_completo'];
        $_SESSION['user_rol'] = $row['rol']; // Estandarizado a 'user_rol'

        // Redirección
        if ($row['rol'] === 'admin') {
            header("Location: admin/admin_panel.php");
        } else {
            header("Location: user/mis_solicitudes.php");
        }
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acceso | GoldFruits</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1b5e20">
    <style>
        :root { --primary: #1b5e20; --gold: #fbc02d; }
        * { box-sizing: border-box; font-family: sans-serif; margin: 0; padding: 0; }
        body { background: linear-gradient(135deg, #1b5e20 0%, #003300 100%); height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .login-card { background: white; width: 100%; max-width: 380px; padding: 40px 30px; border-radius: 24px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        h2 { color: var(--primary); margin-bottom: 5px; }
        input { width: 100%; padding: 14px; margin-bottom: 15px; border: 2px solid #eee; border-radius: 12px; font-size: 1rem; outline: none; }
        input:focus { border-color: var(--primary); }
        button { width: 100%; padding: 16px; background: var(--gold); color: #003300; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; font-size: 1rem; }
        .error { background: #ffebee; color: #c62828; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="font-size: 3rem; margin-bottom: 10px;">🥑</div>
        <h2>GoldFruits</h2>
        <p style="color: #666; margin-bottom: 30px;">Control de Acopio</p>

        <?php if($error): ?>
            <div class="error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="usuario" placeholder="Usuario" required autocomplete="username">
            <input type="password" name="password" placeholder="Contraseña" required autocomplete="current-password">
            <button type="submit">INGRESAR</button>
        </form>
    </div>
    <script>
        if ('serviceWorker' in navigator) { navigator.serviceWorker.register('sw.js'); }
    </script>
</body>
</html>
