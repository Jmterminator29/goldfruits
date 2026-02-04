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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Acceso | GoldFruits</title>
    
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
        :root {
            --primary: #1b5e20;
            --primary-dark: #003300;
            --gold: #fbc02d;
            --gold-hover: #f9a825;
            --bg-light: #f4f6f8;
            --text-dark: #1a1a1a;
            --text-gray: #666;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }

        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            width: 100%;
            max-width: 380px;
            padding: 40px 30px;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        /* Decoración superior */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 6px;
            background: linear-gradient(90deg, var(--gold), var(--primary));
        }

        .brand-logo {
            font-size: 3.5rem;
            margin-bottom: 10px;
            display: block;
            letter-spacing: -5px; /* Juntar un poco los emojis */
        }

        h2 {
            color: var(--primary);
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }

        p.subtitle {
            color: var(--text-gray);
            font-size: 0.95rem;
            margin-bottom: 30px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            font-size: 0.8rem;
            color: var(--primary);
            font-weight: 700;
            margin-left: 5px;
            margin-bottom: 5px;
            display: block;
        }

        .input-wrapper {
            position: relative;
        }

        /* Íconos de la izquierda (Usuario/Candado) */
        .input-wrapper .icon-left {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            opacity: 0.5;
            pointer-events: none;
        }

        /* Ícono de la derecha (Ojo ver contraseña) */
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.6;
            z-index: 10;
            user-select: none;
        }
        
        .toggle-password:hover {
            opacity: 1;
        }

        input {
            width: 100%;
            padding: 14px 45px 14px 45px; /* Padding a ambos lados para los íconos */
            border: 2px solid #eee;
            border-radius: 12px;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            background: #fcfcfc;
            color: var(--text-dark);
        }

        input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 4px rgba(27, 94, 32, 0.1);
        }

        button {
            width: 100%;
            padding: 16px;
            background: var(--gold);
            color: var(--primary-dark);
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
            box-shadow: 0 4px 6px rgba(251, 192, 45, 0.3);
        }

        button:active {
            transform: scale(0.98);
        }

        .error-msg {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
            text-align: left;
        }

        /* Botón Instalar Flotante */
        #btnInstall {
            display: none;
            position: fixed;
            bottom: 30px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: background 0.3s;
            z-index: 1000;
        }
        
        #btnInstall:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        @media (max-height: 600px) {
            .login-card { padding: 25px 20px; }
            .brand-logo { font-size: 2.5rem; margin-bottom: 5px; }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <span class="brand-logo">🥑🥭</span>
        <h2>GoldFruits</h2>
        <p class="subtitle">Sistema de Control de Acopio</p>

        <?php if(isset($error)): ?>
            <div class="error-msg">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>USUARIO</label>
                <div class="input-wrapper">
                    <span class="icon-left">👤</span>
                    <input type="text" name="usuario" placeholder="Ingresa tu usuario" required autocomplete="username">
                </div>
            </div>

            <div class="input-group">
                <label>CONTRASEÑA</label>
                <div class="input-wrapper">
                    <span class="icon-left">🔒</span>
                    <input type="password" id="passwordInput" name="password" placeholder="••••••••" required autocomplete="current-password">
                    <span class="toggle-password" onclick="togglePass()">👁️</span>
                </div>
            </div>

            <button type="submit">INICIAR SESIÓN</button>
        </form>
    </div>

    <button id="btnInstall">📲 Instalar Aplicación</button>

    <script>
        // Función para ver/ocultar contraseña
        function togglePass() {
            const input = document.getElementById('passwordInput');
            const icon = document.querySelector('.toggle-password');
            if (input.type === "password") {
                input.type = "text";
                icon.style.opacity = "1"; // Resaltar ojo cuando se ve
            } else {
                input.type = "password";
                icon.style.opacity = "0.6"; // Opaco cuando está oculta
            }
        }

        // Lógica PWA (Instalación)
        let deferredPrompt;
        const btnInstall = document.getElementById('btnInstall');

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            btnInstall.style.display = 'block';
            
            btnInstall.style.opacity = '0';
            btnInstall.style.transform = 'translateY(20px)';
            setTimeout(() => {
                btnInstall.style.transition = 'all 0.5s ease';
                btnInstall.style.opacity = '1';
                btnInstall.style.transform = 'translateY(0)';
            }, 100);
        });

        btnInstall.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log(`User response to the install prompt: ${outcome}`);
                deferredPrompt = null;
                btnInstall.style.display = 'none';
            }
        });

        const isIos = () => {
            const userAgent = window.navigator.userAgent.toLowerCase();
            return /iphone|ipad|ipod/.test(userAgent);
        }
    </script>
</body>
</html>