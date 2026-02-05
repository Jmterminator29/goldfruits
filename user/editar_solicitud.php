<?php
// editar_solicitud.php
// v5: Compatible con TARA/DESTARA y LIQUIDACIÓN POR PROVEEDOR
require_once '../includes/auth.php';       
require_once '../includes/db_connect.php'; 

if (!isset($_GET['id'])) {
    die("❌ Error: Falta el ID de la solicitud.");
}

$id  = (int)$_GET['id'];
$uid = (int)$_SESSION['user_id'];

// 1. Obtener Cabecera
$sql = "SELECT * FROM acopios_cabecera WHERE id = ? AND usuario_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$id, $uid]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("❌ Error: Solicitud no encontrada o no tienes permiso.");
}

// 2. Obtener Pesadas (Detalle) - INCLUYENDO peso_bruto
// Verificar si existe la columna peso_bruto (compatibilidad)
$stmtDet = $conn->prepare("SELECT * FROM acopios_pesadas WHERE acopio_id = ? ORDER BY numero_tanda ASC");
$stmtDet->execute([$id]);
$pesadas = $stmtDet->fetchAll(PDO::FETCH_ASSOC);


// 2.1 Obtener Orígenes desde tablas CON DETALLE DE PRECIOS Y TARA
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

// Preparar JSON para JS
$pesadas_json = json_encode($pesadas ?: []);

