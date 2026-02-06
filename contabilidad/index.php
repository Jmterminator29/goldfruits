<?php 
// 1. CONFIGURACIÓN REGIONAL Y CONEXIÓN
if (session_status() === PHP_SESSION_NONE) session_start();

// Forzar zona horaria de Perú
date_default_timezone_set('America/Lima');

$root = $_SERVER['DOCUMENT_ROOT'];
include_once $root . '/contabilidad/config/db.php'; 

// 2. CAPTURAR VISTA ACTUAL
$view = $_GET['view'] ?? 'inicio';

// 3. GENERAR FECHA EN ESPAÑOL
$meses_es = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
$fecha_hoy = date('d') . " de " . $meses_es[date('n') - 1] . " de " . date('Y');

// 4. DATOS PARA EL DASHBOARD (Solo inicio)
$total_p = 0; $total_a = 0;
if($view == 'inicio' && isset($conexion)) {
    $res_p = mysqli_query($conexion, "SELECT COUNT(*) as t FROM trabajadores WHERE estado='ACTIVO'");
    $total_p = ($res_p) ? mysqli_fetch_assoc($res_p)['t'] : 0;
    
    $res_a = mysqli_query($conexion, "SELECT COUNT(*) as t FROM areas");
    $total_a = ($res_a) ? mysqli_fetch_assoc($res_a)['t'] : 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Fruits | Gestión RRHH</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            --gf-green: #1b4d2e;
            --gf-light-green: #6dad38;
            --gf-orange: #f49d1a; 
            --gf-bg: #f0fdf4;
            --sidebar-width: 260px;
        }
        
        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: var(--gf-bg);
            background-image: radial-gradient(var(--gf-light-green) 0.5px, transparent 0.5px);
            background-size: 20px 20px;
            margin: 0;
            display: flex; /* Estructura Flexbox */
            min-height: 100vh;
        }

        /* --- SIDEBAR CORREGIDO --- */
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            height: 100vh; 
            position: sticky; /* Se mantiene al hacer scroll */
            top: 0;
            background: linear-gradient(180deg, var(--gf-green) 0%, #0f2b15 100%);
            color: white;
            display: flex;
            flex-direction: column;
            padding: 20px 15px;
            z-index: 1000;
        }

        .logo-area {
            text-align: center; margin-bottom: 25px;
            padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .logo-img { width: 120px; transition: transform 0.3s; }
        .logo-img:hover { transform: scale(1.05); }

        .nav-link {
            color: rgba(255,255,255,0.7); padding: 12px 15px; margin-bottom: 4px;
            border-radius: 12px; transition: all 0.3s; font-weight: 500;
            display: flex; align-items: center; text-decoration: none;
        }
        .nav-link i { font-size: 1.2rem; margin-right: 12px; width: 25px; text-align: center; }
        .nav-link:hover { background: rgba(255,255,255,0.1); color: white; transform: translateX(5px); }
        .nav-link.active { 
            background: var(--gf-orange); color: white; 
            box-shadow: 0 4px 15px rgba(244, 157, 26, 0.4); font-weight: 700;
        }

        /* --- CONTENIDO PRINCIPAL CORREGIDO --- */
        .main-wrapper {
            flex-grow: 1; /* Ocupa el resto del espacio */
            min-width: 0; /* Importante para que las tablas no rompan el flex */
            padding: 30px;
            display: flex;
            flex-direction: column;
        }

        .glass-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.8);
            box-shadow: 0 10px 30px rgba(27, 77, 46, 0.08);
            padding: 25px;
            width: 100%;
            overflow: hidden; /* Evita que el contenedor se estire de más */
        }

        /* DASHBOARD */
        .welcome-banner {
            background: linear-gradient(135deg, var(--gf-light-green), var(--gf-green));
            border-radius: 24px; padding: 30px; color: white;
            box-shadow: 0 15px 30px rgba(27, 77, 46, 0.15);
            margin-bottom: 25px;
        }
        .metric-card {
            background: white; border-radius: 20px; padding: 25px;
            text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            transition: transform 0.3s; height: 100%;
        }
        .metric-card:hover { transform: translateY(-5px); }
        .metric-value { font-size: 2.5rem; font-weight: 800; color: var(--gf-green); }

        /* Ajuste para tablas responsivas dentro del glass-container */
        .table-responsive {
            border-radius: 12px;
            overflow-x: auto;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-area">
            <img src="https://i.ibb.co/dJJSS7W0/Gemini-Generated-Image-52frmw52frmw52fr-removebg-preview.png" alt="Gold Fruits" class="logo-img">
            <div class="mt-2 small text-uppercase tracking-wider opacity-75 fw-bold" style="font-size: 0.7rem;">Gestión v3.0</div>
        </div>
        
        <div class="nav flex-column overflow-y-auto">
            <a href="index.php?view=inicio" class="nav-link <?= $view=='inicio'?'active':'' ?>">
                <i class="bi bi-grid-fill"></i> Inicio
            </a>
            
            <div class="small text-uppercase text-white-50 mt-4 mb-2 px-3" style="font-size: 0.65rem; letter-spacing: 1px;">Personal</div>
            <a href="index.php?view=lista_personal" class="nav-link <?= $view=='lista_personal'?'active':'' ?>">
                <i class="bi bi-people-fill"></i> Lista Personal
            </a>
            <a href="index.php?view=registrar" class="nav-link <?= $view=='registrar'?'active':'' ?>">
                <i class="bi bi-person-plus-fill"></i> Registrar
            </a>
            
            <div class="small text-uppercase text-white-50 mt-4 mb-2 px-3" style="font-size: 0.65rem; letter-spacing: 1px;">Nóminas</div>
            <a href="index.php?view=procesar" class="nav-link <?= $view=='procesar'?'active':'' ?>">
                <i class="bi bi-calculator"></i> Procesar
            </a>
            <a href="index.php?view=gestion" class="nav-link <?= $view=='gestion'?'active':'' ?>">
                <i class="bi bi-receipt"></i> Boletas
            </a>
            <a href="index.php?view=resumen_pagos" class="nav-link <?= $view=='resumen_pagos'?'active':'' ?>">
                <i class="bi bi-pie-chart-fill"></i> Reportes
            </a>

            <div class="small text-uppercase text-white-50 mt-4 mb-2 px-3" style="font-size: 0.65rem; letter-spacing: 1px;">Costos</div>
            <a href="index.php?view=reporteria" class="nav-link <?= $view=='reporteria'?'active':'' ?>">
                <i class="bi bi-graph-up-arrow"></i> Producción
            </a>

            <div class="small text-uppercase text-white-50 mt-4 mb-2 px-3" style="font-size: 0.65rem; letter-spacing: 1px;">Sistema</div>
            <a href="index.php?view=tarifas" class="nav-link <?= $view=='tarifas'?'active':'' ?>">
                <i class="bi bi-tags-fill"></i> Tarifas
            </a>
            <a href="index.php?view=configuracion" class="nav-link <?= $view=='configuracion'?'active':'' ?>">
                <i class="bi bi-gear-fill"></i> Config
            </a>
        </div>
    </aside>

    <main class="main-wrapper">
        
        <?php if($view == 'inicio'): ?>
            <div class="welcome-banner animate__animated animate__fadeIn">
                <span class="badge bg-white text-success mb-2 px-3 py-2 rounded-pill shadow-sm text-uppercase fw-bold" style="font-size: 0.7rem;">
                    <?= $fecha_hoy ?>
                </span>
                <h1 class="fw-bold">¡Bienvenido al Panel!</h1>
                <p class="opacity-75">Administración centralizada de Gold Fruits</p>
            </div>

            <div class="row g-4 animate__animated animate__fadeInUp">
                <div class="col-md-6">
                    <div class="metric-card border">
                        <div class="text-success fs-1 mb-2"><i class="bi bi-people-fill"></i></div>
                        <div class="metric-value"><?= $total_p ?></div>
                        <div class="text-muted text-uppercase fw-bold small">Colaboradores Activos</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-card border">
                        <div class="text-warning fs-1 mb-2"><i class="bi bi-building-fill"></i></div>
                        <div class="metric-value"><?= $total_a ?></div>
                        <div class="text-muted text-uppercase fw-bold small">Áreas Operativas</div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="glass-container animate__animated animate__fadeIn">
                <?php
                switch ($view) {
                    case 'lista_personal': include 'views/listar_personal.php'; break;
                    case 'registrar':      include 'views/registro_trabajador.php'; break;
                    case 'procesar':       include 'views/procesar_nomina.php'; break;
                    case 'gestion':        include 'views/gestion_nominas.php'; break;
                    case 'resumen_pagos':  include 'views/resumen_pagos.php'; break;
                    case 'reporteria':     include 'views/reporteria_produccion.php'; break; // NUEVA VISTA
                    case 'tarifas':        include 'views/categorias.php'; break;
                    case 'configuracion':  include 'views/configuracion.php'; break;
                    case 'editar':         include 'views/editar_trabajador.php'; break;
                    default: 
                        echo "<div class='text-center py-5'>
                                <i class='bi bi-exclamation-octagon text-danger display-1'></i>
                                <h3 class='mt-3'>Vista no encontrada</h3>
                                <a href='index.php' class='btn btn-success mt-2'>Volver al inicio</a>
                              </div>";
                }
                ?>
            </div>
        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Manejo de alertas vía URL
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        
        if (status === 'success') {
            Swal.fire({
                title: 'Completado',
                text: 'La operación se realizó con éxito.',
                icon: 'success',
                confirmButtonColor: '#1b4d2e',
                timer: 2000
            });
        } else if (status === 'updated') {
            Swal.fire({
                title: 'Actualizado',
                text: 'Información guardada correctamente.',
                icon: 'success',
                confirmButtonColor: '#1b4d2e'
            });
        } else if (status === 'error') {
            Swal.fire({
                title: 'Error',
                text: 'No se pudo completar la acción.',
                icon: 'error',
                confirmButtonColor: '#d33'
            });
        }
    </script>
</body>
</html>