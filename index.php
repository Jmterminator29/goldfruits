<?php
/**
 * SISTEMA GOLDFRUITS V4 - LOGIN CENTRALIZADO MULTIMÓDULO
 * Diseño: Enterprise Glassmorphism (Footer corregido)
 */
session_start();
// FUERZA UTF-8 EN EL NAVEGADOR
header('Content-Type: text/html; charset=utf-8');

require_once 'includes/db_connect.php';

// --- 1. LÓGICA DE REDIRECCIÓN SI YA HAY SESIÓN ---
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['user_rol'])) {
        switch ($_SESSION['user_rol']) {
            case 'admin':
                header("Location: admin/admin_panel.php");
                break;
            case 'contabilidad':
                header("Location: contabilidad/index.php");
                break;
            default:
                header("Location: user/mis_solicitudes.php");
                break;
        }
    } else {
        header("Location: user/mis_solicitudes.php");
    }
    exit();
}

// --- 2. PROCESAMIENTO DEL LOGIN ---
$error_msg = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = trim($_POST['usuario']);
    $pass = trim($_POST['password']);

    // Consulta segura
    $stmt = $conn->prepare("SELECT id, nombre_completo, password, rol FROM usuarios WHERE usuario = ? LIMIT 1");
    $stmt->execute([$user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificación
    if ($row && $pass === $row['password']) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_nombre'] = $row['nombre_completo'];
        $_SESSION['user_rol'] = $row['rol'];

        switch ($row['rol']) {
            case 'admin':
                header("Location: admin/admin_panel.php");
                break;
            case 'contabilidad':
                header("Location: contabilidad/index.php");
                break;
            default:
                header("Location: user/mis_solicitudes.php");
                break;
        }
        exit();
    } else {
        $error_msg = "Credenciales incorrectas. Verifique su usuario y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Seguro | GoldFruits Plataforma</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --gf-primary: #1b5e20;   /* Verde Institucional */
            --gf-dark: #0f3d14;      /* Verde Profundo */
            --gf-gold: #fbc02d;      /* Dorado Acento */
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--gf-dark);
            background-image: 
                radial-gradient(at 0% 0%, rgba(251, 192, 45, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(27, 94, 32, 0.2) 0px, transparent 50%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            perspective: 1000px;
        }

        .card-login {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .card-login:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.6);
        }

        .header-brand {
            background: white;
            padding: 40px 30px 20px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .header-brand::after {
            content: '';
            position: absolute;
            bottom: -1px; left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--gf-gold);
            border-radius: 2px;
        }

        .logo-img {
            width: 140px; 
            height: auto;
            display: block;
            margin: 0 auto 10px;
            animation: float 3s ease-in-out infinite;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.15));
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .brand-subtitle {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-top: 5px; 
        }

        .login-body {
            padding: 40px 30px;
        }

        .form-floating > .form-control {
            border-radius: 12px;
            border: 2px solid #f1f3f5;
            height: 55px;
            font-weight: 500;
            color: var(--gf-dark);
        }

        .form-floating > .form-control:focus {
            border-color: var(--gf-primary);
            box-shadow: 0 0 0 4px rgba(27, 94, 32, 0.1);
        }

        .form-floating > label {
            color: #adb5bd;
            font-weight: 500;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: var(--gf-primary);
            font-weight: 600;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #adb5bd;
            font-size: 1.2rem;
            z-index: 5;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: var(--gf-primary);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--gf-gold) 0%, #f9a825 100%);
            color: #0f3d14;
            border: none;
            padding: 14px;
            border-radius: 12px;
            width: 100%;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-top: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(249, 168, 37, 0.4);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px -5px rgba(249, 168, 37, 0.5);
            color: #000;
        }
        
        .btn-login:active {
            transform: scale(0.98);
        }

        .footer-copy {
            text-align: center;
            margin-top: 25px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
            font-weight: 400;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="card card-login">
            
            <div class="header-brand">
                <img src="https://i.ibb.co/Ng4vvTNq/logo.png" alt="GoldFruits Logo" class="logo-img">
                <div class="brand-subtitle">Sistema de Gestión Integral</div>
            </div>

            <div class="login-body">
                <form method="POST" action="" id="loginForm" autocomplete="off">
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="usuario" name="usuario" placeholder="Usuario" required>
                        <label for="usuario"><i class="bi bi-person me-2"></i>Usuario</label>
                    </div>

                    <div class="form-floating mb-4 position-relative">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required style="padding-right: 45px;">
                        <label for="password"><i class="bi bi-lock me-2"></i>Contraseña</label>
                        <i class="bi bi-eye-slash password-toggle" id="togglePass" onclick="togglePassword()" title="Ver contraseña"></i>
                    </div>

                    <button type="submit" class="btn btn-login" id="btnSubmit">
                        <span class="btn-text">INGRESAR AL SISTEMA</span>
                    </button>

                </form>
            </div>
        </div>

        <div class="footer-copy">
            &copy; <?php echo date('Y'); ?> GoldFruits S.A.C. <br> Todos los derechos reservados.
        </div>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById('password');
            const icon = document.getElementById('togglePass');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            } else {
                passInput.type = 'password';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ACCEDIENDO...';
        });

        <?php if(!empty($error_msg)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Credenciales Incorrectas',
                text: '<?php echo $error_msg; ?>',
                confirmButtonColor: '#1b5e20',
                confirmButtonText: 'Reintentar',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-success px-4 rounded-pill'
                }
            });
        });
        <?php endif; ?>

        if ('serviceWorker' in navigator) { navigator.serviceWorker.register('sw.js'); }
    </script>

</body>
</html>