// Construir estructura rica para JS
if (!empty($origenes_db)) {
    $tmp = [];
    foreach ($origenes_db as $r) {
        $tmp[] = [
            "proveedor" => $r["proveedor"],
            "campo" => $r["campo"] ?? "",
            "cuenta" => $r["cuenta_bancaria"] ?? "",
            "proveedor_id" => (int)$r["proveedor_id"],
            "origen_id" => (int)$r["origen_id"],
            // Tara guardada (o 1.6 por defecto si es antiguo)
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
    $origenes_json = $data['origenes_detalle'] ?: '[]';
}

// Texto visual
$proveedor_lista_ui = trim((string)($data['proveedor'] ?? ''));
if (!empty($origenes_db)) {
    $names = [];
    foreach ($origenes_db as $r) $names[] = trim((string)$r['proveedor']);
    $names = array_values(array_unique($names));
    if (!empty($names)) $proveedor_lista_ui = implode(', ', $names);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Editar: <?php echo htmlspecialchars($data['codigo_unico']); ?></title>
    <style>
        :root { --primary: #1b5e20; --gold: #fbc02d; --bg: #f5f5f5; --text: #212121; --danger: #d32f2f; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); margin: 0; padding-bottom: 80px; color: var(--text); }
        
        .app-bar { background: var(--primary); color: white; padding: 15px 20px; position: sticky; top: 0; z-index: 100; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .app-bar h1 { margin: 0; font-size: 1.1rem; font-weight: 800; }

        .container { padding: 15px; max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #ddd; overflow: hidden; }
        .card.active { border-color: var(--gold); box-shadow: 0 0 0 1px var(--gold); }
        .card-header { padding: 15px; display: flex; justify-content: space-between; align-items: center; font-weight: 700; background: #fff; cursor: pointer; select-user: none; }
        .card-body { display: none; padding: 15px; background: #fff; border-top: 1px solid #eee; }
        .card.active .card-body { display: block; }

        label { display: block; margin-top: 10px; font-size: 0.8rem; color: #666; font-weight: 700; margin-bottom: 4px; }
        .input-box { background: #f9f9f9; border: 1px solid #ddd; border-radius: 8px; padding: 10px; display: flex; align-items: center; }
        .input-box.money { border-color: var(--gold); background: #fffde7; }
        .input-box.readonly { background: #eee; color: #555; pointer-events: none; }
        input, select { border: none; background: transparent; width: 100%; font-size: 16px; outline: none; color: #333; }
        
        .row { display: flex; gap: 10px; } .col { flex: 1; }

        .origen-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; background: #e3f2fd; padding: 8px; border-radius: 6px; }
        .btn-action { border:none; width: 36px; height: 36px; border-radius: 6px; cursor: pointer; display:grid; place-items:center; font-size:1.2rem; }
        .btn-plus { background: var(--primary); color: white; }
        .btn-trash { background: #ffebee; color: var(--danger); border: 1px solid var(--danger); }

        .pesada-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid #eee; background: white; font-size: 0.85rem; }
        .pesada-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc; margin-right: 10px; background: #eee; }
        
        .btn-main { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 900; margin-top: 25px; cursor: pointer; }
        .btn-main:disabled { background: #ccc; cursor: not-allowed; }
        
        .btn-camera { background: white; border: 1px dashed #aaa; color: #555; width: 100%; padding: 12px; border-radius: 8px; margin-top: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; cursor: pointer; }
        .btn-camera.has-photo { background: #e8f5e9; border-color: #2e7d32; color: #2e7d32; border-style: solid; }

        .big-total { font-size: 1.5rem; font-weight: 900; color: var(--primary); display: block; text-align: right; }
        .icon-arrow { transition: transform 0.2s; }
        .card.active .icon-arrow { transform: rotate(180deg); }

        /* Estilos Liquidación Detallada */
        .liqui-block { border: 1px solid #ccc; border-radius: 8px; margin-bottom: 15px; overflow: hidden; background: #fff; }
        .liqui-header { background: #e3f2fd; padding: 10px; font-weight: bold; color: #1565c0; font-size: 0.9rem; display:flex; justify-content:space-between; }
        .liqui-row { display: flex; align-items: center; border-bottom: 1px solid #eee; padding: 8px; font-size: 0.85rem; }
        .mini-input { width: 100%; border-bottom: 1px solid var(--gold); text-align: right; font-weight: bold; padding: 4px; border-top: none; border-left: none; border-right: none; background: transparent; }
        
        .badge-cat { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; color: white; font-weight: bold; margin-left: 5px; }
        .cat1 { background: #2e7d32; } 
        .cat2 { background: #fbc02d; color: black; } 
        .rastrojo { background: #d32f2f; }
    </style>
</head>
<body>

<div class="app-bar">
    <h1>✏️ Editando: <?php echo htmlspecialchars($data['codigo_unico']); ?></h1>
    <a href="mis_solicitudes.php" style="color:white; text-decoration:none; font-size:1.5rem;">&times;</a>
</div>

<div class="container">
    <form id="formAcopio">
        <input type="hidden" name="id_acopio" value="<?php echo (int)$data['id']; ?>">
        <input type="hidden" name="codigo_unico" value="<?php echo htmlspecialchars($data['codigo_unico']); ?>">
        <input type="hidden" id="origenes_json" name="origenes_json" value='<?php echo htmlspecialchars($origenes_json, ENT_QUOTES); ?>'>
        <input type="hidden" id="detalle_pesadas_json" name="detalle_pesadas_json">
        <input type="hidden" id="total_fruta" name="total_fruta" value="<?php echo htmlspecialchars($data['total_kilos_neto']); ?>">
        <input type="hidden" id="total_pagar_texto" name="total_pagar_texto">

        <div class="card active" id="card1">
            <div class="card-header" onclick="toggleCard('card1')">
                <span>1. Orígenes (Proveedores)</span> <span class="icon-arrow">▼</span>
            </div>
            <div class="card-body">
                <div id="lista_origenes" style="margin-top:15px;"></div>
                
                <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:150px;">
                        <input type="text" id="tmp_prov" class="input-box" placeholder="Nuevo Agricultor" style="margin-bottom:5px; background:#fff;">
                        <input type="text" id="tmp_campo" class="input-box" placeholder="Campo / Sector" style="background:#fff;">
                    </div>
                    <div style="width:100px;">
                        <select id="tmp_tara" class="input-box" style="background:#fff; font-weight:bold; color:var(--primary); font-size:0.8rem;">
                            <option value="1.6">Tara 1.6</option>
                            <option value="1.7">Tara 1.7</option>
                            <option value="1.8">Tara 1.8</option>
                        </select>
                    </div>
                    <button type="button" class="btn-action btn-plus" style="height:auto; width:40px;" onclick="addOrigen()">+</button>
                </div>
                
                <label>Cuenta Bancaria (General)</label>
                <div class="input-box">
                    <input type="text" name="cuenta" value="<?php echo htmlspecialchars($data['cuenta_bancaria']); ?>" placeholder="Banco - Nro Cuenta">
                </div>
            </div>
        </div>

        <div class="card" id="card2">
            <div class="card-header" onclick="toggleCard('card2')">
                <span>2. Transporte y Flete</span> <span class="icon-arrow">▼</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col"><label>Chofer</label><div class="input-box"><input type="text" name="conductor_nombre" value="<?php echo htmlspecialchars($data['conductor']); ?>"></div></div>
                    <div class="col"><label>Placa</label><div class="input-box"><input type="text" name="vehiculo_placa" value="<?php echo htmlspecialchars($data['placa']); ?>"></div></div>
                </div>
                <div class="row">
                    <div class="col"><label>Flete (S/)</label><div class="input-box money"><input type="number" name="flete" value="<?php echo htmlspecialchars($data['precio_flete']); ?>"></div></div>
                    <div class="col"><label style="color:var(--danger)">Adelanto</label><div class="input-box money" style="border-color:var(--danger);"><input type="number" name="adelanto_flete" value="<?php echo htmlspecialchars($data['adelanto_flete']); ?>"></div></div>
                </div>
            </div>
        </div>

        <div class="card" id="card3">
            <div class="card-header" onclick="toggleCard('card3')">
                <span>3. Pesaje (Balanza + Fotos)</span> <span class="icon-arrow">▼</span>
            </div>
            <div class="card-body">
                <div style="background: #f0f7ff; padding: 10px; border-radius: 8px; border: 1px solid #bbdefb;">
                    <label style="margin-top:0;">Origen de esta tanda:</label>
                    <select id="select_origen_pesada" class="input-box" style="background:#fff; font-weight:bold; color:#1565c0;" onchange="mostrarTaraInfo()"></select>
                    
                    <div id="info_tara_actual" style="text-align:right; font-size:0.8rem; color:#d32f2f; font-weight:bold; margin-bottom:5px; height:15px;"></div>

                    <label style="margin-top:0;">Categoría:</label>
                    <select id="select_categoria" class="input-box" style="background:#fff; font-weight:bold;">
                        <option value="cat1">🏆 Cat 1 - Grande</option>
                        <option value="cat2">🔸 Cat 1 - Chico</option>
                        <option value="rastrojo">❌ Rastrojo</option>
                    </select>

                    <div class="row">
                        <div class="col"><input type="number" id="temp_jabas" class="input-box" style="background:#fff" placeholder="Jabas"></div>
                        <div class="col"><input type="number" id="temp_peso" class="input-box" style="background:#fff" placeholder="Peso BALANZA"></div>
                    </div>

                    <label class="btn-camera" id="btn_temp_foto">
                        <span>📷 Tomar Foto</span>
                        <input type="file" id="temp_foto_input" accept="image/*" capture="environment" onchange="checkTempPhoto()" hidden>
                    </label>

                    <button type="button" class="btn-main" style="margin-top:10px; padding:10px; font-size:0.9rem;" onclick="agregarPesada()">AGREGAR TANDA</button>
                </div>

                <div id="lista_pesadas_container" style="margin-top:15px;"></div>

                <div class="row" style="margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
                    <div class="col" style="text-align:center;"><small>Jabas</small><br><b id="gtj" style="font-size:1.2rem;">0</b></div>
                    <div class="col" style="text-align:center;"><small>Peso NETO</small><br><b id="gtp" style="font-size:1.2rem; color:var(--primary);">0.00</b></div>
                </div>
            </div>
        </div>

        <div class="card" id="card4">
            <div class="card-header" onclick="toggleCard('card4')">
                <span style="color:var(--primary)">4. Liquidación (Detallada)</span> <span class="icon-arrow">▼</span>
            </div>
            <div class="card-body" style="background:#f9f9f9;">
                <p style="font-size:0.8rem; color:#666; margin-top:0;">Precios por proveedor (Neto):</p>
                
                <div id="liqui_container"></div>

                <div style="margin-top: 15px; border-top: 2px dashed #a5d6a7; padding-top: 10px; text-align:right;">
                    <span style="font-size:0.9rem; color:#555;">TOTAL A PAGAR:</span><br>
                    <span class="big-total" id="txt_total_pagar">S/ 0.00</span>
                </div>
            </div>
        </div>

        <div class="card" id="card5">
            <div class="card-header" onclick="toggleCard('card5')">
                <span>5. Personal de Campo</span> <span class="icon-arrow">▼</span>
            </div>
            <div class="card-body">
                <?php
                    $personal = [
                        ['id'=>'cosecha', 'label'=>'🚜 Cosecha'],
                        ['id'=>'cargadores', 'label'=>'📦 Cargadores'],
                        ['id'=>'inspectores', 'label'=>'🔍 Inspectores']
                    ];
                    foreach ($personal as $p) {
                        $k = $p['id'];
                        echo "
                        <div style='margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;'>
                            <strong style='color:#333; font-size:0.9rem;'>{$p['label']}</strong>
                            <div class='row' style='margin-top:5px;'>
                                <div class='col'><label>Pers.</label><div class='input-box'><input type='number' id='{$k}_personas' name='{$k}_personas' value='".htmlspecialchars($data[$k.'_personas'])."' oninput='calcPersonal()'></div></div>
                                <div class='col'><label>Días</label><div class='input-box'><input type='number' id='{$k}_dias' name='{$k}_dias' value='".htmlspecialchars($data[$k.'_dias'])."' oninput='calcPersonal()'></div></div>
                            </div>
                            <div class='row'>
                                <div class='col'><label>Precio Día</label><div class='input-box money'><input type='number' id='{$k}_precio' name='{$k}_precio' value='".htmlspecialchars($data[$k.'_precio'])."' oninput='calcPersonal()'></div></div>
                                <div class='col'><label>Subtotal</label><div class='input-box readonly'><input type='text' id='sub_{$k}' name='subtotal_{$k}' readonly value='".htmlspecialchars($data['subtotal_'.$k])."'></div></div>
                            </div>
                        </div>";
                    }
                ?>
            </div>
        </div>
        
        <div class="card" id="card6">
            <div class="card-header" onclick="toggleCard('card6')"><span>6. Otros Gastos</span> <span class="icon-arrow">▼</span></div>
            <div class="card-body">
                <div class="row">
                    <div class="col"><label>Viáticos</label><div class="input-box"><input type="number" name="viaticos" value="<?php echo htmlspecialchars($data['viaticos']); ?>"></div></div>
                    <div class="col"><label>Gastos Op.</label><div class="input-box"><input type="number" name="gastos_operativos" value="<?php echo htmlspecialchars($data['gastos_operativos']); ?>"></div></div>
                </div>
            </div>
        </div>

    </form>
    
    <button class="btn-main" onclick="guardarTodo()">💾 GUARDAR CAMBIOS</button>
</div>

<script>
// --- DATOS INICIALES ---
let origenes = [];
try { 
    origenes = JSON.parse(document.getElementById('origenes_json').value || '[]') || []; 
} catch(e) { origenes = []; }

// Normalizar estructura de origenes
origenes = origenes.map(o => {
    if(!o.precios) o.precios = {p1:0, p2:0, pr:0};
    if(!o.kilos) o.kilos = {k1:0, k2:0, kr:0};
    if(!o.tara) o.tara = 1.6; // Default si no tiene
    return o;
});

// Fallback si no hay origenes
if (origenes.length === 0 && "<?php echo addslashes($data['proveedor']); ?>") {
    origenes.push({ 
        proveedor: "<?php echo addslashes($data['proveedor']); ?>", campo: "", 
        precios: {p1:0, p2:0, pr:0}, kilos: {k1:0, k2:0, kr:0}, tara: 1.6
    });
}

let pesadas = <?php echo $pesadas_json; ?>;
// Normalizar datos de pesadas
pesadas = pesadas.map(p => {
    // Si es registro antiguo, no tiene peso_bruto. Asumimos bruto=neto (o bruto=peso)
    // El campo 'peso' de la BD es el NETO.
    let neto = parseFloat(p.peso) || 0;
    let bruto = (p.peso_bruto) ? parseFloat(p.peso_bruto) : neto; // Fallback

    return {
        jabas: parseFloat(p.jabas) || 0,
        pNeto: neto,
        pBruto: bruto,
        foto_url: p.foto_url || null,
        file: null, 
        preview: p.foto_url || null, 
        origen_id: (p.origen_id) ? parseInt(p.origen_id, 10) : null,
        origen: p.origen_referencia || "",
        categoria: p.categoria || "cat1",
        es_nueva_fila: false
    };
});

// --- INICIALIZACIÓN ---
window.onload = function() {
    renderOrigenes();
    renderPesadas();
    updateLiquidation(); 
    calcPersonal();
};

function toggleCard(id) {
    const el = document.getElementById(id);
    const wasActive = el.classList.contains('active');
    document.querySelectorAll('.card').forEach(c => c.classList.remove('active'));
    if(!wasActive) el.classList.add('active');
}

// --- LOGICA ORIGENES ---
function renderOrigenes() {
    const div = document.getElementById('lista_origenes');
    div.innerHTML = "";
    origenes.forEach((o, i) => {
        div.innerHTML += `
        <div class="origen-row">
            <div style="flex:1;">
                <strong>${esc(o.proveedor)}</strong> <small>(${esc(o.campo)})</small><br>
                <span style="font-size:0.75rem; color:#d32f2f;">Tara: ${o.tara} kg</span>
            </div>
            <button type="button" class="btn-action btn-trash" onclick="delOrigen(${i})">🗑️</button>
        </div>`;
    });
    actualizarSelects();
}

function addOrigen() {
    const p = document.getElementById('tmp_prov').value.trim();
    const c = document.getElementById('tmp_campo').value.trim();
    const t = parseFloat(document.getElementById('tmp_tara').value) || 1.6;

    if(!p) return alert("Ingresa nombre");
    
    origenes.push({ 
        proveedor: p, campo: c, tara: t,
        precios: {p1:0, p2:0, pr:0},
        kilos: {k1:0, k2:0, kr:0}
    });
    
    document.getElementById('tmp_prov').value = "";
    document.getElementById('tmp_campo').value = "";
    renderOrigenes();
    updateLiquidation();
}

function delOrigen(i) {
    if(confirm('¿Borrar origen?')) {
        origenes.splice(i, 1);
        renderOrigenes();
        updateLiquidation();
    }
}

function actualizarSelects() {
    fillSelect('select_origen_pesada');
}

function fillSelect(id) {
    const sel = document.getElementById(id);
    if(!sel) return;
    sel.innerHTML = "";
    sel.add(new Option("-- Selecciona Origen --", ""));

    origenes.forEach((o, idx) => {
        const label = o.proveedor + (o.campo ? " - " + o.campo : "");
        const value = idx; // INDEX
        const opt = new Option(label, value);
        sel.add(opt);
    });
    
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

// --- LOGICA PESADAS ---
function checkTempPhoto() {
    const f = document.getElementById('temp_foto_input');
    const b = document.getElementById('btn_temp_foto');
    if(f.files && f.files[0]) {
        b.classList.add('has-photo');
        b.querySelector('span').innerText = "✅ Foto Lista";
    }
}

function agregarPesada() {
    const selOri = document.getElementById('select_origen_pesada');
    const idx = selOri.value;
    if(idx === "") return alert("Selecciona origen");

    const j = parseFloat(document.getElementById('temp_jabas').value);
    const pBruto = parseFloat(document.getElementById('temp_peso').value);
    const cat = document.getElementById('select_categoria').value;
    const fileInp = document.getElementById('temp_foto_input');

    if(!j || !pBruto) return alert("Faltan datos numéricos");
    if(!fileInp.files[0]) return alert("La foto es obligatoria");

    // CALCULO DESTARA
    const o = origenes[idx];
    const taraUnit = o.tara || 1.6;
    let pNeto = pBruto - (j * taraUnit);
    if(pNeto < 0) pNeto = 0;

    pesadas.push({
        jabas: j, 
        pBruto: pBruto,
        pNeto: pNeto,
        categoria: cat, 
        origen_id: o.origen_id || null, 
        origen: o.proveedor, 
        file: fileInp.files[0],
        preview: URL.createObjectURL(fileInp.files[0]),
        foto_url: null,
        es_nueva_fila: true 
    });

    document.getElementById('temp_jabas').value = "";
    document.getElementById('temp_peso').value = "";
    fileInp.value = "";
    const btn = document.getElementById('btn_temp_foto');
    btn.classList.remove('has-photo');
    btn.querySelector('span').innerText = "📷 Tomar Foto";
    
    renderPesadas();
    updateLiquidation(); 
}

function delPesada(i) {
    if(confirm("¿Eliminar tanda?")) {
        pesadas.splice(i, 1);
        renderPesadas();
        updateLiquidation();
    }
}

function renderPesadas() {
    const c = document.getElementById('lista_pesadas_container');
    c.innerHTML = "";
    
    let tJabas=0, tPesoNeto=0;

    pesadas.forEach((p, i) => {
        tJabas += p.jabas;
        tPesoNeto += p.pNeto;
        
        const imgSrc = p.preview || p.foto_url || 'placeholder.png';
        const badgeClass = p.categoria === 'cat1' ? 'cat1' : (p.categoria === 'cat2' ? 'cat2' : 'rastrojo');
        const badgeText = p.categoria === 'cat1' ? 'C1-Gde' : (p.categoria === 'cat2' ? 'C1-Chico' : 'Rastrojo');

        let nombreProv = p.origen;
        if (!nombreProv && p.origen_id) {
            const found = origenes.find(o => o.origen_id == p.origen_id);
            if(found) nombreProv = found.proveedor;
        }

        c.innerHTML += `
        <div class="pesada-item">
            <div style="display:flex; align-items:center;">
                <img src="${imgSrc}" class="pesada-thumb">
                <div>
                    <div style="margin-bottom:4px; font-weight:bold; color:#1565c0;">
                        ${esc(nombreProv)} <span class="badge-cat ${badgeClass}">${badgeText}</span>
                    </div>
                    <div style="font-size:0.85rem;">
                        <b>${p.jabas} jbs</b> | Bruto: ${p.pBruto} | <b style="color:#d32f2f">Neto: ${p.pNeto.toFixed(2)}</b>
                    </div>
                </div>
            </div>
            <button class="btn-action btn-trash" onclick="delPesada(${i})">🗑️</button>
        </div>`;
    });

    document.getElementById('gtj').innerText = tJabas;
    document.getElementById('gtp').innerText = tPesoNeto.toFixed(2);
    document.getElementById('total_fruta').value = tPesoNeto.toFixed(2);
}

// --- MOTOR DE LIQUIDACIÓN ---
function updateLiquidation() {
    origenes.forEach(o => { o.kilos = {k1:0, k2:0, kr:0}; });

    pesadas.forEach(p => {
        let oriIndex = -1;
        if(p.origen_id) oriIndex = origenes.findIndex(o => o.origen_id == p.origen_id);
        if (oriIndex === -1) oriIndex = origenes.findIndex(o => o.proveedor === p.origen);

        if(oriIndex !== -1) {
            if(p.categoria==='cat1') origenes[oriIndex].kilos.k1 += p.pNeto;
            else if(p.categoria==='cat2') origenes[oriIndex].kilos.k2 += p.pNeto;
            else origenes[oriIndex].kilos.kr += p.pNeto;
        }
    });

    let container = document.getElementById('liqui_container');
    container.innerHTML = "";
    let granTotal = 0;

    origenes.forEach((o, i) => {
        let sub = (o.kilos.k1 * o.precios.p1) + (o.kilos.k2 * o.precios.p2) + (o.kilos.kr * o.precios.pr);
        granTotal += sub;

        container.innerHTML += `
        <div class="liqui-block">
            <div class="liqui-header">
                <span>${esc(o.proveedor)}</span>
                <span style="font-size:0.8rem; color:#444;">(Tara: ${o.tara})</span>
            </div>
            
            <div class="liqui-row">
                <span class="badge-cat cat1">C1</span> 
                <div style="flex:1">${o.kilos.k1.toFixed(2)} kg</div>
                <div style="width:90px;">
                    S/ <input type="number" class="mini-input" value="${o.precios.p1||''}" 
                    oninput="updP(${i}, 'p1', this.value)" placeholder="0.00">
                </div>
            </div>
            <div class="liqui-row">
                <span class="badge-cat cat2">C2</span> 
                <div style="flex:1">${o.kilos.k2.toFixed(2)} kg</div>
                <div style="width:90px;">
                    S/ <input type="number" class="mini-input" value="${o.precios.p2||''}" 
                    oninput="updP(${i}, 'p2', this.value)" placeholder="0.00">
                </div>
            </div>
            <div class="liqui-row">
                <span class="badge-cat rastrojo">RZ</span> 
                <div style="flex:1">${o.kilos.kr.toFixed(2)} kg</div>
                <div style="width:90px;">
                    S/ <input type="number" class="mini-input" value="${o.precios.pr||''}" 
                    oninput="updP(${i}, 'pr', this.value)" placeholder="0.00">
                </div>
            </div>
            <div class="liqui-row" style="justify-content:space-between; background:#fffde7; font-weight:bold;">
                <span>A Pagar:</span>
                <span id="sub_txt_${i}">S/ ${sub.toFixed(2)}</span>
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
        let sub = (o.kilos.k1 * o.precios.p1) + (o.kilos.k2 * o.precios.p2) + (o.kilos.kr * o.precios.pr);
        granTotal += sub;
        let el = document.getElementById('sub_txt_' + i);
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

function esc(str) {
    if(!str) return "";
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function guardarTodo() {
    const btn = document.querySelector('.btn-main');
    btn.disabled = true;
    btn.innerText = "Guardando...";

    document.getElementById('origenes_json').value = JSON.stringify(origenes);
    
    const pesadasLimpias = pesadas.map(p => ({
        jabas: p.jabas,
        peso: p.pNeto, // Enviamos NETO como 'peso' para el backend
        peso_bruto: p.pBruto, // Enviamos BRUTO como 'peso_bruto'
        categoria: p.categoria,
        origen: p.origen || (p.origen_id ? origenes.find(o=>o.origen_id==p.origen_id)?.proveedor : ""),
        foto_url: p.foto_url, 
        es_nueva_fila: p.es_nueva_fila 
    }));
    document.getElementById('detalle_pesadas_json').value = JSON.stringify(pesadasLimpias);

    const fd = new FormData(document.getElementById('formAcopio'));
    
    pesadas.forEach((p, idx) => {
        if(p.file) {
            fd.append('foto_file_' + idx, p.file);
        }
    });

    fetch('actualizar_goldfruits.php', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(res => {
        if(res.trim().includes('Exitoso') || res.trim() === 'OK') {
            alert('✅ Guardado correctamente');
            window.location.href = 'mis_solicitudes.php';
        } else {
            alert('⚠️ ' + res);
            btn.disabled = false;
            btn.innerText = "💾 GUARDAR CAMBIOS";
        }
    })
    .catch(e => {
        alert('Error de conexión');
        btn.disabled = false;
        btn.innerText = "💾 GUARDAR CAMBIOS";
    });
}
</script>
</body>
</html>