<?php
// editar_solicitud.php
require_once 'auth.php';       
require_once 'db_connect.php'; 

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

// 2. Obtener Pesadas (Detalle)
$stmtDet = $conn->prepare("SELECT * FROM acopios_pesadas WHERE acopio_id = ? ORDER BY numero_tanda ASC");
$stmtDet->execute([$id]);
$pesadas = $stmtDet->fetchAll(PDO::FETCH_ASSOC);


// 2.1 Obtener Orígenes desde tablas (si existen)
$stmtOri = $conn->prepare("
    SELECT ao.id as origen_id, p.id as proveedor_id, p.nombre as proveedor, ao.campo, p.cuenta_bancaria
    FROM acopios_origenes ao
    JOIN proveedores p ON p.id = ao.proveedor_id
    WHERE ao.acopio_id = ?
    ORDER BY ao.id ASC
");
$stmtOri->execute([$id]);
$origenes_db = $stmtOri->fetchAll(PDO::FETCH_ASSOC);

// Preparar JSON para JS
$pesadas_json = json_encode($pesadas ?: []);

// Preferir orígenes de BD (match real). Si no hay, usar JSON guardado en cabecera (compatibilidad).
if (!empty($origenes_db)) {
    $tmp = [];
    foreach ($origenes_db as $r) {
        $tmp[] = [
            "proveedor" => $r["proveedor"],
            "campo" => $r["campo"] ?? "",
            "cuenta" => $r["cuenta_bancaria"] ?? "",
            "proveedor_id" => (int)$r["proveedor_id"],
            "origen_id" => (int)$r["origen_id"]
        ];
    }
    $origenes_json = json_encode($tmp, JSON_UNESCAPED_UNICODE);
} else {
    $origenes_json = $data['origenes_detalle'] ?: '[]';
}

// Texto profesional para mostrar productores con comas
$proveedor_lista_ui = trim((string)($data['proveedor'] ?? ''));
if (!empty($origenes_db)) {
    $names = [];
    foreach ($origenes_db as $r) {
        $n = trim((string)($r['proveedor'] ?? ''));
        if ($n !== '') $names[] = $n;
    }
    $names = array_values(array_unique($names));
    if (!empty($names)) $proveedor_lista_ui = implode(', ', $names);
} else {
    $tmpOri = json_decode($data['origenes_detalle'] ?: '[]', true);
    if (is_array($tmpOri)) {
        $names = [];
        foreach ($tmpOri as $r) {
            $n = trim((string)($r['proveedor'] ?? ''));
            if ($n !== '') $names[] = $n;
        }
        $names = array_values(array_unique($names));
        if (!empty($names)) $proveedor_lista_ui = implode(', ', $names);
    }
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

        <div class="card active" id="card1">
            <div class="card-header" onclick="toggleCard('card1')">
                <span>1. Orígenes (Proveedores)</span> <span class="icon-arrow">▼</span>
            </div>
            <div class="card-body">
                <label>Productores (lista)</label>
                <div class="input-box">
                    <input type="text" value="<?php echo htmlspecialchars($proveedor_lista_ui); ?>" readonly>
                </div>
                <small style="display:block; margin-top:6px; color:#666;">Agrega o quita productores abajo. Al guardar, se actualizarán automáticamente con comas.</small>

                <div id="lista_origenes"></div>
                <div style="display:flex; gap:8px; margin-top:10px;">
                    <div style="flex:1;">
                        <input type="text" id="tmp_prov" class="input-box" placeholder="Nombre Agricultor" style="margin-bottom:5px; background:#fff;">
                        <input type="text" id="tmp_campo" class="input-box" placeholder="Campo / Sector" style="background:#fff;">
                    </div>
                    <button type="button" class="btn-action btn-plus" style="height:auto;" onclick="addOrigen()">+</button>
                </div>
                
                <label>Cuenta Bancaria</label>
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
                    <select id="select_origen_pesada" class="input-box" style="background:#fff; font-weight:bold; color:#1565c0;"></select>

                    <label>Categoría:</label>
                    <select id="select_categoria" class="input-box" style="background:#fff; font-weight:bold;">
                        <option value="cat1">🏆 Cat 1 - Grande</option>
                        <option value="cat2">🔸 Cat 1 - Chico</option>
                        <option value="rastrojo">❌ Rastrojo</option>
                    </select>

                    <div class="row">
                        <div class="col"><input type="number" id="temp_jabas" class="input-box" style="background:#fff" placeholder="Jabas"></div>
                        <div class="col"><input type="number" id="temp_peso" class="input-box" style="background:#fff" placeholder="Kg"></div>
                    </div>

                    <label class="btn-camera" id="btn_temp_foto">
                        <span>📷 Tomar Foto</span>
                        <input type="file" id="temp_foto_input" accept="image/*" capture="environment" onchange="checkTempPhoto()" hidden>
                    </label>

                    <button type="button" class="btn-main" style="margin-top:10px; padding:10px; font-size:0.9rem;" onclick="agregarPesada()">AGREGAR TANDA</button>
                </div>

                <div id="lista_pesadas_container" style="margin-top:15px;"></div>

                <div class="row" style="margin-top:15px; border-top:1px solid #eee; padding-top:10px;">
                    <div class="col" style="text-align:center;"><small>Total Jabas</small><br><b id="gtj" style="font-size:1.2rem;">0</b></div>
                    <div class="col" style="text-align:center;"><small>Total Kg</small><br><b id="gtp" style="font-size:1.2rem; color:var(--primary);">0.00</b></div>
                </div>
                
                <input type="hidden" name="total_jabas" id="input_total_jabas">
                <input type="hidden" name="total_peso_bruto" id="input_total_peso">
            </div>
        </div>

        <div class="card" id="card4">
            <div class="card-header" onclick="toggleCard('card4')">
                <span style="color:var(--primary)">4. Liquidación Compra</span> <span class="icon-arrow">▼</span>
            </div>
            <div class="card-body" style="background-color: #f1f8e9;">
                <div class="row" style="align-items:center;">
                    <div class="col">
                        <label style="margin:0; font-size:0.75rem; color:#2e7d32;">Cat 1 (Kg)</label>
                        <input type="number" id="k_cat1" name="total_cat1" class="input-box readonly" readonly>
                    </div>
                    <div class="col">
                        <label style="margin:0; font-size:0.75rem;">Precio</label>
                        <input type="number" id="p_cat1" name="precio_cat1" class="input-box money" value="<?php echo htmlspecialchars($data['precio_cat1']); ?>" oninput="calcularTotalFruta()">
                    </div>
                </div>
                <div class="row" style="align-items:center; margin-top:8px;">
                    <div class="col">
                        <label style="margin:0; font-size:0.75rem; color:#f9a825;">Cat 2 (Kg)</label>
                        <input type="number" id="k_cat2" name="total_cat2" class="input-box readonly" readonly>
                    </div>
                    <div class="col">
                        <input type="number" id="p_cat2" name="precio_cat2" class="input-box money" value="<?php echo htmlspecialchars($data['precio_cat2']); ?>" oninput="calcularTotalFruta()">
                    </div>
                </div>
                <div class="row" style="align-items:center; margin-top:8px;">
                    <div class="col">
                        <label style="margin:0; font-size:0.75rem; color:#c62828;">Rastrojo (Kg)</label>
                        <input type="number" id="k_rastrojo" name="total_rastrojo" class="input-box readonly" readonly>
                    </div>
                    <div class="col">
                        <input type="number" id="p_rastrojo" name="precio_rastrojo" class="input-box money" value="<?php echo htmlspecialchars($data['precio_rastrojo']); ?>" oninput="calcularTotalFruta()">
                    </div>
                </div>

                <div style="margin-top: 15px; border-top: 2px dashed #a5d6a7; padding-top: 10px; text-align:right;">
                    <span style="font-size:0.9rem; color:#555;">A Pagar por Fruta:</span><br>
                    <span class="big-total" id="txt_total_pagar">S/ 0.00</span>
                    <input type="hidden" name="importe_total_fruta" id="hidden_total_pagar">
                    <input type="hidden" name="precio_x_kg" id="precio_kg_promedio">
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

// Fallback si no hay origenes pero sí un proveedor en cabecera antigua
if (origenes.length === 0 && "<?php echo addslashes($data['proveedor']); ?>") {
    origenes.push({ proveedor: "<?php echo addslashes($data['proveedor']); ?>", campo: "" });
}

let pesadas = <?php echo $pesadas_json; ?>;
// Normalizar datos de pesadas
pesadas = pesadas.map(p => ({
    jabas: parseFloat(p.jabas) || 0,
    peso: parseFloat(p.peso) || 0,
    foto_url: p.foto_url || null,
    file: null, // Para nueva foto
    preview: p.foto_url || null, // URL para mostrar
    origen_id: (p.origen_id !== undefined && p.origen_id !== null && p.origen_id !== '') ? parseInt(p.origen_id, 10) : null,
    origen: p.origen_referencia || "",
    categoria: p.categoria || "cat1",
    es_nueva_fila: false
}));

// --- INICIALIZACIÓN ---
window.onload = function() {
    renderOrigenes();
    renderPesadas();
    calcularTotalFruta();
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
                <strong>${esc(o.proveedor)}</strong> <small>(${esc(o.campo)})</small>
            </div>
            <button type="button" class="btn-action btn-trash" onclick="delOrigen(${i})">🗑️</button>
        </div>`;
    });
    actualizarSelects();
}

function addOrigen() {
    const p = document.getElementById('tmp_prov').value.trim();
    const c = document.getElementById('tmp_campo').value.trim();
    if(!p) return alert("Ingresa al menos el nombre del proveedor");
    origenes.push({ proveedor: p, campo: c });
    document.getElementById('tmp_prov').value = "";
    document.getElementById('tmp_campo').value = "";
    renderOrigenes();
}

function delOrigen(i) {
    if(confirm('¿Borrar origen?')) {
        origenes.splice(i, 1);
        renderOrigenes();
    }
}

function actualizarSelects() {
    // Select principal para agregar
    fillSelect('select_origen_pesada');
    
    // Si hay pesadas renderizadas, actualizar sus selects internos
}

function fillSelect(id, selectedVal = null) {
    const sel = document.getElementById(id);
    if(!sel) return;
    sel.innerHTML = "";
    if(origenes.length === 0) {
        sel.add(new Option("-- Crea un origen primero --", ""));
        return;
    }

    origenes.forEach((o, idx) => {
        const label = o.proveedor + (o.campo ? " - " + o.campo : "");
        const value = (o.origen_id !== undefined && o.origen_id !== null) ? String(o.origen_id) : label; // fallback
        const opt = new Option(label, value);
        opt.dataset.label = label;
        if(selectedVal !== null && String(selectedVal) === String(value)) opt.selected = true;
        sel.add(opt);
    });

    if(selectedVal !== null && selectedVal !== '' && !Array.from(sel.options).some(o => String(o.value) === String(selectedVal))) {
        const opt = new Option(String(selectedVal) + " (Eliminado)", String(selectedVal));
        opt.dataset.label = String(selectedVal);
        opt.selected = true;
        sel.add(opt);
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
    const j = parseFloat(document.getElementById('temp_jabas').value);
    const p = parseFloat(document.getElementById('temp_peso').value);
    const cat = document.getElementById('select_categoria').value;
    const selOri = document.getElementById('select_origen_pesada');
    const oriVal = selOri.value;
    const oriLabel = selOri.options[selOri.selectedIndex] ? (selOri.options[selOri.selectedIndex].dataset.label || selOri.options[selOri.selectedIndex].text) : '';
    const oriIdNum = parseInt(oriVal, 10);
    const oriId = (!isNaN(oriIdNum) && String(oriIdNum) === String(oriVal)) ? oriIdNum : null;
    const fileInp = document.getElementById('temp_foto_input');

    if(!oriVal) return alert("Selecciona un origen");
    if(!j || !p) return alert("Ingresa jabas y peso");
    if(!fileInp.files[0]) return alert("La foto es obligatoria");

    pesadas.push({
        jabas: j, peso: p, categoria: cat, origen_id: oriId, origen: oriLabel,
        file: fileInp.files[0],
        preview: URL.createObjectURL(fileInp.files[0]),
        foto_url: null,
        es_nueva_fila: true // Esto debe coincidir con el backend
    });

    document.getElementById('temp_jabas').value = "";
    document.getElementById('temp_peso').value = "";
    fileInp.value = "";
    const btn = document.getElementById('btn_temp_foto');
    btn.classList.remove('has-photo');
    btn.querySelector('span').innerText = "📷 Tomar Foto";
    
    renderPesadas();
}

function updatePesada(idx, key, val) {
    pesadas[idx][key] = val;
    if(key === 'categoria' || key === 'peso') renderPesadas(false); 
    renderPesadas(); 
}


function updatePesadaOrigen(idx, sel){
    const v = sel.value;
    const label = sel.options[sel.selectedIndex] ? (sel.options[sel.selectedIndex].dataset.label || sel.options[sel.selectedIndex].text) : '';
    const n = parseInt(v, 10);
    pesadas[idx].origen_id = (!isNaN(n) && String(n) === String(v)) ? n : null;
    pesadas[idx].origen = label;
    renderPesadas();
}

function delPesada(i) {
    if(confirm("¿Eliminar tanda?")) {
        pesadas.splice(i, 1);
        renderPesadas();
    }
}

function renderPesadas() {
    const c = document.getElementById('lista_pesadas_container');
    c.innerHTML = "";
    
    let tJabas=0, tPeso=0;
    let k1=0, k2=0, kr=0;

    pesadas.forEach((p, i) => {
        tJabas += p.jabas;
        tPeso += p.peso;
        if(p.categoria === 'cat1') k1 += p.peso;
        else if(p.categoria === 'cat2') k2 += p.peso;
        else kr += p.peso;

        const imgSrc = p.preview || 'placeholder.png';
        
        let optsOrigen = "";
        origenes.forEach(o => {
            const label = o.proveedor + (o.campo ? " - " + o.campo : "");
            const value = (o.origen_id !== undefined && o.origen_id !== null) ? String(o.origen_id) : label;
            const selected = (p.origen_id !== null && p.origen_id !== undefined && String(p.origen_id) === String(value))
                          || (p.origen_id === null && label === p.origen);
            optsOrigen += `<option value="${esc(value)}" data-label="${esc(label)}" ${selected ? 'selected' : ''}>${esc(label)}</option>`;
        });

        if(p.origen && !(p.origen_id !== null && p.origen_id !== undefined) && !origenes.some(o => (o.proveedor + (o.campo ? " - " + o.campo : "")) === p.origen)) {
             optsOrigen += `<option value="${esc(p.origen)}" data-label="${esc(p.origen)}" selected>${esc(p.origen)} (?)</option>`;
        }

        c.innerHTML += `
        <div class="pesada-item">
            <div style="display:flex; align-items:center;">
                <img src="${imgSrc}" class="pesada-thumb">
                <div>
                    <div style="margin-bottom:4px;">
                        <select onchange="updatePesadaOrigen(${i}, this)" style="border:1px solid #ccc; border-radius:4px; font-size:0.8rem; width:140px;">
                            ${optsOrigen}
                        </select>
                    </div>
                    <div style="margin-bottom:4px;">
                        <select onchange="updatePesada(${i}, 'categoria', this.value)" style="border:1px solid #ccc; border-radius:4px; font-size:0.8rem;">
                            <option value="cat1" ${p.categoria=='cat1'?'selected':''}>Cat 1 Gde</option>
                            <option value="cat2" ${p.categoria=='cat2'?'selected':''}>Cat 1 Chi</option>
                            <option value="rastrojo" ${p.categoria=='rastrojo'?'selected':''}>Rastrojo</option>
                        </select>
                    </div>
                    <div style="font-size:0.85rem;">
                        <b>${p.jabas} jbs</b> | <span style="color:var(--primary); font-weight:bold;">${p.peso} kg</span>
                    </div>
                </div>
            </div>
            <button class="btn-action btn-trash" onclick="delPesada(${i})">🗑️</button>
        </div>`;
    });

    document.getElementById('gtj').innerText = tJabas;
    document.getElementById('gtp').innerText = tPeso.toFixed(2);
    
    document.getElementById('k_cat1').value = k1.toFixed(2);
    document.getElementById('k_cat2').value = k2.toFixed(2);
    document.getElementById('k_rastrojo').value = kr.toFixed(2);
    
    document.getElementById('input_total_jabas').value = tJabas;
    document.getElementById('input_total_peso').value = tPeso.toFixed(2);
    document.getElementById('total_fruta').value = tPeso.toFixed(2);

    calcularTotalFruta();
}

function calcularTotalFruta() {
    const getVal = (id) => parseFloat(document.getElementById(id).value) || 0;
    const total = (getVal('k_cat1') * getVal('p_cat1')) + 
                  (getVal('k_cat2') * getVal('p_cat2')) + 
                  (getVal('k_rastrojo') * getVal('p_rastrojo'));
    
    document.getElementById('txt_total_pagar').innerText = "S/ " + total.toLocaleString('es-PE', {minimumFractionDigits: 2});
    document.getElementById('hidden_total_pagar').value = total.toFixed(2);
    
    const kgs = parseFloat(document.getElementById('total_fruta').value) || 0;
    document.getElementById('precio_kg_promedio').value = kgs > 0 ? (total/kgs).toFixed(4) : 0;
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

// --- ENVIO ---
function guardarTodo() {
    const btn = document.querySelector('.btn-main');
    btn.disabled = true;
    btn.innerText = "Guardando...";

    document.getElementById('origenes_json').value = JSON.stringify(origenes);
    
    const pesadasLimpias = pesadas.map(p => ({
        jabas: p.jabas,
        peso: p.peso,
        categoria: p.categoria,
        origen_id: p.origen_id,
        origen: p.origen,
        foto_url: p.foto_url, 
        es_nueva_fila: p.es_nueva_fila // Clave para el backend
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