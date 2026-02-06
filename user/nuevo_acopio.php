<?php 
require_once '../includes/auth.php'; 
// Asegurar UTF-8
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1b5e20"> 
    <title>Nuevo Acopio | GoldFruits</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <script src="offline_queue.js"></script>
    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js');
      }
    </script>

    <style>
        /* --- ESTILOS VISUALES PREMIUM --- */
        :root {
            --gf-primary: #1b5e20;
            --gf-dark: #0f3d14;
            --gf-gold: #fbc02d;
            --gf-glass: rgba(255, 255, 255, 0.95);
            --gf-glass-border: rgba(255, 255, 255, 0.5);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--gf-dark);
            /* Fondo corporativo sutil */
            background-image: 
                radial-gradient(at 0% 0%, rgba(251, 192, 45, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(27, 94, 32, 0.2) 0px, transparent 50%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            color: #333;
            padding-bottom: 100px; /* Espacio para el botón flotante */
            min-height: 100vh;
        }

        /* NAVBAR CON EFECTO CRISTAL */
        .app-bar {
            background: rgba(27, 94, 32, 0.9);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .menu-btn { font-size: 1.6rem; cursor: pointer; color: var(--gf-gold); }
        .page-title { font-weight: 700; font-size: 1.1rem; letter-spacing: 0.5px; margin: 0; }

        /* SIDEBAR PREMIUM */
        .sidebar {
            height: 100%; width: 0; position: fixed; z-index: 2000; top: 0; left: 0;
            background: linear-gradient(180deg, #0f3d14 0%, #1b5e20 100%);
            overflow-x: hidden; transition: 0.3s; padding-top: 60px;
            box-shadow: 5px 0 25px rgba(0,0,0,0.5);
        }
        .sidebar a {
            padding: 15px 25px; text-decoration: none; font-size: 1rem; color: rgba(255,255,255,0.8);
            display: block; transition: 0.2s; border-bottom: 1px solid rgba(255,255,255,0.05);
            font-weight: 500;
        }
        .sidebar a:hover { background: rgba(255,255,255,0.1); color: white; padding-left: 30px; }
        .sidebar .closebtn { position: absolute; top: 10px; right: 20px; font-size: 36px; color: var(--gf-gold); }
        #overlay { position: fixed; display: none; width: 100%; height: 100%; top: 0; left: 0; background: rgba(0,0,0,0.7); z-index: 1500; backdrop-filter: blur(3px); }

        /* TARJETAS GLASSMORPHISM */
        .gf-card {
            background: var(--gf-glass);
            border: 1px solid var(--gf-glass-border);
            border-radius: 16px;
            margin-bottom: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .gf-card.active {
            border: 2px solid var(--gf-gold);
            transform: scale(1.01);
            box-shadow: 0 10px 40px rgba(251, 192, 45, 0.15);
        }

        .gf-card-header {
            padding: 18px 20px;
            font-weight: 700;
            cursor: pointer;
            background: rgba(255,255,255,0.5);
            color: var(--gf-primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .gf-card.active .gf-card-header {
            background: linear-gradient(90deg, rgba(251, 192, 45, 0.1) 0%, transparent 100%);
            color: #000;
        }

        .gf-card-body { display: none; padding: 20px; }
        .gf-card.active .gf-card-body { display: block; animation: slideDown 0.3s ease; }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* INPUTS MODERNOS */
        .gf-input-group {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 8px 12px;
            margin-bottom: 10px;
            transition: 0.3s;
            display: flex; align-items: center;
        }
        .gf-input-group:focus-within {
            border-color: var(--gf-primary);
            box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1);
        }
        .gf-input-group.money { background: #fffde7; border-color: var(--gf-gold); }
        
        input, select { 
            border: none; background: transparent; width: 100%; 
            font-size: 1rem; outline: none; color: #333; font-weight: 500;
        }
        label { color: #666; font-size: 0.8rem; font-weight: 700; margin-bottom: 5px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }

        /* BOTONES */
        .btn-plus { background: var(--gf-primary); color: white; border: none; width: 45px; height: 45px; border-radius: 10px; font-size: 1.4rem; transition: 0.2s; }
        .btn-trash { background: #ffebee; color: #d32f2f; border: 1px solid #ffcdd2; width: 40px; height: 40px; border-radius: 10px; }
        
        .btn-camera {
            border: 2px dashed #ccc; background: #f8f9fa; padding: 15px;
            border-radius: 12px; text-align: center; width: 100%;
            cursor: pointer; color: #666; font-weight: 600;
            margin-top: 10px; transition: 0.3s;
        }
        .btn-camera.has-photo { border-color: var(--gf-primary); background: #e8f5e9; color: var(--gf-primary); }

        .btn-add-tanda {
            width: 100%; background: var(--gf-primary); color: white;
            padding: 12px; border: none; border-radius: 10px;
            font-weight: 700; margin-top: 15px;
            box-shadow: 0 4px 10px rgba(27, 94, 32, 0.3);
        }

        .btn-save-main {
            width: 100%; padding: 18px;
            background: linear-gradient(135deg, var(--gf-gold) 0%, #f9a825 100%);
            color: #0f3d14; border: none; border-radius: 14px;
            font-size: 1.2rem; font-weight: 800;
            box-shadow: 0 10px 25px rgba(249, 168, 37, 0.4);
            margin-top: 20px; transition: 0.3s;
        }
        .btn-save-main:active { transform: scale(0.98); }

        /* ESTILOS DE ELEMENTOS (Categorías, Items, Tabla) */
        .badge-cat { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; color: white; font-weight: 700; }
        .cat1 { background: #2e7d32; }
        .cat2 { background: #fbc02d; color: black; }
        .rastrojo { background: #c62828; }

        .gps-dot { width: 12px; height: 12px; background: #999; border-radius: 50%; box-shadow: inset 0 0 2px rgba(0,0,0,0.5); }
        .gps-active { background: #00e676; box-shadow: 0 0 8px #00e676; }

        .origen-item { background: #f1f8e9; border: 1px solid #c8e6c9; border-radius: 10px; padding: 10px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; }
        .pesada-item { display: flex; align-items: center; border-bottom: 1px solid #eee; padding: 10px 0; }
        .pesada-thumb { width: 45px; height: 45px; border-radius: 8px; object-fit: cover; margin-right: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        
        .liqui-card { border: 1px solid #e0e0e0; border-radius: 10px; margin-bottom: 10px; overflow: hidden; background: white; }
        .liqui-head { background: #e3f2fd; padding: 8px 12px; font-weight: 700; font-size: 0.9rem; color: #1565c0; display:flex; justify-content:space-between; }
        .liqui-row { display: flex; align-items: center; padding: 8px 12px; border-bottom: 1px solid #f5f5f5; font-size: 0.9rem; }
        .mini-input { text-align: right; border-bottom: 2px solid var(--gf-gold); padding: 2px; font-weight: 700; width: 100%; }

    </style>
</head>
<body>

    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">×</a>
        
        <div style="text-align:center; padding: 30px 0 20px;">
             <img src="https://i.ibb.co/KzVLFpSV/Gemini-Generated-Image-45ambn45ambn45am-removebg-preview-2.png" alt="GoldFruits" style="width: 120px; height: auto; display: block; margin: 0 auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">
             
             <div style="color:var(--gf-gold); font-weight:bold; margin-top:15px; letter-spacing:1px;">
                <?php echo strtoupper($_SESSION['user_nombre']); ?>
             </div>
        </div>
        
        <a href="nuevo_acopio.php" style="background: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--gf-gold);">
            <i class="bi bi-plus-circle me-2"></i>Nueva Operación
        </a>
        <a href="mis_solicitudes.php"><i class="bi bi-folder2-open me-2"></i>Mis Solicitudes</a>
        <a href="ia_panel.php" style="color:var(--gf-gold);"><i class="bi bi-robot me-2"></i>Consultor IA</a>
        <a href="../logout.php" style="color:#ff8a80; margin-top:20px;"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a>
    </div>
    
    <div id="overlay" onclick="closeNav()"></div>

    <div class="app-bar">
        <span class="menu-btn" onclick="openNav()"><i class="bi bi-list"></i></span>
        <h1 class="page-title">Nuevo Acopio</h1>
        <div id="gpsStatus" class="gps-dot"></div>
    </div>

    <div id="netBanner" style="display:none; position:sticky; top:65px; z-index:900; background:#ffcdd2; color:#b71c1c; padding:10px 20px; font-weight:700; border-bottom:1px solid #e57373; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <i class="bi bi-wifi-off me-2"></i>Sin conexión: Se guardará offline.
        <span id="pendingCount" style="float:right; font-size:0.9rem; background:white; padding:2px 8px; border-radius:10px;"></span>
    </div>

    <div class="container mt-3">
        <form id="formAcopio">
            <input type="hidden" id="codigo_unico" name="codigo_unico">
            <input type="hidden" id="latitud" name="latitud">
            <input type="hidden" id="longitud" name="longitud">
            <input type="hidden" id="origenes_json" name="origenes_json"> 
            <input type="hidden" id="detalle_pesadas_json" name="detalle_pesadas_json">
            <input type="hidden" id="total_pagar_texto" name="total_pagar_texto">

            <div class="gf-card active" id="c1">
                <div class="gf-card-header" onclick="tgl('c1')">
                    <span><i class="bi bi-geo-alt-fill me-2"></i>1. Orígenes (Proveedores)</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="gf-card-body">
                    <div id="lista_origenes"></div>
                    
                    <div style="background: rgba(0,0,0,0.02); padding: 15px; border-radius: 12px; margin-top: 10px;">
                        <div class="row g-2">
                            <div class="col-8">
                                <div class="gf-input-group">
                                    <input type="text" id="tmp_prov" placeholder="Nombre Agricultor">
                                </div>
                                <div class="gf-input-group mb-0">
                                    <input type="text" id="tmp_campo" placeholder="Campo / Sector">
                                </div>
                            </div>
                            <div class="col-4">
                                <label style="font-size:0.7rem;">Tara Jaba</label>
                                <div class="gf-input-group mb-2">
                                    <select id="tmp_tara" style="font-weight:bold; color:var(--gf-primary);">
                                        <option value="1.6">1.6 kg</option>
                                        <option value="1.7">1.7 kg</option>
                                        <option value="1.8">1.8 kg</option>
                                    </select>
                                </div>
                                <button type="button" class="btn-plus w-100" onclick="addOrigen()"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </div>
                    </div>

                    <label class="mt-3">Cuenta Bancaria (General)</label>
                    <div class="gf-input-group">
                        <i class="bi bi-bank me-2 text-muted"></i>
                        <input type="tel" name="cuenta" placeholder="Para depósito general">
                    </div>
                </div>
            </div>

            <div class="gf-card" id="c2">
                <div class="gf-card-header" onclick="tgl('c2')">
                    <span><i class="bi bi-truck me-2"></i>2. Transporte</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="gf-card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label>Chofer</label>
                            <div class="gf-input-group">
                                <input type="text" name="conductor_nombre">
                            </div>
                        </div>
                        <div class="col-6">
                            <label>Placa</label>
                            <div class="gf-input-group">
                                <input type="text" name="vehiculo_placa" style="text-transform:uppercase;">
                            </div>
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-6">
                            <label>Flete (S/)</label>
                            <div class="gf-input-group money">
                                <input type="number" name="flete" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="text-danger">Adelanto</label>
                            <div class="gf-input-group" style="background:#ffebee; border-color:#ef5350;">
                                <input type="number" name="adelanto_flete" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="gf-card" id="c3">
                <div class="gf-card-header" onclick="tgl('c3')">
                    <span><i class="bi bi-basket-fill me-2"></i>3. Pesaje</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="gf-card-body">
                    <div style="background: #e3f2fd; padding: 15px; border-radius: 12px; border: 1px dashed #90caf9;">
                        <label style="color:#1565c0;">¿A quién pertenece?</label>
                        <div class="gf-input-group mb-1" style="border: 2px solid #1565c0;">
                            <select id="select_origen_pesada" onchange="mostrarTaraInfo()">
                                <option value="">-- Selecciona Origen --</option>
                            </select>
                        </div>
                        <div id="info_tara_actual" style="text-align:right; font-size:0.75rem; color:#d32f2f; font-weight:700; height:18px;"></div>

                        <label style="color:#2e7d32;">Calidad / Categoría</label>
                        <div class="gf-input-group" style="border: 2px solid #2e7d32;">
                            <select id="select_categoria">
                                <option value="cat1">🏆 Cat 1 - Grande</option>
                                <option value="cat2">🔸 Cat 1 - Chico</option>
                                <option value="rastrojo">❌ Rastrojo</option>
                            </select>
                        </div>

                        <div class="row g-2 mt-2">
                            <div class="col-6">
                                <div class="gf-input-group">
                                    <input type="number" id="tj" placeholder="Jabas">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="gf-input-group">
                                    <input type="number" id="tp" placeholder="Peso KG">
                                </div>
                            </div>
                        </div>

                        <label class="btn-camera" id="btn_temp_foto">
                            <i class="bi bi-camera-fill me-2"></i>Tomar Foto Evidencia
                            <input type="file" id="tf" accept="image/*" capture="environment" onchange="checkF()" style="display:none;">
                        </label>

                        <button type="button" class="btn-add-tanda" onclick="addP()">AGREGAR TANDA</button>
                    </div>

                    <div id="listP" class="mt-3"></div>

                    <div class="row mt-3 pt-3 border-top text-muted" style="font-size:0.9rem;">
                        <div class="col-6">Jabas: <b id="gtj">0</b></div>
                        <div class="col-6 text-end">Neto: <b id="gtp" style="color:var(--gf-primary); font-size:1.2rem;">0.00</b></div>
                    </div>
                </div>
            </div>

            <div class="gf-card" id="c4">
                <div class="gf-card-header" onclick="tgl('c4')">
                    <span><i class="bi bi-calculator-fill me-2"></i>4. Liquidación</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="gf-card-body" style="background:#fafafa;">
                    <p class="small text-muted mb-2">Establece precios (Se paga por Peso NETO):</p>
                    
                    <div id="liqui_container"></div>

                    <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 20px; border-radius: 12px; text-align: center; margin-top: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                        <small style="text-transform:uppercase; letter-spacing:1px; font-weight:700; color:#2e7d32;">Total General a Pagar</small><br>
                        <span id="txt_gran_total" style="font-size:2rem; font-weight:900; color:#1b5e20;">S/ 0.00</span>
                    </div>
                </div>
            </div>

            <div class="gf-card" id="c5">
                <div class="gf-card-header" onclick="tgl('c5')">
                    <span><i class="bi bi-people-fill me-2"></i>5. Personal</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="gf-card-body">
                    <div class="p-3 mb-3 bg-light rounded border">
                        <label>🚜 Cosecha</label>
                        <div class="row g-2">
                            <div class="col-4"><div class="gf-input-group mb-0"><input type="number" id="cp" name="cosecha_personas" placeholder="Pers" oninput="cP()"></div></div>
                            <div class="col-4"><div class="gf-input-group mb-0"><input type="number" id="cd" name="cosecha_dias" value="1" oninput="cP()"></div></div>
                            <div class="col-4"><div class="gf-input-group mb-0 money"><input type="number" id="cpr" name="cosecha_precio" placeholder="S/ Día" oninput="cP()"></div></div>
                        </div>
                        <div class="text-end mt-1"><b id="txt_sc" style="color:var(--gf-primary);">S/ 0.00</b><input type="hidden" id="sc" name="sub_cosecha" value="0"></div>
                    </div>

                    <div class="p-3 mb-3 bg-light rounded border">
                        <label>📦 Cargadores</label>
                        <div class="row g-2">
                            <div class="col-4"><div class="gf-input-group mb-0"><input type="number" id="cap" name="cargadores_personas" placeholder="Pers" oninput="cP()"></div></div>
                            <div class="col-4"><div class="gf-input-group mb-0"><input type="number" id="cad" name="cargadores_dias" value="1" oninput="cP()"></div></div>
                            <div class="col-4"><div class="gf-input-group mb-0 money"><input type="number" id="capr" name="cargadores_precio" placeholder="S/ Día" oninput="cP()"></div></div>
                        </div>
                        <div class="text-end mt-1"><b id="txt_sca" style="color:var(--gf-primary);">S/ 0.00</b><input type="hidden" id="sca" name="sub_cargadores" value="0"></div>
                    </div>

                    <div class="p-3 bg-light rounded border">
                        <label>🔍 Inspectores</label>
                        <div class="row g-2">
                            <div class="col-4"><div class="gf-input-group mb-0"><input type="number" id="ip" name="inspectores_personas" placeholder="Pers" oninput="cP()"></div></div>
                            <div class="col-4"><div class="gf-input-group mb-0"><input type="number" id="id" name="inspectores_dias" value="1" oninput="cP()"></div></div>
                            <div class="col-4"><div class="gf-input-group mb-0 money"><input type="number" id="ipr" name="inspectores_precio" placeholder="S/ Día" oninput="cP()"></div></div>
                        </div>
                        <div class="text-end mt-1"><b id="txt_si" style="color:var(--gf-primary);">S/ 0.00</b><input type="hidden" id="si" name="sub_inspectores" value="0"></div>
                    </div>
                </div>
            </div>

            <div class="gf-card" id="c6">
                <div class="gf-card-header" onclick="tgl('c6')">
                    <span><i class="bi bi-cash-stack me-2"></i>6. Otros Gastos</span>
                    <i class="bi bi-chevron-down"></i>
                </div>
                <div class="gf-card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label>Viáticos</label>
                            <div class="gf-input-group">
                                <input type="number" name="viaticos" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-6">
                            <label>Operativos</label>
                            <div class="gf-input-group">
                                <input type="number" name="operativos" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <button class="btn-save-main" id="btnG" onclick="send()">
            <i class="bi bi-cloud-arrow-up-fill me-2"></i>GUARDAR OPERACIÓN
        </button>
    </div>

<script>
    // --- ESTADO ---
    let origenes = [];
    let pesadas = [];

    // --- ORIGENES ---
    function addOrigen() {
        let p = document.getElementById('tmp_prov').value.trim();
        let c = document.getElementById('tmp_campo').value.trim();
        let t = parseFloat(document.getElementById('tmp_tara').value) || 1.6;

        if(p === "") { alert("Escribe nombre"); return; }
        
        origenes.push({ 
            proveedor: p, 
            campo: c,
            tara: t,
            precios: { p1:0, p2:0, pr:0 },
            kilos: { k1:0, k2:0, kr:0 }
        });

        document.getElementById('tmp_prov').value = ""; document.getElementById('tmp_campo').value = "";
        renderOrigenes(); 
        actualizarSelectPesaje();
        updateLiquidation(); 
    }

    function removeOrigen(idx) { 
        origenes.splice(idx, 1); 
        renderOrigenes(); 
        actualizarSelectPesaje(); 
        updateLiquidation();
    }

    function renderOrigenes() {
        let h = "";
        origenes.forEach((o, i) => {
            // Estilo actualizado
            h += `<div class="origen-item">
                    <div style="flex:1;">
                        <div style="font-weight:700; color:#1b5e20;">${o.proveedor}</div>
                        <div style="font-size:0.8rem; color:#666;">${o.campo}</div>
                        <span class="badge bg-danger text-white" style="font-size:0.65rem;">Tara: ${o.tara} kg</span>
                    </div>
                    <button type="button" class="btn-trash" onclick="removeOrigen(${i})"><i class="bi bi-trash"></i></button>
                  </div>`;
        });
        document.getElementById('lista_origenes').innerHTML = h;
    }

    function actualizarSelectPesaje() {
        let s = document.getElementById('select_origen_pesada'); s.innerHTML = "";
        if(origenes.length === 0) { 
            let o=document.createElement('option'); o.value=""; o.text="-- Falta Origen --"; s.add(o); 
        } else { 
            origenes.forEach((o, i) => { 
                let opt=document.createElement('option'); 
                let t=o.proveedor+(o.campo?" - "+o.campo:""); 
                opt.value=i; 
                opt.text=t; 
                s.add(opt); 
            }); 
        }
        mostrarTaraInfo();
    }

    function mostrarTaraInfo() {
        let idx = document.getElementById('select_origen_pesada').value;
        let info = document.getElementById('info_tara_actual');
        if(idx !== "" && origenes[idx]) {
            info.innerText = "Descuento Tara: " + origenes[idx].tara + " kg x jaba";
        } else {
            info.innerText = "";
        }
    }

    // --- PESAJE ---
    function checkF(){ if(document.getElementById('tf').files[0]) document.getElementById('btn_temp_foto').classList.add('has-photo'); }
    
    function addP(){
        let idx = document.getElementById('select_origen_pesada').value;
        if(idx === "") { alert("Agrega origen primero"); tgl('c1'); return; }
        
        let j = parseFloat(document.getElementById('tj').value), 
            pBruto = parseFloat(document.getElementById('tp').value), 
            f = document.getElementById('tf').files[0];
            
        if(!j||!pBruto||!f) return alert("Faltan datos (Jabas, Peso o Foto)");
        
        let taraUnit = origenes[idx].tara;
        let descuento = j * taraUnit;
        let pNeto = pBruto - descuento;
        
        if(pNeto < 0) pNeto = 0; 

        let cat = document.getElementById('select_categoria').value; 
        let provName = origenes[idx].proveedor; 

        pesadas.push({
            idx: parseInt(idx),
            j: j, 
            pBruto: pBruto,
            pNeto: pNeto,
            tara: taraUnit,
            f: f, 
            u: URL.createObjectURL(f), 
            origen: provName, 
            cat: cat
        });

        document.getElementById('tj').value=""; document.getElementById('tp').value=""; document.getElementById('tf').value="";
        document.getElementById('btn_temp_foto').classList.remove('has-photo');
        
        renderP();
        updateLiquidation(); 
    }

    function renderP(){
        let h="", gtj=0, gtpNeto=0;
        pesadas.forEach((x,i)=>{ 
            gtj+=x.j; gtpNeto+=x.pNeto;
            let badgeClass = x.cat === 'cat1' ? 'cat1' : (x.cat === 'cat2' ? 'cat2' : 'rastrojo');
            let badgeText = x.cat === 'cat1' ? 'C1-Gde' : (x.cat === 'cat2' ? 'C1-Chico' : 'Rastrojo');

            h+=`<div class="pesada-item">
                    <img src="${x.u}" class="pesada-thumb">
                    <div style="flex:1;">
                        <div style="font-size:0.85rem; font-weight:700; color:#1b5e20;">${x.origen} <span class="badge-cat ${badgeClass}">${badgeText}</span></div>
                        <div style="font-size:0.8rem; color:#555;">
                            <b>${x.j} jb</b> | Bruto: ${x.pBruto} | <b style="color:#d32f2f">Neto: ${x.pNeto.toFixed(2)}</b>
                        </div>
                    </div>
                </div>`;
        });
        document.getElementById('listP').innerHTML=h;
        document.getElementById('gtj').innerText=gtj; 
        document.getElementById('gtp').innerText=gtpNeto.toFixed(2);
    }

    // --- LIQUIDACIÓN ---
    function updateLiquidation() {
        origenes.forEach(o => { o.kilos = {k1:0, k2:0, kr:0}; });

        pesadas.forEach(p => {
            if(origenes[p.idx]) {
                if(p.cat==='cat1') origenes[p.idx].kilos.k1 += p.pNeto;
                else if(p.cat==='cat2') origenes[p.idx].kilos.k2 += p.pNeto;
                else origenes[p.idx].kilos.kr += p.pNeto;
            }
        });

        let container = document.getElementById('liqui_container');
        container.innerHTML = "";
        let granTotal = 0;

        origenes.forEach((o, i) => {
            let sub = (o.kilos.k1 * o.precios.p1) + (o.kilos.k2 * o.precios.p2) + (o.kilos.kr * o.precios.pr);
            granTotal += sub;

            container.innerHTML += `
            <div class="liqui-card">
                <div class="liqui-head">
                    <span>${o.proveedor}</span>
                    <span style="font-size:0.75rem; color:#666;">Tara: ${o.tara}</span>
                </div>
                
                <div class="liqui-row">
                    <span class="badge-cat cat1 me-2">C1</span> 
                    <div style="flex:1">${o.kilos.k1.toFixed(2)} kg</div>
                    <div style="width:100px;">
                        S/ <input type="number" class="mini-input" value="${o.precios.p1||''}" 
                        oninput="updP(${i}, 'p1', this.value)" placeholder="0.00">
                    </div>
                </div>
                
                <div class="liqui-row">
                    <span class="badge-cat cat2 me-2">C2</span> 
                    <div style="flex:1">${o.kilos.k2.toFixed(2)} kg</div>
                    <div style="width:100px;">
                        S/ <input type="number" class="mini-input" value="${o.precios.p2||''}" 
                        oninput="updP(${i}, 'p2', this.value)" placeholder="0.00">
                    </div>
                </div>

                <div class="liqui-row">
                    <span class="badge-cat rastrojo me-2">RZ</span> 
                    <div style="flex:1">${o.kilos.kr.toFixed(2)} kg</div>
                    <div style="width:100px;">
                        S/ <input type="number" class="mini-input" value="${o.precios.pr||''}" 
                        oninput="updP(${i}, 'pr', this.value)" placeholder="0.00">
                    </div>
                </div>

                <div class="liqui-row bg-light" style="justify-content:space-between; font-weight:800;">
                    <span>A Pagar:</span>
                    <span id="sub_txt_${i}" style="color:#1b5e20;">S/ ${sub.toFixed(2)}</span>
                </div>
            </div>`;
        });

        document.getElementById('txt_gran_total').innerText = "S/ " + granTotal.toFixed(2);
        document.getElementById('total_pagar_texto').value = granTotal.toFixed(2);
        document.getElementById('origenes_json').value = JSON.stringify(origenes);
    }

    function updP(idx, tipo, val) {
        origenes[idx].precios[tipo] = parseFloat(val) || 0;
        let granTotal = 0;
        origenes.forEach((o, i) => {
            let sub = (o.kilos.k1 * o.precios.p1) + (o.kilos.k2 * o.precios.p2) + (o.kilos.kr * o.precios.pr);
            granTotal += sub;
            let el = document.getElementById('sub_txt_' + i);
            if(el) el.innerText = "S/ " + sub.toFixed(2);
        });
        document.getElementById('txt_gran_total').innerText = "S/ " + granTotal.toFixed(2);
        document.getElementById('total_pagar_texto').value = granTotal.toFixed(2);
        document.getElementById('origenes_json').value = JSON.stringify(origenes);
    }

    // --- PERSONAL ---
    function cP(){
        ['cosecha','cargadores','inspectores'].forEach(k=>{
            let p=parseFloat(document.getElementById(k+'_personas').value)||0;
            let d=parseFloat(document.getElementById(k+'_dias').value)||0;
            let m=parseFloat(document.getElementById(k+'_precio').value)||0;
            let t=(p*d*m).toFixed(2);
            document.getElementById(k=='cosecha'?'txt_sc':(k=='cargadores'?'txt_sca':'txt_si')).innerText="S/ "+t;
            document.getElementById(k=='cosecha'?'sc':(k=='cargadores'?'sca':'si')).value=t;
        });
    }

    // --- UI HELPERS ---
    function tgl(id){ 
        document.querySelectorAll('.gf-card').forEach(c=>c.classList.remove('active')); 
        document.getElementById(id).classList.add('active'); 
    }
    
    function openNav(){ document.getElementById("mySidebar").style.width="280px"; document.getElementById("overlay").style.display="block"; }
    function closeNav(){ document.getElementById("mySidebar").style.width="0"; document.getElementById("overlay").style.display="none"; }
    
    window.onload=()=>{ document.getElementById('codigo_unico').value='GF-'+Date.now(); forceGPS(); }
    
    function forceGPS(){
        navigator.geolocation.getCurrentPosition(p=>{
            document.getElementById('latitud').value=p.coords.latitude; document.getElementById('longitud').value=p.coords.longitude;
            document.getElementById('gpsStatus').className="gps-dot gps-active";
        }, e=>{ alert("⚠️ POR FAVOR ACTIVA TU GPS"); });
    }

    async function send(){
        let btn=document.getElementById('btnG');
        if(!document.getElementById('latitud').value && !confirm("Sin GPS. ¿Seguir?")) return;
        if(origenes.length===0){ alert("Falta Proveedor"); tgl('c1'); return; }
        
        btn.disabled=true; btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Guardando...';
        
        document.getElementById('origenes_json').value = JSON.stringify(origenes);

        let pesadasSimple = pesadas.map(x => ({
            jabas: x.j, 
            peso: x.pNeto, 
            peso_bruto: x.pBruto, 
            origen: x.origen, 
            categoria: x.cat
        }));
        
        let fd=new FormData(document.getElementById('formAcopio'));
        fd.append('detalle_pesadas_json', JSON.stringify(pesadasSimple));
        
        pesadas.forEach((x,i)=>fd.append('fotos_pesadas[]', x.f));

        if (!navigator.onLine) {
            try {
                const { local_id } = await window.GF_OFFLINE.queueAcopioFromCurrentForm(document.getElementById('formAcopio'), pesadas);
                alert("✅ Guardado OFFLINE (ID: " + local_id + "). Se enviará al volver internet.");
                window.location.reload(); return;
            } catch (e) { alert("Error offline: " + e); btn.disabled=false; return; }
        }
        
        try {
            const r = await fetch('guardar_goldfruits.php', {method:'POST', body:fd});
            const d = await r.text();
            if (!r.ok || /^error\s*:/i.test((d||'').trim())) throw new Error(d || ('HTTP '+r.status));
            alert(d); window.location.reload();
        } catch(e){
            try {
                const { local_id } = await window.GF_OFFLINE.queueAcopioFromCurrentForm(document.getElementById('formAcopio'), pesadas);
                alert("⚠️ Error red. Guardado OFFLINE (ID: " + local_id + ")");
                window.location.reload();
            } catch (err) { alert("Error fatal: " + err); btn.disabled=false; }
        }
    }

    // UI de Red
    function ensureNetBanner(){ let banner = document.getElementById('netBanner'); if (!banner) return; return banner; }
    async function refreshNetUI(){
        const banner = document.getElementById('netBanner');
        const pending = document.getElementById('pendingCount');
        try {
            const items = await window.GF_OFFLINE.listQueue();
            if (pending) pending.innerText = items.length ? ('Pendientes: ' + items.length) : '';
        } catch (e) {}
        banner.style.display = navigator.onLine ? 'none' : 'block';
    }
    window.addEventListener('online', async () => {
        await refreshNetUI();
        try { const r = await window.GF_OFFLINE.syncQueue(); if (r && r.synced) alert('📡 Sincronizado: ' + r.synced + ' envíos.'); } catch (e) {}
    });
    window.addEventListener('offline', refreshNetUI);
    (async () => { await refreshNetUI(); if (navigator.onLine) { try { await window.GF_OFFLINE.syncQueue(); } catch (e) {} } })();
</script>
</body>
</html>