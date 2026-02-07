<?php
// editar_solicitud.php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

// 1. Validar ID y Permisos
if (!isset($_GET['id'])) {
    die("❌ Error: Falta el ID de la solicitud.");
}

$id  = (int)$_GET['id'];
$uid = (int)$_SESSION['user_id'];

// 2. Obtener Cabecera
$sql = "SELECT * FROM acopios_cabecera WHERE id = ? AND usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id, $uid]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("❌ Error: Solicitud no encontrada o no tienes permiso para editarla.");
}

// 3. Obtener Pesadas (Tandas) existentes
$stmtDet = $conn->prepare("SELECT * FROM acopios_pesadas WHERE acopio_id = ? ORDER BY numero_tanda ASC");
$stmtDet->execute([$id]);
$pesadas = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

// 4. Obtener Orígenes (Proveedores) guardados
$stmtOri = $conn->prepare("
    SELECT 
        ao.id as origen_id, 
        p.id as proveedor_id, 
        p.nombre as proveedor, 
        ao.campo, 
        p.cuenta_bancaria,
        ao.p_cat1, ao.p_cat2, ao.p_rastrojo,
        ao.tara_asignada
    FROM acopios_origenes ao
    JOIN proveedores p ON p.id = ao.proveedor_id
    WHERE ao.acopio_id = ?
    ORDER BY ao.id ASC
");
$stmtOri->execute([$id]);
$origenes_db = $stmtOri->fetchAll(PDO::FETCH_ASSOC);

// JSON para JS
$pesadas_json = json_encode($pesadas ?: [], JSON_UNESCAPED_UNICODE);

// Estructurar orígenes para JS
if (!empty($origenes_db)) {
    $tmp = [];
    foreach ($origenes_db as $r) {
        $tmp[] = [
            "proveedor" => $r["proveedor"],
            "campo" => $r["campo"] ?? "",
            "cuenta" => $r["cuenta_bancaria"] ?? "",
            "proveedor_id" => (int)$r["proveedor_id"],
            "origen_id" => (int)$r["origen_id"],
            "tara" => (float)($r['tara_asignada'] ?? 1.6),
            "precios" => [
                "p1" => (float)($r['p_cat1'] ?? 0),
                "p2" => (float)($r['p_cat2'] ?? 0),
                "pr" => (float)($r['p_rastrojo'] ?? 0)
            ],
            "kilos" => ["k1"=>0, "k2"=>0, "kr"=>0]
        ];
    }
    $origenes_json = json_encode($tmp, JSON_UNESCAPED_UNICODE);
} else {
    // Fallback (json guardado en cabecera)
    $origenes_json = ($data['origenes_detalle'] && trim($data['origenes_detalle']) !== '') ? $data['origenes_detalle'] : '[]';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0f3d14">
    <title>Editar | <?php echo htmlspecialchars($data['codigo_unico']); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
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
            background-image: 
                radial-gradient(at 0% 0%, rgba(251, 192, 45, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(27, 94, 32, 0.2) 0px, transparent 50%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            color: #333;
            padding-bottom: 100px;
            min-height: 100vh;
        }

        .app-bar {
            background: rgba(27, 94, 32, 0.9);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 20px;
            position: sticky; top: 0; z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .page-title { font-weight: 700; font-size: 1.1rem; margin: 0; }

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

        .gf-card {
            background: var(--gf-glass);
            border: 1px solid var(--gf-glass-border);
            border-radius: 16px;
            margin-bottom: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            overflow: hidden; transition: all 0.3s ease;
        }
        .gf-card.active {
            border: 2px solid var(--gf-gold);
            transform: scale(1.01);
            box-shadow: 0 10px 40px rgba(251, 192, 45, 0.15);
        }
        .gf-card-header {
            padding: 18px 20px; font-weight: 700; cursor: pointer;
            background: rgba(255,255,255,0.5); color: var(--gf-primary);
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .gf-card.active .gf-card-header {
            background: linear-gradient(90deg, rgba(251, 192, 45, 0.1) 0%, transparent 100%);
            color: #000;
        }
        .gf-card-body { display: none; padding: 20px; }
        .gf-card.active .gf-card-body { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .gf-input-group {
            background: white; border: 1px solid #e0e0e0; border-radius: 10px;
            padding: 8px 12px; margin-bottom: 10px; transition: 0.3s;
            display: flex; align-items: center;
        }
        .gf-input-group:focus-within { border-color: var(--gf-primary); box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1); }
        .gf-input-group.money { background: #fffde7; border-color: var(--gf-gold); }

        input, select { border: none; background: transparent; width: 100%; font-size: 1rem; outline: none; color: #333; font-weight: 500; }
        label { color: #666; font-size: 0.8rem; font-weight: 700; margin-bottom: 5px; display: block; text-transform: uppercase; letter-spacing: 0.5px; }

        .origen-item { background: #f1f8e9; border: 1px solid #c8e6c9; border-radius: 10px; padding: 10px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between; }
        .pesada-item { display: flex; align-items: center; border-bottom: 1px solid #eee; padding: 10px 0; }
        .pesada-thumb { width: 45px; height: 45px; border-radius: 8px; object-fit: cover; margin-right: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        .btn-plus { background: var(--gf-primary); color: white; border: none; width: 45px; height: 45px; border-radius: 10px; font-size: 1.4rem; }
        .btn-trash { background: #ffebee; color: #d32f2f; border: 1px solid #ffcdd2; width: 40px; height: 40px; border-radius: 10px; }

        .btn-camera {
            border: 2px dashed #ccc; background: #f8f9fa; padding: 15px;
            border-radius: 12px; text-align: center; width: 100%;
            cursor: pointer; color: #666; font-weight: 600;
            margin-top: 10px; transition: 0.3s;
            user-select: none;
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
        .btn-save-main:disabled { opacity: 0.7; filter: grayscale(1); cursor: not-allowed; }

        .badge-cat { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; color: white; font-weight: 700; }
        .cat1 { background: #2e7d32; }
        .cat2 { background: #fbc02d; color: black; }
        .rastrojo { background: #c62828; }

        /* Liquidación */
        .liqui-card { background: #fff; border: 1px solid #eee; border-radius: 12px; padding: 12px; margin-bottom: 10px; }
        .liqui-head { display:flex; justify-content:space-between; font-weight:800; margin-bottom:8px; color:#1b5e20; }
        .liqui-row { display:flex; align-items:center; gap:10px; padding:6px 0; border-top:1px dashed #eee; }
        .mini-input { width: 70px; border: 1px solid #ddd; border-radius: 8px; padding: 3px 6px; font-weight:700; }
    </style>

    <!-- ✅ Inyección segura: NO se rompe JSON -->
    <script>
      const ORIGENES_INI = <?php echo $origenes_json ?: '[]'; ?>;
      const PESADAS_INI  = <?php echo $pesadas_json ?: '[]'; ?>;
    </script>
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
    <a href="nuevo_acopio.php"><i class="bi bi-plus-circle me-2"></i>Nueva Operación</a>
    <a href="mis_solicitudes.php"><i class="bi bi-folder2-open me-2"></i>Mis Solicitudes</a>
    <a href="ia_panel.php" style="color:var(--gf-gold);"><i class="bi bi-robot me-2"></i>Consultor IA</a>
    <a href="../logout.php" style="color:#ff8a80; margin-top:20px;"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a>
</div>
<div id="overlay" onclick="closeNav()"></div>

<div class="app-bar">
    <span onclick="openNav()" style="font-size:1.6rem; cursor:pointer; color:var(--gf-gold);"><i class="bi bi-list"></i></span>
    <h1 class="page-title">Editar Operación</h1>
    <a href="mis_solicitudes.php" style="color:white; font-size:1.6rem;"><i class="bi bi-x"></i></a>
</div>

<div class="container mt-3">
    <form id="formAcopio" enctype="multipart/form-data">
        <input type="hidden" name="id_acopio" value="<?php echo (int)$data['id']; ?>">
        <input type="hidden" name="codigo_unico" value="<?php echo htmlspecialchars($data['codigo_unico']); ?>">

        <!-- Se usan para enviar al backend al guardar -->
        <input type="hidden" id="origenes_json" name="origenes_json" value="">
        <input type="hidden" id="detalle_pesadas_json" name="detalle_pesadas_json">

        <input type="hidden" id="total_fruta" name="total_fruta" value="<?php echo htmlspecialchars($data['total_kilos_neto']); ?>">
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
                                <input type="text" id="tmp_prov" placeholder="Nuevo Agricultor">
                            </div>
                            <div class="gf-input-group mb-0">
                                <input type="text" id="tmp_campo" placeholder="Campo / Sector">
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="gf-input-group mb-2">
                                <select id="tmp_tara" style="font-weight:bold; color:var(--gf-primary); font-size:0.8rem;">
                                    <option value="1.6">Tara 1.6</option>
                                    <option value="1.7">Tara 1.7</option>
                                    <option value="1.8">Tara 1.8</option>
                                </select>
                            </div>
                            <button type="button" class="btn-plus w-100" onclick="addOrigen()"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </div>
                </div>

                <label class="mt-3">Cuenta Bancaria (General)</label>
                <div class="gf-input-group">
                    <i class="bi bi-bank me-2 text-muted"></i>
                    <input type="text" name="cuenta" value="<?php echo htmlspecialchars($data['cuenta_bancaria']); ?>" placeholder="Banco - Nro Cuenta">
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
                        <div class="gf-input-group"><input type="text" name="conductor_nombre" value="<?php echo htmlspecialchars($data['conductor']); ?>"></div>
                    </div>
                    <div class="col-6">
                        <label>Placa</label>
                        <div class="gf-input-group"><input type="text" name="vehiculo_placa" value="<?php echo htmlspecialchars($data['placa']); ?>"></div>
                    </div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-6">
                        <label>Flete (S/)</label>
                        <div class="gf-input-group money"><input type="number" name="flete" value="<?php echo htmlspecialchars($data['precio_flete']); ?>"></div>
                    </div>
                    <div class="col-6">
                        <label class="text-danger">Adelanto</label>
                        <div class="gf-input-group" style="background:#ffebee; border-color:#ef5350;"><input type="number" name="adelanto_flete" value="<?php echo htmlspecialchars($data['adelanto_flete']); ?>"></div>
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
                    <label style="color:#1565c0; font-weight:800;">Origen de esta tanda:</label>
                    <div class="gf-input-group mb-1" style="border: 2px solid #1565c0;">
                        <select id="select_origen_pesada" onchange="mostrarTaraInfo()" style="font-weight:700; color:#0d47a1;"></select>
                    </div>
                    <div id="info_tara_actual" style="text-align:right; font-size:0.75rem; color:#d32f2f; font-weight:700; height:18px; margin-bottom:10px;"></div>

                    <label style="color:#2e7d32;">Categoría:</label>
                    <div class="gf-input-group" style="border: 2px solid #2e7d32;">
                        <select id="select_categoria" style="font-weight:700; color:#1b5e20;">
                            <option value="cat1">🏆 Cat 1 - Grande</option>
                            <option value="cat2">🔸 Cat 1 - Chico</option>
                            <option value="rastrojo">❌ Rastrojo</option>
                        </select>
                    </div>

                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <label>N° Jabas</label>
                            <div class="gf-input-group"><input type="number" id="temp_jabas" placeholder="0" style="font-size:1.2rem; text-align:center;"></div>
                        </div>
                        <div class="col-6">
                            <label>Peso KG</label>
                            <div class="gf-input-group"><input type="number" step="0.01" id="temp_peso" placeholder="0.00" style="font-size:1.2rem; text-align:center;"></div>
                        </div>
                    </div>

                    <!-- ✅ FIX FOTO: el input YA NO está dentro del label (no se borra nunca) -->
                    <label class="btn-camera" id="btn_temp_foto" for="temp_foto_input">
                        <span id="txt_temp_foto"><i class="bi bi-camera-fill me-2"></i>Tomar Foto Evidencia</span>
                    </label>
                    <input type="file" id="temp_foto_input" accept="image/*" capture="environment" onchange="checkTempPhoto()" hidden>

                    <button type="button" class="btn-add-tanda" onclick="agregarPesada()">
                        <i class="bi bi-plus-circle-fill me-2"></i>AGREGAR TANDA
                    </button>
                </div>

                <div id="lista_pesadas_container" class="mt-3"></div>

                <div class="row mt-3 pt-3 border-top text-muted" style="font-size:0.9rem;">
                    <div class="col-6">Jabas: <b id="gtj">0</b></div>
                    <div class="col-6 text-end">Neto Total: <b id="gtp" style="color:var(--gf-primary); font-size:1.2rem;">0.00</b></div>
                </div>
            </div>
        </div>

        <div class="gf-card" id="c4">
            <div class="gf-card-header" onclick="tgl('c4')">
                <span><i class="bi bi-calculator-fill me-2"></i>4. Liquidación</span>
                <i class="bi bi-chevron-down"></i>
            </div>
            <div class="gf-card-body" style="background:#fafafa;">
                <p class="small text-muted mb-2">Precios por proveedor (Neto):</p>
                <div id="liqui_container"></div>
                <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 20px; border-radius: 12px; text-align: center; margin-top: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <small style="text-transform:uppercase; letter-spacing:1px; font-weight:700; color:#2e7d32;">Total a Pagar</small><br>
                    <span id="txt_total_pagar" style="font-size:2rem; font-weight:900; color:#1b5e20;">S/ 0.00</span>
                </div>
            </div>
        </div>

        <div class="gf-card" id="c5">
            <div class="gf-card-header" onclick="tgl('c5')">
                <span><i class="bi bi-people-fill me-2"></i>5. Personal</span>
                <i class="bi bi-chevron-down"></i>
            </div>
            <div class="gf-card-body">
                <?php
                    $personal = [
                        ['id'=>'cosecha', 'label'=>'🚜 Cosecha'],
                        ['id'=>'cargadores', 'label'=>'📦 Cargadores'],
                        ['id'=>'inspectores', 'label'=>'🔍 Inspectores']
                    ];
                    foreach ($personal as $p) {
                        $k = $p['id'];
                        echo "
                        <div class='p-3 mb-3 bg-light rounded border'>
                            <label>{$p['label']}</label>
                            <div class='row g-2'>
                                <div class='col-4'><div class='gf-input-group mb-0'><input type='number' id='{$k}_personas' name='{$k}_personas' value='".htmlspecialchars($data[$k.'_personas'])."' oninput='calcPersonal()' placeholder='Pers'></div></div>
                                <div class='col-4'><div class='gf-input-group mb-0'><input type='number' id='{$k}_dias' name='{$k}_dias' value='".htmlspecialchars($data[$k.'_dias'])."' oninput='calcPersonal()' placeholder='Días'></div></div>
                                <div class='col-4'><div class='gf-input-group mb-0 money'><input type='number' id='{$k}_precio' name='{$k}_precio' value='".htmlspecialchars($data[$k.'_precio'])."' oninput='calcPersonal()' placeholder='Precio'></div></div>
                            </div>
                            <div class='text-end mt-1'>
                                <div class='gf-input-group d-inline-block w-auto mb-0' style='background:transparent; border:none; padding:0;'>
                                    <input type='text' id='sub_{$k}' name='subtotal_{$k}' readonly value='".htmlspecialchars($data['subtotal_'.$k])."' style='text-align:right; font-weight:bold; color:var(--gf-primary);'>
                                </div>
                            </div>
                        </div>";
                    }
                ?>
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
                        <div class="gf-input-group"><input type="number" name="viaticos" value="<?php echo htmlspecialchars($data['viaticos']); ?>"></div>
                    </div>
                    <div class="col-6">
                        <label>Gastos Op.</label>
                        <div class="gf-input-group"><input type="number" name="gastos_operativos" value="<?php echo htmlspecialchars($data['gastos_operativos']); ?>"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="d-grid gap-2 mt-4 mb-5">
        <button type="button" class="btn-save-main" onclick="guardarTodo()">
            <i class="bi bi-cloud-arrow-up-fill me-2"></i>GUARDAR CAMBIOS
        </button>
    </div>
</div>

<script>
// --- DATOS INICIALES (NO SE ROMPE EL JSON) ---
let origenes = Array.isArray(ORIGENES_INI) ? ORIGENES_INI : [];
origenes = origenes.map(o => {
    if(!o.precios) o.precios = {p1:0, p2:0, pr:0};
    if(!o.kilos)   o.kilos   = {k1:0, k2:0, kr:0};
    if(!o.tara)    o.tara    = 1.6;
    return o;
});

let pesadas = Array.isArray(PESADAS_INI) ? PESADAS_INI : [];
pesadas = pesadas.map(p => {
    let neto  = parseFloat(p.peso) || 0;
    let bruto = (p.peso_bruto) ? parseFloat(p.peso_bruto) : neto;
    return {
        jabas: parseFloat(p.jabas) || 0,
        pNeto: neto,
        pBruto: bruto,
        foto_url: p.foto_url || null,
        file: null,
        preview: p.foto_url ? ('../user/' + p.foto_url) : null,
        origen_id: (p.origen_id) ? parseInt(p.origen_id, 10) : null,
        origen: p.origen_referencia || p.origen || "",
        origen_idx: -1,
        categoria: p.categoria || "cat1",
        es_nueva_fila: false
    };
});

function inferirOrigenIdxParaPesadas() {
    pesadas.forEach(p => {
        let idx = -1;
        if (p.origen_id) idx = origenes.findIndex(o => (o.origen_id && o.origen_id == p.origen_id));
        if (idx === -1 && p.origen) idx = origenes.findIndex(o => (o.proveedor || "") === p.origen);
        p.origen_idx = idx;
    });
}

// --- HELPERS UI ---
function tgl(id) {
    document.querySelectorAll('.gf-card').forEach(c => c.classList.remove('active'));
    document.getElementById(id).classList.add('active');
}
function openNav(){ document.getElementById("mySidebar").style.width="280px"; document.getElementById("overlay").style.display="block"; }
function closeNav(){ document.getElementById("mySidebar").style.width="0"; document.getElementById("overlay").style.display="none"; }
function esc(str) { if(!str) return ""; return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }

// --- ORIGENES ---
function renderOrigenes() {
    const div = document.getElementById('lista_origenes');
    div.innerHTML = "";
    origenes.forEach((o, i) => {
        div.innerHTML += `
        <div class="origen-item animate__animated animate__fadeIn">
            <div style="flex:1;">
                <div style="font-weight:700; color:#1b5e20;">${esc(o.proveedor)}</div>
                <div style="font-size:0.8rem; color:#666;">${esc(o.campo || "")}</div>
                <span class="badge bg-danger text-white" style="font-size:0.65rem;">Tara: ${parseFloat(o.tara||1.6).toFixed(2)} kg</span>
            </div>
            <button type="button" class="btn-trash" onclick="delOrigen(${i})"><i class="bi bi-trash"></i></button>
        </div>`;
    });

    actualizarSelects();
    document.getElementById('origenes_json').value = JSON.stringify(origenes);
}

function addOrigen() {
    const p = document.getElementById('tmp_prov').value.trim();
    const c = document.getElementById('tmp_campo').value.trim();
    const t = parseFloat(document.getElementById('tmp_tara').value) || 1.6;

    if(!p) return alert("Ingresa nombre del proveedor");

    origenes.push({
        proveedor: p,
        campo: c,
        tara: t,
        proveedor_id: null,
        origen_id: null,
        precios: {p1:0, p2:0, pr:0},
        kilos: {k1:0, k2:0, kr:0}
    });

    document.getElementById('tmp_prov').value = "";
    document.getElementById('tmp_campo').value = "";
    renderOrigenes();
    updateLiquidation();
}

function delOrigen(i) {
    if(confirm('¿Borrar este origen?')) {
        pesadas = pesadas.filter(p => p.origen_idx !== i);
        pesadas.forEach(p => { if (p.origen_idx > i) p.origen_idx--; });

        origenes.splice(i, 1);
        renderOrigenes();
        renderPesadas();
        updateLiquidation();
    }
}

function actualizarSelects() {
    const sel = document.getElementById('select_origen_pesada');
    if(!sel) return;
    sel.innerHTML = "";
    sel.add(new Option("-- Selecciona Origen --", ""));
    origenes.forEach((o, idx) => {
        const label = (o.proveedor || "Sin nombre") + (o.campo ? " - " + o.campo : "");
        sel.add(new Option(label, String(idx)));
    });
    mostrarTaraInfo();
}

function mostrarTaraInfo() {
    const sel = document.getElementById('select_origen_pesada');
    const info = document.getElementById('info_tara_actual');
    if(!sel || !info) return;

    const idxStr = sel.value;
    if(idxStr !== "" && origenes[parseInt(idxStr,10)]) {
        info.innerText = "Descuento Tara: " + (parseFloat(origenes[parseInt(idxStr,10)].tara)||1.6) + " kg x jaba";
    } else {
        info.innerText = "";
    }
}

// --- FOTO (FIX: no borra input) ---
function checkTempPhoto() {
    const f = document.getElementById('temp_foto_input');
    const b = document.getElementById('btn_temp_foto');
    const t = document.getElementById('txt_temp_foto');
    if(!f || !b || !t) return;

    if(f.files && f.files[0]) {
        b.classList.add('has-photo');
        b.style.borderColor = "#1b5e20";
        b.style.backgroundColor = "#e8f5e9";
        b.style.color = "#1b5e20";
        t.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Foto Capturada';
    } else {
        b.classList.remove('has-photo');
        b.style.borderColor = "#ccc";
        b.style.backgroundColor = "#f8f9fa";
        b.style.color = "#666";
        t.innerHTML = '<i class="bi bi-camera-fill me-2"></i>Tomar Foto Evidencia';
    }
}

// --- PESADAS ---
function agregarPesada() {
    try {
        const selectOrigen = document.getElementById('select_origen_pesada');
        if(!selectOrigen) return alert("❌ Error: no existe select_origen_pesada");

        const idxStr = selectOrigen.value;
        if(idxStr === "" || idxStr === null) {
            tgl('c1');
            return alert("⚠️ Error: Selecciona un Origen (Proveedor) válido.");
        }

        const origen_idx = parseInt(idxStr, 10);
        const o = origenes[origen_idx];
        if(!o) {
            tgl('c1');
            return alert("⚠️ Error: Origen inválido.");
        }

        const j = parseFloat(document.getElementById('temp_jabas').value);
        const pBruto = parseFloat(document.getElementById('temp_peso').value);
        const cat = document.getElementById('select_categoria').value;

        const fileInp = document.getElementById('temp_foto_input');
        if(!fileInp) return alert("❌ Error: no se encontró el input de foto (temp_foto_input).");

        if (isNaN(j) || j <= 0) return alert("⚠️ Cantidad de Jabas inválida.");
        if (isNaN(pBruto) || pBruto <= 0) return alert("⚠️ Peso inválido.");

        // Foto obligatoria
        if (!fileInp.files || !fileInp.files[0]) {
            return alert("📸 LA FOTO ES OBLIGATORIA.");
        }

        const taraUnit = parseFloat(o.tara) || 1.6;
        let pNeto = pBruto - (j * taraUnit);
        if(pNeto < 0) pNeto = 0;

        const file = fileInp.files[0];

        pesadas.push({
            jabas: j,
            pBruto: pBruto,
            pNeto: pNeto,
            categoria: cat,
            origen_idx: origen_idx,
            origen_id: o.origen_id || null,
            origen: o.proveedor || "",
            file: file,
            preview: URL.createObjectURL(file),
            foto_url: null,
            es_nueva_fila: true
        });

        // Reset inputs
        document.getElementById('temp_jabas').value = "";
        document.getElementById('temp_peso').value = "";
        fileInp.value = "";

        // Reset UI foto
        const btn = document.getElementById('btn_temp_foto');
        const txt = document.getElementById('txt_temp_foto');
        if(btn) {
            btn.classList.remove('has-photo');
            btn.style.borderColor = "#ccc";
            btn.style.backgroundColor = "#f8f9fa";
            btn.style.color = "#666";
        }
        if(txt) txt.innerHTML = '<i class="bi bi-camera-fill me-2"></i>Tomar Foto Evidencia';

        renderPesadas();
        updateLiquidation();
        tgl('c3');

    } catch (e) {
        console.error(e);
        alert("❌ Error interno: " + e.message);
    }
}

function delPesada(i) {
    if(confirm("¿Eliminar esta tanda?")) {
        pesadas.splice(i, 1);
        renderPesadas();
        updateLiquidation();
    }
}

function renderPesadas() {
    const c = document.getElementById('lista_pesadas_container');
    c.innerHTML = "";

    let tJabas = 0;
    let tPesoNeto = 0;

    pesadas.forEach((p, i) => {
        tJabas += (parseFloat(p.jabas)||0);
        tPesoNeto += (parseFloat(p.pNeto)||0);

        let imgSrc = 'placeholder.png';
        if (p.preview) imgSrc = p.preview;
        else if (p.foto_url) imgSrc = '../user/' + p.foto_url;

        let badgeClass = 'rastrojo';
        let badgeText = 'Rastrojo';
        if (p.categoria === 'cat1') { badgeClass = 'cat1'; badgeText = 'C1-Gde'; }
        else if (p.categoria === 'cat2') { badgeClass = 'cat2'; badgeText = 'C1-Chico'; }

        let nombreProv = p.origen || "";
        if ((!nombreProv || nombreProv.trim()==="") && p.origen_idx >= 0 && origenes[p.origen_idx]) {
            nombreProv = origenes[p.origen_idx].proveedor || "";
        }
        if ((!nombreProv || nombreProv.trim()==="") && p.origen_id) {
            const found = origenes.find(o => o.origen_id && o.origen_id == p.origen_id);
            if(found) nombreProv = found.proveedor || "";
        }

        c.innerHTML += `
        <div class="pesada-item animate__animated animate__fadeIn">
            <img src="${imgSrc}" class="pesada-thumb" onclick="window.open('${imgSrc}')">
            <div style="flex:1;">
                <div style="font-size:0.9rem; font-weight:700; color:#1b5e20;">
                    ${esc(nombreProv)} <span class="badge-cat ${badgeClass}">${badgeText}</span>
                </div>
                <div style="font-size:0.85rem; color:#555; margin-top:2px;">
                    <b>${p.jabas} jbs</b> | Bruto: ${p.pBruto} | <b style="color:#d32f2f">Neto: ${parseFloat(p.pNeto).toFixed(2)}</b>
                </div>
            </div>
            <button class="btn-trash" type="button" onclick="delPesada(${i})"><i class="bi bi-trash"></i></button>
        </div>`;
    });

    document.getElementById('gtj').innerText = tJabas;
    document.getElementById('gtp').innerText = tPesoNeto.toFixed(2);
    document.getElementById('total_fruta').value = tPesoNeto.toFixed(2);
}

// --- LIQUIDACIÓN ---
function updateLiquidation() {
    origenes.forEach(o => { o.kilos = {k1:0, k2:0, kr:0}; });

    pesadas.forEach(p => {
        let idx = -1;

        if (typeof p.origen_idx === 'number' && p.origen_idx >= 0 && origenes[p.origen_idx]) {
            idx = p.origen_idx;
        } else if (p.origen_id) {
            idx = origenes.findIndex(o => o.origen_id && o.origen_id == p.origen_id);
        }
        if (idx === -1 && p.origen) {
            idx = origenes.findIndex(o => (o.proveedor || "") === p.origen);
        }

        if(idx !== -1) {
            const neto = parseFloat(p.pNeto)||0;
            if(p.categoria==='cat1') origenes[idx].kilos.k1 += neto;
            else if(p.categoria==='cat2') origenes[idx].kilos.k2 += neto;
            else origenes[idx].kilos.kr += neto;
        }
    });

    const container = document.getElementById('liqui_container');
    container.innerHTML = "";
    let granTotal = 0;

    origenes.forEach((o, i) => {
        let sub = (o.kilos.k1 * (o.precios.p1||0)) + (o.kilos.k2 * (o.precios.p2||0)) + (o.kilos.kr * (o.precios.pr||0));
        granTotal += sub;

        container.innerHTML += `
        <div class="liqui-card">
            <div class="liqui-head">
                <span>${esc(o.proveedor)}</span>
                <span style="font-size:0.75rem; color:#666;">Tara: ${parseFloat(o.tara||1.6).toFixed(2)}</span>
            </div>
            <div class="liqui-row">
                <span class="badge-cat cat1 me-2">C1</span>
                <div style="flex:1">${o.kilos.k1.toFixed(2)} kg</div>
                <div style="width:110px;">
                    S/ <input type="number" class="mini-input" value="${o.precios.p1||''}" oninput="updP(${i}, 'p1', this.value)" placeholder="0.00">
                </div>
            </div>
            <div class="liqui-row">
                <span class="badge-cat cat2 me-2">C2</span>
                <div style="flex:1">${o.kilos.k2.toFixed(2)} kg</div>
                <div style="width:110px;">
                    S/ <input type="number" class="mini-input" value="${o.precios.p2||''}" oninput="updP(${i}, 'p2', this.value)" placeholder="0.00">
                </div>
            </div>
            <div class="liqui-row">
                <span class="badge-cat rastrojo me-2">RZ</span>
                <div style="flex:1">${o.kilos.kr.toFixed(2)} kg</div>
                <div style="width:110px;">
                    S/ <input type="number" class="mini-input" value="${o.precios.pr||''}" oninput="updP(${i}, 'pr', this.value)" placeholder="0.00">
                </div>
            </div>
            <div class="liqui-row bg-light" style="justify-content:space-between; font-weight:800;">
                <span>A Pagar:</span>
                <span id="sub_txt_${i}" style="color:#1b5e20;">S/ ${sub.toFixed(2)}</span>
            </div>
        </div>`;
    });

    document.getElementById('txt_total_pagar').innerText = "S/ " + granTotal.toFixed(2);
    document.getElementById('total_pagar_texto').value = granTotal.toFixed(2);

    document.getElementById('origenes_json').value = JSON.stringify(origenes);
}

function updP(idx, tipo, val) {
    origenes[idx].precios[tipo] = parseFloat(val) || 0;

    let granTotal = 0;
    origenes.forEach((o, i) => {
        let sub = (o.kilos.k1 * (o.precios.p1||0)) + (o.kilos.k2 * (o.precios.p2||0)) + (o.kilos.kr * (o.precios.pr||0));
        granTotal += sub;
        const el = document.getElementById('sub_txt_' + i);
        if(el) el.innerText = "S/ " + sub.toFixed(2);
    });

    document.getElementById('txt_total_pagar').innerText = "S/ " + granTotal.toFixed(2);
    document.getElementById('total_pagar_texto').value = granTotal.toFixed(2);
    document.getElementById('origenes_json').value = JSON.stringify(origenes);
}

function calcPersonal() {
    ['cosecha','cargadores','inspectores'].forEach(k => {
        const p = parseFloat(document.getElementById(k+'_personas').value)||0;
        const d = parseFloat(document.getElementById(k+'_dias').value)||0;
        const m = parseFloat(document.getElementById(k+'_precio').value)||0;
        document.getElementById('sub_'+k).value = (p*d*m).toFixed(2);
    });
}

// --- GUARDAR ---
function guardarTodo() {
    const btn = document.querySelector('.btn-save-main');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

    document.getElementById('origenes_json').value = JSON.stringify(origenes);

    const pesadasLimpias = pesadas.map(p => ({
        jabas: p.jabas,
        peso: p.pNeto,
        peso_bruto: p.pBruto,
        categoria: p.categoria,
        origen: p.origen || (p.origen_idx >= 0 && origenes[p.origen_idx] ? origenes[p.origen_idx].proveedor : ""),
        origen_idx: (typeof p.origen_idx === 'number' ? p.origen_idx : -1),
        origen_id: p.origen_id || null,
        foto_url: p.foto_url,
        es_nueva_fila: !!p.es_nueva_fila
    }));
    document.getElementById('detalle_pesadas_json').value = JSON.stringify(pesadasLimpias);

    const fd = new FormData(document.getElementById('formAcopio'));
    pesadas.forEach((p, idx) => { if(p.file) fd.append('foto_file_' + idx, p.file); });

    fetch('actualizar_goldfruits.php', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(res => {
        const t = res.trim().toUpperCase();
        if(t.includes('EXITO') || t.includes('OK') || t.includes('CORRECTAMENTE')) {
            alert('✅ Operación actualizada correctamente');
            window.location.href = 'mis_solicitudes.php';
        } else {
            alert('⚠️ El servidor respondió: ' + res);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>GUARDAR CAMBIOS';
        }
    })
    .catch(e => {
        console.error("Error de red:", e);
        alert('❌ Error de conexión o el servidor no responde.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>GUARDAR CAMBIOS';
    });
}

// --- INIT ---
window.onload = function() {
    inferirOrigenIdxParaPesadas();
    renderOrigenes();
    renderPesadas();
    updateLiquidation();
    calcPersonal();
};
</script>
</body>
</html>

