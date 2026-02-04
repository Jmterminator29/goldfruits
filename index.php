<?php
// index.php (Login)
session_start();
require_once 'db_connect.php';

// Si ya está logueado, redirigir según su rol guardado
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_rol'] === 'admin') {
        header("Location: admin_panel.php");
    } else {
        header("Location: mis_solicitudes.php");
    }
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['usuario'];
    $pass = $_POST['password'];

    // PEDIMOS EL ROL EN LA CONSULTA
    $stmt = $conn->prepare("SELECT id, nombre_completo, password, rol FROM usuarios WHERE usuario = ?");
    $stmt->execute([$user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // VERIFICACIÓN TEXTO PLANO + REDIRECCIÓN POR ROL
    if ($row && $pass == $row['password']) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_nombre'] = $row['nombre_completo'];
        $_SESSION['user_rol'] = $row['rol']; 

        if ($row['rol'] === 'admin') {
            header("Location: admin_panel.php");
        } else {
            header("Location: mis_solicitudes.php");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso GoldFruits</title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1b5e20">
    <link rel="apple-touch-icon" href="icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    
    <script>
        // Registro del Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js');
            });
        }
    </script>

    <style>
        body { font-family: sans-serif; background: #1b5e20; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: white; padding: 30px; border-radius: 10px; width: 90%; max-width: 320px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 16px; }
        button { width: 100%; padding: 12px; background: #fbc02d; border: none; font-weight: bold; border-radius: 5px; cursor: pointer; color: #1b5e20; font-size: 16px; margin-top: 10px; }
        h2 { color: #1b5e20; margin-top: 0; }
        .error { color: red; font-size: 0.9rem; margin-bottom: 10px; display: block; }

        /* Estilo del botón de instalación */
        #btnInstall {
            display: none; /* Oculto por defecto */
            margin-top: 20px;
            background: white;
            color: #1b5e20;
            border: 2px solid white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

    <div class="login-box">
        <h2>GoldFruits</h2>
        <p style="color:#666;">Sistema de Control</p>
        <?php if(isset($error)) echo "<span class='error'>$error</span>"; ?>
        <form method="POST">
            <input type="text" name="usuario" placeholder="Usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">INGRESAR</button>
        </form>
    </div>

    <button id="btnInstall">📲 INSTALAR APP</button>

    <script>
        let deferredPrompt;
        const btnInstall = document.getElementById('btnInstall');

        // Escuchar el evento 'beforeinstallprompt' (Solo Chrome/Android)
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevenir que el navegador muestre su propio aviso inmediatamente
            e.preventDefault();
            // Guardar el evento para dispararlo después
            deferredPrompt = e;
            // Mostrar nuestro botón
            btnInstall.style.display = 'block';
        });

        btnInstall.addEventListener('click', async () => {
            if (deferredPrompt) {
                // Mostrar el aviso nativo
                deferredPrompt.prompt();
                // Esperar a que el usuario responda
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to the install prompt: ${outcome}`);
                // Limpiar la variable
                deferredPrompt = null;
                // Ocultar botón
                btnInstall.style.display = 'none';
            }
        });

        // Detección simple para iOS (iPhone/iPad)
        const isIos = () => {
            const userAgent = window.navigator.userAgent.toLowerCase();
            return /iphone|ipad|ipod/.test(userAgent);
        }

        // Si es iOS y no está instalado, mostrar mensaje (opcional)
        if (isIos() && !window.navigator.standalone) {
            // En iOS no se puede instalar con botón, se debe hacer manual.
            // Podrías mostrar un texto aquí si quisieras:
            // btnInstall.style.display = 'block';
            // btnInstall.innerText = 'Para instalar: Pulsa Compartir y luego "Agregar a Inicio"';
            // btnInstall.disabled = true; // Solo informativo
        }
    </script>
</body>
</html>