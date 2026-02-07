<?php 
// 1. INICIAR SESIÓN Y CONFIGURACIÓN
if (session_status() === PHP_SESSION_NONE) session_start();

// Forzar zona horaria
date_default_timezone_set('America/Lima');

// 2. CONEXIÓN A BASE DE DATOS DE CONTABILIDAD
require_once __DIR__ . '/config/db.php'; 

// 3. CAPTURAR VISTA ACTUAL
$view = $_GET['view'] ?? 'inicio';
// Compatibilidad
if ($view === 'config') $view = 'configuracion';
if ($view === 'lista') $view = 'lista_personal';

// 4. GENERAR FECHA
$meses_es = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
$fecha_hoy = date('d') . " de " . $meses_es[date('n') - 1] . " de " . date('Y');

// 5. DATOS DASHBOARD
$total_p = 0; $total_a = 0;
if($view == 'inicio' && isset($conexion) && !$conexion->connect_error) {
    $res_p = @mysqli_query($conexion, "SELECT COUNT(*) as t FROM trabajadores WHERE estado='ACTIVO'");
    if($res_p) $total_p = mysqli_fetch_assoc($res_p)['t'];
    
    $res_a = @mysqli_query($conexion, "SELECT COUNT(*) as t FROM areas");
    if($res_a) $total_a = mysqli_fetch_assoc($res_a)['t'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Fruits | Gestión de Nóminas</title>
    
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
            background-image: radial-gradient(rgba(109, 173, 56, 0.2) 0.5px, transparent 0.5px);
            background-size: 20px 20px;
            margin: 0;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden; 
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width); 
            height: 100vh; 
            position: sticky; top: 0;
            background: linear-gradient(180deg, var(--gf-green) 0%, #0f2b15 100%);
            color: white;
            display: flex; flex-direction: column;
            z-index: 1050;
            transition: all 0.3s ease;
            box-shadow: 4px 0 15px rgba(0,0,0,0.15);
        }

        .sidebar-header { padding: 20px 15px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .sidebar-menu { flex-grow: 1; overflow-y: auto; padding: 15px; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.3) transparent; }
        .sidebar-footer { padding: 20px 15px; border-top: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; background: rgba(0,0,0,0.1); }

        .logo-img { width: 100px; transition: transform 0.3s; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1)); }
        .logo-img:hover { transform: scale(1.05); }

        .nav-link {
            color: rgba(255,255,255,0.85); padding: 12px 15px; margin-bottom: 5px;
            border-radius: 12px; transition: all 0.2s; font-weight: 500;
            display: flex; align-items: center; text-decoration: none; font-size: 0.9rem;
        }
        .nav-link i { font-size: 1.2rem; margin-right: 12px; width: 25px; text-align: center; opacity: 0.9; }
        .nav-link:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-link.active { background: var(--gf-orange); color: white; box-shadow: 0 4px 15px rgba(244, 157, 26, 0.4); font-weight: 700; }

        /* --- CONTENIDO --- */
        .main-wrapper { flex: 1; min-width: 0; padding: 25px; display: flex; flex-direction: column; transition: all 0.3s ease; }

        .glass-container {
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(12px);
            border-radius: 20px; border: 1px solid rgba(255,255,255,0.6);
            box-shadow: 0 10px 40px rgba(27, 77, 46, 0.08); padding: 25px;
            width: 100%; overflow: hidden;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--gf-light-green), var(--gf-green));
            border-radius: 20px; padding: 30px; color: white;
            box-shadow: 0 10px 25px rgba(27, 77, 46, 0.15); margin-bottom: 25px; position: relative; overflow: hidden;
        }
        .metric-card {
            background: white; border-radius: 20px; padding: 25px;
            text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.03);
            transition: transform 0.3s; height: 100%; border: 1px solid rgba(0,0,0,0.02);
        }
        .metric-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.08); }
        .metric-value { font-size: 2.8rem; font-weight: 800; color: var(--gf-green); margin: 10px 0; }

        /* --- RESPONSIVE --- */
        .mobile-nav-toggle { display: none; }
        .sidebar-overlay { display: none; }

        @media (max-width: 991px) {
            .sidebar { position: fixed; left: -100%; top: 0; bottom: 0; width: 280px; height: 100vh; box-shadow: 5px 0 25px rgba(0,0,0,0.3); }
            .sidebar.active { left: 0; }
            .main-wrapper { width: 100%; padding: 15px; }
            .mobile-nav-toggle { display: block; margin-bottom: 20px; }
            .btn-menu-mobile {
                background: white; color: var(--gf-green); border: none; padding: 10px 20px; border-radius: 50px;
                font-weight: 700; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 10px;
            }
            .sidebar-overlay {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6);
                z-index: 1040; backdrop-filter: blur(4px); display: none;
            }
            .sidebar-overlay.active { display: block; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="https://i.ibb.co/dJJSS7W0/Gemini-Generated-Image-52frmw52frmw52fr-removebg-preview.png" alt="Gold Fruits" class="logo-img">
            <div class="mt-2 small text-uppercase tracking-wider opacity-75 fw-bold" style="font-size: 0.7rem;">Gestión de Nóminas</div>
        </div>
        
        <div class="sidebar-menu">
            <a href="index.php?view=inicio" class="nav-link <?= $view=='inicio'?'active':'' ?>">
                <i class="bi bi-grid-fill"></i> Inicio
            </a>
            
            <div class="small text-uppercase text-white-50 mt-3 mb-2 px-3 fw-bold" style="font-size: 0.7rem;">Personal</div>
            <a href="index.php?view=lista_personal" class="nav-link <?= $view=='lista_personal'?'active':'' ?>">
                <i class="bi bi-people-fill"></i> Lista Personal
            </a>
            <a href="index.php?view=registrar" class="nav-link <?= $view=='registrar'?'active':'' ?>">
                <i class="bi bi-person-plus-fill"></i> Registrar
            </a>
            
            <div class="small text-uppercase text-white-50 mt-3 mb-2 px-3 fw-bold" style="font-size: 0.7rem;">Nóminas</div>
            <a href="index.php?view=procesar" class="nav-link <?= $view=='procesar'?'active':'' ?>">
                <i class="bi bi-calculator"></i> Procesar
            </a>
            <a href="index.php?view=gestion" class="nav-link <?= $view=='gestion'?'active':'' ?>">
                <i class="bi bi-receipt"></i> Boletas
            </a>
            <a href="index.php?view=resumen_pagos" class="nav-link <?= $view=='resumen_pagos'?'active':'' ?>">
                <i class="bi bi-pie-chart-fill"></i> Reportes
            </a>

            <div class="small text-uppercase text-white-50 mt-3 mb-2 px-3 fw-bold" style="font-size: 0.7rem;">Operaciones</div>
            <a href="index.php?view=reporteria" class="nav-link <?= $view=='reporteria'?'active':'' ?>">
                <i class="bi bi-graph-up-arrow"></i> Producción
            </a>

            <div class="small text-uppercase text-white-50 mt-3 mb-2 px-3 fw-bold" style="font-size: 0.7rem;">Sistema</div>
            <a href="index.php?view=tarifas" class="nav-link <?= $view=='tarifas'?'active':'' ?>">
                <i class="bi bi-tags-fill"></i> Tarifas
            </a>
            <a href="index.php?view=configuracion" class="nav-link <?= $view=='configuracion'?'active':'' ?>">
                <i class="bi bi-gear-fill"></i> Configuración
            </a>
        </div>

        <div class="sidebar-footer">
            <a href="../logout.php" class="nav-link text-white bg-danger bg-opacity-25 justify-content-center fw-bold shadow-sm">
                <i class="bi bi-box-arrow-left"></i> CERRAR SESIÓN
            </a>
        </div>
    </aside>

    <main class="main-wrapper">
        
        <div class="mobile-nav-toggle">
            <button class="btn-menu-mobile" onclick="toggleSidebar()">
                <i class="bi bi-list fs-4"></i> MENÚ
            </button>
        </div>

        <?php if($view == 'inicio'): ?>
            <div class="welcome-banner animate__animated animate__fadeIn">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-white text-success mb-3 px-3 py-2 rounded-pill shadow-sm text-uppercase fw-bold" style="font-size: 0.7rem;">
                            <i class="bi bi-calendar-event me-2"></i><?= $fecha_hoy ?>
                        </span>
                        <h1 class="fw-bold mb-1">Bienvenido</h1>
                        <p class="opacity-75 mb-0">Sistema de Gestión de Nóminas y RRHH.</p>
                    </div>
                    <div class="d-none d-md-block opacity-25">
                        <i class="bi bi-flower1" style="font-size: 4rem;"></i>
                    </div>
                </div>
            </div>

            <div class="row g-4 animate__animated animate__fadeInUp">
                <div class="col-md-6 col-xl-4">
                    <div class="metric-card">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle text-success">
                                <i class="bi bi-people-fill fs-3"></i>
                            </div>
                        </div>
                        <div class="metric-value"><?= $total_p ?></div>
                        <div class="text-muted text-uppercase fw-bold small ls-1">Personal Activo</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-4">
                    <div class="metric-card">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle text-warning">
                                <i class="bi bi-building-fill fs-3"></i>
                            </div>
                        </div>
                        <div class="metric-value"><?= $total_a ?></div>
                        <div class="text-muted text-uppercase fw-bold small ls-1">Áreas / Fincas</div>
                    </div>
                </div>
                <div class="col-md-12 col-xl-4">
                    <div class="metric-card bg-primary text-white" style="background: linear-gradient(135deg, #1b4d2e, #2e7d32) !important;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill me-2"></i>Acciones Rápidas</h5>
                        <div class="d-grid gap-2">
                            <a href="index.php?view=registrar" class="btn btn-light btn-sm fw-bold text-success text-start border-0"><i class="bi bi-plus-lg me-2"></i>Nuevo Ingreso</a>
                            <a href="index.php?view=procesar" class="btn btn-outline-light btn-sm fw-bold text-start"><i class="bi bi-calculator me-2"></i>Procesar Nómina</a>
                        </div>
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
                    case 'reporteria':     include 'views/reporteria_produccion.php'; break;
                    case 'tarifas':        include 'views/categorias.php'; break;
                    case 'configuracion':  include 'views/configuracion.php'; break;
                    case 'editar':         include 'views/editar_trabajador.php'; break;
                    default: 
                        echo "<div class='text-center py-5'>
                                <div class='mb-3 text-danger opacity-50'><i class='bi bi-ban' style='font-size: 4rem;'></i></div>
                                <h3 class='fw-bold text-secondary'>Página no encontrada</h3>
                                <a href='index.php' class='btn btn-success rounded-pill px-4 mt-2 fw-bold'>Volver al inicio</a>
                              </div>";
                }
                ?>
            </div>
        <?php endif; ?>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }

        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        
        if (status) {
            let title = 'Información', icon = 'info', text = 'Operación realizada';
            if (status === 'success') { title='¡Éxito!'; icon='success'; text='La operación se completó correctamente.'; }
            if (status === 'updated') { title='Actualizado'; icon='success'; text='Datos guardados correctamente.'; }
            if (status === 'deleted') { title='Eliminado'; icon='success'; text='Registro eliminado.'; }
            if (status === 'error') { title='Error'; icon='error'; text='No se pudo completar la acción.'; }

            Swal.fire({
                title: title, text: text, icon: icon,
                confirmButtonColor: '#1b4d2e', timer: 2000, timerProgressBar: true
            });
            const newUrl = window.location.pathname + window.location.search.replace(/[\?&]status=[^&]+/, '').replace(/^&/, '?');
            window.history.replaceState({}, document.title, newUrl);
        }
    </script>
</body>
</html>