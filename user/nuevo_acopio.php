<?php require_once '../includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1b5e20"> 
    <title>Nuevo Acopio</title>

    <script src="offline_queue.js"></script>

    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js');
      }
    </script>
    <style>
        /* --- ESTILOS --- */
        :root { --primary: #1b5e20; --gold: #fbc02d; --bg: #f5f5f5; --text: #212121; --danger: #d32f2f; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); margin: 0; padding-bottom: 80px; color: var(--text); overflow-x: hidden; transition: margin-left .3s; }
        
        .app-bar { background: var(--primary); color: white; padding: 15px 20px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.2); display: flex; justify-content: space-between; align-items: center; }
        .menu-btn { font-size: 1.5rem; cursor: pointer; }
        
        .sidebar { height: 100%; width: 0; position: fixed; z-index: 200; top: 0; left: 0; background-color: #111; overflow-x: hidden; transition: 0.3s; padding-top: 60px; }
        .sidebar a { padding: 15px 20px; text-decoration: none; font-size: 1.1rem; color: #818181; display: block; border-bottom: 1px solid #333; }
        .sidebar .closebtn { position: absolute; top: 0; right: 25px; font-size: 36px; border: none; }
        #overlay { position: fixed; display: none; width: 100%; height: 100%; top: 0; left: 0; background: rgba(0,0,0,0.5); z-index: 150; }

        .container { padding: 15px; max-width: 100%; }
        .card { background: white; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .card.active { border: 1px solid var(--gold); }
        .card-header { padding: 15px; display: flex; justify-content: space-between; font-weight: 600; cursor: pointer; background: white; }
        .card-body { display: none; padding: 15px; border-top: 1px solid #eee; }
        .card.active .card-body { display: block; }

        label { display: block; margin-top: 10px; font-size: 0.8rem; color: #666; font-weight: 600; }
        .input-box { background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px; padding: 10px; display: flex; align-items: center; }
        .input-box.money { border-color: var(--gold); background: #fffde7; }
        .input-box.readonly { background: #e0e0e0; color: #555; pointer-events: none; }
        input, select { border: none; background: transparent; width: 100%; font-size: 1rem; outline: none; }
        
        .origen-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
        .btn-plus { background: var(--primary); color: white; border: none; width: 40px; height: 40px; border-radius: 8px; font-size: 1.2rem; cursor: pointer; }
        .btn-trash { background: #ffebee; color: var(--danger); border: 1px solid var(--danger); width: 40px; height: 40px; border-radius: 8px; cursor: pointer; }

        .pesada-item { display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #eee; align-items: center; }
        .pesada-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px; }
        .add-area { background: #e3f2fd; padding: 15px; border-radius: 8px; }
        .mini-camera-btn { background: white; border: 1px solid #ccc; width: 100%; padding: 10px; border-radius: 8px; margin-top: 10px; display: flex; justify-content: center; gap: 10px; cursor: pointer; }
        .mini-camera-btn.has-photo { background: #c8e6c9; border-color: #2e7d32; color: #1b5e20; }
        .btn-add { width: 100%; background: var(--primary); color: white; padding: 12px; border: none; border-radius: 8px; margin-top: 10px; font-weight: bold; }

        .personal-block { border: 1px solid #eee; padding: 10px; border-radius: 8px; margin-bottom: 10px; background: #fafafa; }
        .subtotal-val { font-weight: 800; color: var(--primary); text-align: right; display: block; margin-top: 5px; }
        .btn-main { width: 100%; padding: 18px; background: var(--primary); color: white; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 700; margin-top: 20px; }
        .row { display: flex; gap: 10px; } .col { flex: 1; }
        input[type="file"] { display: none; }
        .gps-dot { height: 12px; width: 12px; border-radius: 50%; background: #ccc; display: inline-block; }
        .gps-active { background: #00e676; box-shadow: 0 0 5px #00e676; }
        
        .select-custom { width: 100%; padding: 10px; border-radius: 8px; border: 2px solid #1565c0; background: white; color: #1565c0; font-weight: bold; margin-bottom: 10px; }
        
        /* ETIQUETAS VISUALES */
        .badge-cat { font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; color: white; font-weight: bold; margin-left: 5px; }
        .cat1 { background: #2e7d32; } 
        .cat2 { background: #fbc02d; color: black; } 
        .rastrojo { background: #d32f2f; }

        /* Estilos Liquidación Detallada */
        .liqui-block { border: 1px solid #ccc; border-radius: 8px; margin-bottom: 15px; overflow: hidden; background: #fff; }
        .liqui-header { background: #e3f2fd; padding: 10px; font-weight: bold; color: #1565c0; font-size: 0.9rem; display: flex; justify-content: space-between;}
        .liqui-row { display: flex; align-items: center; border-bottom: 1px solid #eee; padding: 8px; font-size: 0.85rem; }
        .mini-input { width: 100%; border-bottom: 1px solid var(--gold); text-align: right; font-weight: bold; padding: 4px; border-top: none; border-left: none; border-right: none; background: transparent; }
    </style>
</head>
<body>

    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">×</a>
        <div style="text-align:center; color:var(--gold); font-weight:bold; margin-bottom:20px;">HOLA, <?php echo strtoupper($_SESSION['user_nombre']); ?></div>
        
        <a href="nuevo_acopio.php" style="color:white; background:#222;">➕ Nueva Operación</a>
        <a href="mis_solicitudes.php">📂 Mis Solicitudes</a>
        <a href="ia_panel.php" style="color:var(--gold); border-left: 4px solid var(--gold);">🤖 Consultor IA</a>
        <a href="../logout.php" style="color:#ff5252;">🚪 Cerrar Sesión</a>
    </div>
    <div id="overlay" onclick="closeNav()"></div>

    <div class="app-bar">
        <span class="menu-btn" onclick="openNav()">☰</span>
        <h1 style="margin:0; font-size:1.1rem;">Nuevo Acopio</h1>
        <div id="gpsStatus" class="gps-dot"></div>
    </div>

    <div id="netBanner" style="display:none; position:sticky; top:52px; z-index:120; background:#ffebee; color:#b71c1c; padding:10px 15px; font-weight:700; border-bottom:1px solid #ffcdd2;">
        📴 Sin internet: se guardará offline.
        <span id="pendingCount" style="float:right;"></span>
        <div style="clear:both"></div>
    </div>

    <div class="container">
        <form id="formAcopio">
            <input type="hidden" id="codigo_unico" name="codigo_unico">
            <input type="hidden" id="latitud" name="latitud">
            <input type="hidden" id="longitud" name="longitud">
            <input type="hidden" id="origenes_json" name="origenes_json"> 
            <input type="hidden" id="detalle_pesadas_json" name="detalle_pesadas_json">
            <input type="hidden" id="total_pagar_texto" name="total_pagar_texto">

            <div class="card active" id="c1">
                <div class="card-header" onclick="tgl('c1')">1. Orígenes (Proveedores) ▼</div>
                <div class="card-body">
                    <div id="lista_origenes"></div>
                    <div class="origen-row" style="margin-top:10px; border-top:1px dashed #ccc; padding-top:10px; flex-wrap:wrap;">
                        <div style="flex:1; min-width: 150px;">
                            <input type="text" id="tmp_prov" class="input-box" placeholder="Nombre Agricultor" style="margin-bottom:5px;">
                            <input type="text" id="tmp_campo" class="input-box" placeholder="Campo / Sector">
                        </div>
                        <div style="width: 100px;">
                            <label style="margin-top:0; font-size:0.7rem; color:var(--primary);">Tara Jaba</label>
                            <select id="tmp_tara" class="input-box" style="font-weight:bold; color:var(--primary);">
                                <option value="1.6">1.6 kg</option>
                                <option value="1.7">1.7 kg</option>
                                <option value="1.8">1.8 kg</option>
                            </select>
                        </div>
                        <button type="button" class="btn-plus" onclick="addOrigen()">+</button>
                    </div>
                    <label>Cuenta Bancaria (General)</label>
                    <div class="input-box"><input type="tel" name="cuenta" placeholder="Para depósito general"></div>
                </div>
            </div>

            <div class="card" id="c2">
                <div class="card-header" onclick="tgl('c2')">2. Transporte ▼</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col"><label>Chofer</label><div class="input-box"><input type="text" name="conductor_nombre"></div></div>
                        <div class="col"><label>Placa</label><div class="input-box"><input type="text" name="vehiculo_placa" style="text-transform:uppercase;"></div></div>
                    </div>
                    <div class="row">
                        <div class="col"><label>Flete (S/)</label><div class="input-box money"><input type="number" name="flete" placeholder="0.00"></div></div>
                        <div class="col"><label style="color:#d32f2f;">Adelanto</label><div class="input-box money" style="border-color:#d32f2f; background:#ffebee;"><input type="number" name="adelanto_flete" placeholder="0.00"></div></div>
                    </div>
                </div>
            </div>

            <div class="card" id="c3">
                <div class="card-header" onclick="tgl('c3')">3. Pesaje ▼</div>
                <div class="card-body">
                    <div class="add-area">
                        <label style="margin-top:0; color:#1565c0;">¿A quién pertenece?</label>
                        <select id="select_origen_pesada" class="select-custom" onchange="mostrarTaraInfo()">
                            <option value="">-- Selecciona Origen --</option>
                        </select>
                        
                        <div id="info_tara_actual" style="text-align:right; font-size:0.8rem; color:#d32f2f; font-weight:bold; margin-bottom:5px; height:15px;"></div>
                        
                        <label style="margin-top:5px; color:#2e7d32;">Calidad / Categoría</label>
                        <select id="select_categoria" class="select-custom" style="border-color:#2e7d32; color:#2e7d32;">
                            <option value="cat1">🏆 Cat 1 - Grande</option>
                            <option value="cat2">🔸 Cat 1 - Chico</option>
                            <option value="rastrojo">❌ Rastrojo</option>
                        </select>

                        <div class="row" style="margin-top:10px;">
                            <div class="col"><input type="number" id="tj" class="input-box" style="background:white" placeholder="Jabas"></div>
                            <div class="col"><input type="number" id="tp" class="input-box" style="background:white" placeholder="Peso BALANZA"></div>
                        </div>
                        <label class="mini-camera-btn" id="btn_temp_foto"><span>📷 Tomar Foto</span><input type="file" id="tf" accept="image/*" capture="environment" onchange="checkF()"></label>
                        <button type="button" class="btn-add" onclick="addP()">AGREGAR TANDA</button>
                    </div>
                    
                    <div id="listP" class="pesadas-container"></div>
                    
                    <div class="row" style="margin-top:15px; font-size:0.8rem; color:#666;">
                        <div class="col">Jabas Totales: <b id="gtj">0</b></div>
                        <div class="col">Peso NETO: <b id="gtp" style="color:var(--primary); font-size:1.1rem;">0.00</b></div>
                    </div>
                </div>
            </div>

            <div class="card" id="c4">
                <div class="card-header" onclick="tgl('c4')">4. Liquidación (Detallada) ▼</div>
                <div class="card-body" style="background:#f9f9f9;">
                    <p style="font-size:0.8rem; color:#666; margin-top:0;">Establece el precio (Se paga por Peso NETO):</p>
                    
                    <div id="liqui_container"></div>

                    <div style="background:#e8f5e9; padding:15px; border-radius:8px; text-align:right; margin-top:15px; border: 1px solid #c8e6c9;">
                        <small>TOTAL GENERAL A PAGAR</small><br>
                        <span id="txt_gran_total" style="font-size:1.6rem; font-weight:900; color:#1b5e20;">S/ 0.00</span>
                    </div>
                </div>
            </div>

            <div class="card" id="c5">
                <div class="card-header" onclick="tgl('c5')">5. Personal ▼</div>
                <div class="card-body">
                    <div class="personal-block"><strong>🚜 Cosecha</strong>
                        <div class="row"><div class="col"><input type="number" id="cp" name="cosecha_personas" class="input-box" placeholder="Pers" oninput="cP()"></div><div class="col"><input type="number" id="cd" name="cosecha_dias" class="input-box" value="1" oninput="cP()"></div></div>
                        <div class="input-box money" style="margin-top:5px;"><input type="number" id="cpr" name="cosecha_precio" placeholder="Precio Día" oninput="cP()"></div>
                        <span class="subtotal-val" id="txt_sc">S/ 0.00</span><input type="hidden" id="sc" name="sub_cosecha" value="0">
                    </div>
                    <div class="personal-block"><strong>📦 Cargadores</strong>
                        <div class="row"><div class="col"><input type="number" id="cap" name="cargadores_personas" class="input-box" placeholder="Pers" oninput="cP()"></div><div class="col"><input type="number" id="cad" name="cargadores_dias" class="input-box" value="1" oninput="cP()"></div></div>
                        <div class="input-box money" style="margin-top:5px;"><input type="number" id="capr" name="cargadores_precio" placeholder="Precio Día" oninput="cP()"></div>
                        <span class="subtotal-val" id="txt_sca">S/ 0.00</span><input type="hidden" id="sca" name="sub_cargadores" value="0">
                    </div>
                    <div class="personal-block"><strong>🔍 Inspectores</strong>
                        <div class="row"><div class="col"><input type="number" id="ip" name="inspectores_personas" class="input-box" placeholder="Pers" oninput="cP()"></div><div class="col"><input type="number" id="id" name="inspectores_dias" class="input-box" value="1" oninput="cP()"></div></div>
                        <div class="input-box money" style="margin-top:5px;"><input type="number" id="ipr" name="inspectores_precio" placeholder="Precio Día" oninput="cP()"></div>
                        <span class="subtotal-val" id="txt_si">S/ 0.00</span><input type="hidden" id="si" name="sub_inspectores" value="0">
                    </div>
                </div>
            </div>

            <div class="card" id="c6">
                <div class="card-header" onclick="tgl('c6')">6. Otros ▼</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col"><label>Viáticos</label><div class="input-box"><input type="number" name="viaticos" placeholder="0.00"></div></div>
                        <div class="col"><label>Otros</label><div class="input-box"><input type="number" name="operativos" placeholder="0.00"></div></div>
                    </div>
                </div>
            </div>
        </form>
        <button class="btn-main" id="btnG" onclick="send()">GUARDAR OPERACIÓN</button>
    </div>

<script>
    // --- ESTADO ---
    // Estructura Origen: { proveedor, campo, tara: 1.6, precios:{p1,p2,pr}, kilos:{k1,k2,kr} }
    let origenes = [];
    let pesadas = [];

    // --- ORIGENES ---
    function addOrigen() {
        let p = document.getElementById('tmp_prov').value.trim();
        let c = document.getElementById('tmp_campo').value.trim();
        let t = parseFloat(document.getElementById('tmp_tara').value) || 1.6; // Por defecto 1.6 si falla

        if(p === "") { alert("Escribe nombre"); return; }
        
        // Inicializamos precios en 0 y kilos en 0
        origenes.push({ 
            proveedor: p, 
            campo: c,
            tara: t, // <-- Guardamos la tara especifica
            precios: { p1:0, p2:0, pr:0 },
            kilos: { k1:0, k2:0, kr:0 }
        });

        document.getElementById('tmp_prov').value = ""; document.getElementById('tmp_campo').value = "";
        renderOrigenes(); 
        actualizarSelectPesaje();
        updateLiquidation(); // Refrescar tabla liquidación
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
            h += `<div class="origen-row" style="background:#e3f2fd; padding:8px; border-radius:6px;">
                    <div style="flex:1;">
                        <strong>${o.proveedor}</strong> <small>(${o.campo})</small><br>
                        <span style="font-size:0.75rem; color:#d32f2f;">Tara: ${o.tara} kg</span>
                    </div>
                    <button type="button" class="btn-trash" onclick="removeOrigen(${i})">🗑️</button>
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
                opt.value=i; // Usamos el INDEX para vincular
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
            
        if(!j||!pBruto||!f) return alert("Faltan datos");
        
        // --- LOGICA DE DESTARA ---
        let taraUnit = origenes[idx].tara; // Ya validado al crear origen
        let descuento = j * taraUnit;
        let pNeto = pBruto - descuento;
        
        if(pNeto < 0) pNeto = 0; // Seguridad

        let cat = document.getElementById('select_categoria').value; 
        let provName = origenes[idx].proveedor; // Guardar referencia visual

        pesadas.push({
            idx: parseInt(idx), // Index del proveedor en el array origenes
            j: j, 
            pBruto: pBruto, // Bruto para registro
            pNeto: pNeto,   // Neto para pago
            tara: taraUnit,
            f: f, 
            u: URL.createObjectURL(f), 
            origen: provName, 
            cat: cat
        });

        document.getElementById('tj').value=""; document.getElementById('tp').value=""; document.getElementById('tf').value="";
        document.getElementById('btn_temp_foto').classList.remove('has-photo');
        
        renderP();
        updateLiquidation(); // Recalcular kilos por proveedor
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
                        <div style="font-size:0.8rem; color:#1565c0;">${x.origen} <span class="badge-cat ${badgeClass}">${badgeText}</span></div>
                        <div style="font-size:0.85rem;">
                            <b>${x.j} jb</b> | Bruto: ${x.pBruto} | <b style="color:#d32f2f">Neto: ${x.pNeto.toFixed(2)}</b>
                        </div>
                    </div>
                </div>`;
        });
        document.getElementById('listP').innerHTML=h;
        document.getElementById('gtj').innerText=gtj; 
        document.getElementById('gtp').innerText=gtpNeto.toFixed(2);
    }

    // --- LIQUIDACIÓN POR PROVEEDOR ---
    function updateLiquidation() {
        // 1. Resetear kilos en origenes para recalcular
        origenes.forEach(o => { o.kilos = {k1:0, k2:0, kr:0}; });

        // 2. Sumar kilos de las pesadas a cada origen (USANDO NETO)
        pesadas.forEach(p => {
            if(origenes[p.idx]) {
                if(p.cat==='cat1') origenes[p.idx].kilos.k1 += p.pNeto;
                else if(p.cat==='cat2') origenes[p.idx].kilos.k2 += p.pNeto;
                else origenes[p.idx].kilos.kr += p.pNeto;
            }
        });

        // 3. Renderizar Tabla Dinámica
        let container = document.getElementById('liqui_container');
        container.innerHTML = "";
        let granTotal = 0;

        origenes.forEach((o, i) => {
            let sub = (o.kilos.k1 * o.precios.p1) + (o.kilos.k2 * o.precios.p2) + (o.kilos.kr * o.precios.pr);
            granTotal += sub;

            container.innerHTML += `
            <div class="liqui-block">
                <div class="liqui-header">
                    <span>${o.proveedor}</span>
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

        document.getElementById('txt_gran_total').innerText = "S/ " + granTotal.toFixed(2);
        document.getElementById('total_pagar_texto').value = granTotal.toFixed(2);
        
        // Actualizamos inputs JSON para envío
        document.getElementById('origenes_json').value = JSON.stringify(origenes);
    }

    function updP(idx, tipo, val) {
        origenes[idx].precios[tipo] = parseFloat(val) || 0;
        
        // Recalcular SOLO visuales para no perder foco del input
        let granTotal = 0;
        origenes.forEach((o, i) => {
            let sub = (o.kilos.k1 * o.precios.p1) + (o.kilos.k2 * o.precios.p2) + (o.kilos.kr * o.precios.pr);
            granTotal += sub;
            let el = document.getElementById('sub_txt_' + i);
            if(el) el.innerText = "S/ " + sub.toFixed(2);
        });
        
        document.getElementById('txt_gran_total').innerText = "S/ " + granTotal.toFixed(2);
        document.getElementById('total_pagar_texto').value = granTotal.toFixed(2);
        
        // Guardar estado
        document.getElementById('origenes_json').value = JSON.stringify(origenes);
    }

    // --- PERSONAL (Sin Cambios) ---
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

    function tgl(id){ document.querySelectorAll('.card').forEach(c=>c.classList.remove('active')); document.getElementById(id).classList.add('active'); }
    function openNav(){ document.getElementById("mySidebar").style.width="250px"; document.getElementById("overlay").style.display="block"; }
    function closeNav(){ document.getElementById("mySidebar").style.width="0"; document.getElementById("overlay").style.display="none"; }
    
    window.onload=()=>{ document.getElementById('codigo_unico').value='GF-'+Date.now(); forceGPS(); }
    function forceGPS(){
        navigator.geolocation.getCurrentPosition(p=>{
            document.getElementById('latitud').value=p.coords.latitude; document.getElementById('longitud').value=p.coords.longitude;
            document.getElementById('gpsStatus').className="gps-dot gps-active";
        }, e=>{ alert("Activa GPS"); });
    }

    async function send(){
        let btn=document.getElementById('btnG');
        if(!document.getElementById('latitud').value && !confirm("Sin GPS. ¿Seguir?")) return;
        if(origenes.length===0){ alert("Falta Proveedor"); tgl('c1'); return; }
        
        btn.disabled=true; btn.innerText="Guardando...";
        
        // PREPARAR DATOS
        // 1. Origenes ya tiene precios y kilos gracias a updateLiquidation()
        document.getElementById('origenes_json').value = JSON.stringify(origenes);

        // 2. Pesadas: Convertir a formato simple para backend
        // IMPORTANTE: Enviar 'peso' como NETO y 'peso_bruto' como BRUTO
        let pesadasSimple = pesadas.map(x => ({
            jabas: x.j, 
            peso: x.pNeto, // EL BACKEND DEBE RECIBIR NETO EN 'peso' para calcular pagos
            peso_bruto: x.pBruto, // EXTRA para registro
            origen: x.origen, // Nombre del proveedor
            categoria: x.cat
        }));
        
        let fd=new FormData(document.getElementById('formAcopio'));
        fd.append('detalle_pesadas_json', JSON.stringify(pesadasSimple));
        
        // 3. Fotos
        pesadas.forEach((x,i)=>fd.append('fotos_pesadas[]', x.f));

        // LOGICA ENVIO (OFFLINE FIRST)
        if (!navigator.onLine) {
            try {
                // Para offline, necesitamos guardar la estructura compleja de origenes
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

    // UI de Red (Banner)
    function ensureNetBanner(){
        let banner = document.getElementById('netBanner');
        if (!banner) return; // Ya existe en HTML
        return banner;
    }
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