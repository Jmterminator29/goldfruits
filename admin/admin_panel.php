<?php
// 1. SEGURIDAD Y CONEXIÓN
require_once '../includes/auth_admin.php'; 
require_once '../includes/db_connect.php';

// 2. RECUPERAR NOMBRE (Lógica que ya funciona)
$nombre_usuario = "Administrador";

// A) Intentar leer de la sesión
if (!empty($_SESSION['user_nombre'])) {
    $nombre_usuario = $_SESSION['user_nombre'];
} elseif (!empty($_SESSION['nombres'])) {
    $nombre_usuario = $_SESSION['nombres'];
} elseif (!empty($_SESSION['nombre_completo'])) {
    $nombre_usuario = $_SESSION['nombre_completo'];
} 

// B) Si falla, buscar en BD
$id_user = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? null;

if ($nombre_usuario === "Administrador" && $id_user) {
    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$id_user]);
        $datos = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($datos) {
            if (!empty($datos['nombre_completo'])) {
                $nombre_usuario = $datos['nombre_completo'];
            } elseif (!empty($datos['nombres'])) {
                $nombre_usuario = $datos['nombres'];
                if (!empty($datos['apellidos'])) {
                    $nombre_usuario .= ' ' . explode(' ', $datos['apellidos'])[0];
                }
            } elseif (!empty($datos['usuario'])) {
                $nombre_usuario = $datos['usuario'];
            }
            $_SESSION['user_nombre'] = $nombre_usuario;
        }
    } catch (Exception $e) {}
}

$nombre_usuario = strtoupper($nombre_usuario);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel General | Gold Fruits</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        /* --- ESTILOS VISUALES PREMIUM --- */
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
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* NAVBAR REDISEÑADO */
        .app-bar {
            background: rgba(15, 61, 20, 0.9); /* Un poco más opaco */
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 12px 30px; /* Más padding horizontal */
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 30px rgba(0,0,0,0.3);
            height: 85px; /* Altura fija para acomodar el logo grande */
        }

        .main-logo { 
            height: 75px; /* MUCHO MÁS GRANDE */
            width: auto; 
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4)); 
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .main-logo:hover { transform: scale(1.08); }
        
        /* Contenedor de acciones derecha (Usuario + Logout) */
        .navbar-actions { display: flex; align-items: center; gap: 15px; }

        /* Insignia de Usuario Glass */
        .user-glass-badge {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 700;
            color: white;
            display: flex; align-items: center; gap: 10px;
            box-shadow: inset 0 0 15px rgba(255,255,255,0.05);
            letter-spacing: 0.5px;
        }
        .user-icon-gold { color: var(--gf-gold); font-size: 1.4rem; }

        /* Botón Logout Glass */
        .btn-glass-logout {
             background: rgba(211, 47, 47, 0.2);
             backdrop-filter: blur(5px);
             border: 1px solid rgba(211, 47, 47, 0.5);
             color: rgba(255,255,255,0.95);
             padding: 10px 20px;
             border-radius: 30px;
             text-decoration: none;
             transition: all 0.3s ease;
             display: flex; align-items: center; gap: 8px;
             font-weight: 700;
             font-size: 1.1rem;
        }
        .btn-glass-logout:hover {
            background: rgba(211, 47, 47, 0.85);
            border-color: rgba(211, 47, 47, 1);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(211, 47, 47, 0.4);
        }


        /* CONTENEDOR PRINCIPAL */
        .main-container {
            flex: 1;
            padding: 50px 20px;
            max-width: 1100px;
            margin: 0 auto;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .welcome-text {
            color: white;
            text-align: center;
            margin-bottom: 50px;
            text-shadow: 0 2px 15px rgba(0,0,0,0.6);
        }
        .welcome-title { font-weight: 900; font-size: 2.5rem; margin-bottom: 5px; color: var(--gf-gold); letter-spacing: 1px; }
        .welcome-subtitle { font-weight: 400; opacity: 0.9; font-size: 1.2rem; letter-spacing: 0.5px; }

        /* TARJETAS DE MÓDULOS */
        .module-card {
            background: rgba(255, 255, 255, 0.93);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 28px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.5);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--gf-dark);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .module-card:hover {
            transform: translateY(-12px) scale(1.03);
            box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.6);
            border-color: var(--gf-gold);
            background: rgba(255, 255, 255, 1);
        }

        .icon-wrapper {
            width: 90px; height: 90px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem;
            margin-bottom: 25px;
            transition: transform 0.4s ease;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .module-card:hover .icon-wrapper { transform: scale(1.15) rotate(8deg); }

        /* Colores Específicos */
        .card-acopio .icon-wrapper { background: #e8f5e9; color: var(--gf-primary); border: 3px solid #c8e6c9; }
        .card-nomina .icon-wrapper { background: #fffde7; color: #f57f17; border: 3px solid #fff9c4; }
        .card-config .icon-wrapper { background: #f3e5f5; color: #7b1fa2; border: 3px solid #e1bee7; }

        .module-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 12px; letter-spacing: 0.5px; }
        .module-desc { font-size: 1rem; color: #555; line-height: 1.5; font-weight: 500; }

        /* --- MÓVIL OPTIMIZADO --- */
        @media (max-width: 768px) {
            .app-bar { padding: 10px 15px; height: 70px; }
            .main-logo { height: 55px; } /* Logo grande también en móvil */
            .navbar-actions { gap: 8px; }
            .user-glass-badge { padding: 6px 12px; font-size: 0.9rem; }
            .user-icon-gold { font-size: 1.1rem; }
            .btn-glass-logout { padding: 8px; font-size: 1.2rem; border-radius: 50%; width: 40px; height: 40px; justify-content: center; }
            .btn-glass-logout span { display: none; } /* Ocultar texto "Salir" en móvil */
            
            .welcome-title { font-size: 1.8rem; }
            .main-container { padding-top: 30px; justify-content: flex-start; }

            .module-card {
                flex-direction: row; text-align: left; padding: 25px 20px;
                min-height: auto; gap: 20px; align-items: center; justify-content: flex-start;
                border-radius: 20px;
            }
            .icon-wrapper { width: 65px; height: 65px; font-size: 2.2rem; margin-bottom: 0; flex-shrink: 0; border-width: 2px; }
            .module-title { font-size: 1.3rem; margin-bottom: 4px; }
            .module-desc { font-size: 0.9rem; margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            
            .module-card::after {
                content: '\F285'; font-family: 'bootstrap-icons';
                margin-left: auto; color: var(--gf-gold); font-size: 1.5rem; font-weight: bold;
            }
        }
    </style>
</head>
<body>

    <div class="app-bar animate__animated animate__fadeInDown">
        <img src="https://i.ibb.co/KzVLFpSV/Gemini-Generated-Image-45ambn45ambn45am-removebg-preview-2.png" alt="Gold Fruits" class="main-logo">
        
        <div class="navbar-actions">
            <div class="user-glass-badge">
                <i class="bi bi-person-circle user-icon-gold"></i>
                <span class="d-none d-sm-block"><?= htmlspecialchars($nombre_usuario) ?></span>
            </div>

            <a href="../logout.php" class="btn-glass-logout" title="Cerrar Sesión">
                <i class="bi bi-box-arrow-right"></i>
                <span class="d-none d-md-block">Salir</span>
            </a>
        </div>
    </div>

    <div class="main-container">
        
        <div class="welcome-text animate__animated animate__fadeIn">
            <div class="welcome-title">Panel General</div>
            <div class="welcome-subtitle">Centro de Operaciones</div>
        </div>

        <div class="row g-4 justify-content-center animate__animated animate__fadeInUp">
            
            <div class="col-12 col-md-6 col-lg-4">
                <a href="admin_panel_acopios.php" class="module-card card-acopio">
                    <div class="icon-wrapper">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <div class="module-title">Gestión de Acopios</div>
                        <div class="module-desc">Control de cargas, liquidaciones, proveedores y reportes.</div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <a href="../contabilidad/index.php" class="module-card card-nomina">
                    <div class="icon-wrapper">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <div class="module-title">Nóminas y RRHH</div>
                        <div class="module-desc">Administración de personal, asistencia y pagos.</div>
                    </div>
                </a>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <a href="#" class="module-card card-config" onclick="Swal.fire({title:'Próximamente', text:'Módulo en construcción', icon:'info', confirmButtonColor:'var(--gf-primary)'}); return false;">
                    <div class="icon-wrapper">
                        <i class="bi bi-gear-wide-connected"></i>
                    </div>
                    <div>
                        <div class="module-title">Configuración</div>
                        <div class="module-desc">Gestión de usuarios, roles y parámetros del sistema.</div>
                    </div>
                </a>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>