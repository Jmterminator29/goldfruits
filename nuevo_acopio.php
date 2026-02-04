<?php require_once 'auth.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1b5e20"> 
    <title>Nuevo Acopio</title>

    <!-- Offline-first: cola local + sincronización -->
    <script src="offline_queue.js"></script>

    <script>
      // Asegura SW también en páginas internas
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
        .cat1 { background: #2e7d32; } /* Verde - Grande */
        .cat2 { background: #fbc02d; color: black; } /* Amarillo - Chico */
        .rastrojo { background: #d32f2f; } /* Rojo */
    </style>
</head>
<body>

    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">×</a>
        <div style="text-align:center; color:var(--gold); font-weight:bold; margin-bottom:20px;">HOLA, <?php echo strtoupper($_SESSION['user_nombre']); ?></div>
        
        <a href="nuevo_acopio.php" style="color:white; background:#222;">➕ Nueva Operación</a>
        <a href="mis_solicitudes.php">📂 Mis Solicitudes</a>
        
        <a href="ia_panel.php" style="color:var(--gold); border-left: 4px solid var(--gold);">🤖 Consultor IA</a>
        
        <a href="logout.php" style="color:#ff5252;">🚪 Cerrar Sesión</a>
    </div>
    <div id="overlay" onclick="closeNav()"></div>

    <div class="app-bar">
        <span class="menu-btn" onclick="openNav()">☰</span>
        <h1 style="margin:0; font-size:1.1rem;">Nuevo Acopio</h1>
        <div id="gpsStatus" class="gps-dot"></div>
    </div>

    <div id="netBanner" style="display:none; position:sticky; top:52px; z-index:120; background:#ffebee; color:#b71c1c; padding:10px 15px; font-weight:700; border-bottom:1px solid #ffcdd2;">
        📴 Sin internet: se guardará offline y se enviará automáticamente al volver la conexión.
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
<p style="font-size:0.8rem; color:#666; margin-top:0;">Agrega los agricultores:</p>
                    <div id="lista_origenes"></div>
                    <div class="origen-row" style="margin-top:10px; border-top:1px dashed #ccc; padding-top:10px;">
                        <div style="flex:1;">
                            <input type="text" id="tmp_prov" placeholder="Nombre Agricultor">
                            <input type="text" id="tmp_campo" placeholder="Campo / Sector" style="margin-top:5px;">
                        </div>
                        <button type="button" class="btn-plus" onclick="addOrigen()">+</button>
                    </div>
                    <label>Cuenta Bancaria (Principal)</label>
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
                        <select id="select_origen_pesada" class="select-custom">
                            <option value="">-- Selecciona Origen --</option>
                        </select>
                        
                        <label style="margin-top:10px; color:#2e7d32;">Calidad / Categoría</label>
                        <select id="select_categoria" class="select-custom" style="border-color:#2e7d32; color:#2e7d32;">
                            <option value="cat1">🏆 Cat 1 - Grande</option>
                            <option value="cat2">🔸 Cat 1 - Chico</option>
                            <option value="rastrojo">❌ Rastrojo</option>
                        </select>

                        <div class="row" style="margin-top:10px;">
                            <div class="col"><input type="number" id="tj" class="input-box" style="background:white" placeholder="Jabas"></div>
                            <div class="col"><input type="number" id="tp" class="input-box" style="background:white" placeholder="Kg"></div>
                        </div>
                        <label class="mini-camera-btn" id="btn_temp_foto"><span>📷 Tomar Foto</span><input type="file" id="tf" accept="image/*" capture="environment" onchange="checkF()"></label>
                        <button type="button" class="btn-add" onclick="addP()">AGREGAR TANDA</button>
                    </div>
                    
                    <div id="listP" class="pesadas-container"></div>
                    
                    <div class="row" style="margin-top:15px; font-size:0.8rem; color:#666;">
                        <div class="col">Jabas Totales: <b id="gtj">0</b></div>
                        <div class="col">Peso Bruto Total: <b id="gtp">0.00</b></div>
                    </div>
                </div>
            </div>

            <div class="card" id="c4">
                <div class="card-header" onclick="tgl('c4')">4. Liquidación ▼</div>
                <div class="card-body" style="background:#f1f8e9;">
                    
                    <div class="row" style="align-items:center; border-bottom:1px dashed #ccc; padding-bottom:5px; margin-bottom:5px;">
                        <div class="col">
                            <label style="margin:0; color:#2e7d32; font-size:0.75rem;">Cat 1 - Grande (Kg)</label>
                            <div class="input-box readonly"><input type="number" id="k_cat1" name="total_cat1" value="0" readonly></div>
                        </div>
                        <div class="col">
                            <label style="margin:0; font-size:0.75rem;">Precio</label>
                            <div class="input-box money"><input type="number" id="p_cat1" name="precio_cat1" placeholder="0.00" oninput="calcT()"></div>
                        </div>
                    </div>

                    <div class="row" style="align-items:center; border-bottom:1px dashed #ccc; padding-bottom:5px; margin-bottom:5px;">
                        <div class="col">
                            <label style="margin:0; color:#f9a825; font-size:0.75rem;">Cat 1 - Chico (Kg)</label>
                            <div class="input-box readonly"><input type="number" id="k_cat2" name="total_cat2" value="0" readonly></div>
                        </div>
                        <div class="col">
                            <label style="margin:0; font-size:0.75rem;">Precio</label>
                            <div class="input-box money"><input type="number" id="p_cat2" name="precio_cat2" placeholder="0.00" oninput="calcT()"></div>
                        </div>
                    </div>

                    <div class="row" style="align-items:center;">
                        <div class="col">
                            <label style="margin:0; color:#c62828; font-size:0.75rem;">Rastrojo</label>
                            <div class="input-box readonly"><input type="number" id="k_rastrojo" name="total_rastrojo" value="0" readonly></div>
                        </div>
                        <div class="col">
                            <label style="margin:0; font-size:0.75rem;">Precio</label>
                            <div class="input-box money"><input type="number" id="p_rastrojo" name="precio_rastrojo" placeholder="0.00" oninput="calcT()"></div>
                        </div>
                    </div>

                    <input type="hidden" id="tot_fruta" name="total_fruta" value="0">
                    <input type="hidden" id="pkg" name="precio_kg" value="0">

                    <h2 style="text-align:right; margin-top:15px; color:#1b5e20; border-top:2px solid #1b5e20; padding-top:10px;" id="txt_tot">S/ 0.00</h2>
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
    // --- ORIGENES ---
    let origenes = [];
    function addOrigen() {
        let p = document.getElementById('tmp_prov').value.trim();
        let c = document.getElementById('tmp_campo').value.trim();
        if(p === "") { alert("Escribe nombre"); return; }
        origenes.push({ proveedor: p, campo: c });
        document.getElementById('tmp_prov').value = ""; document.getElementById('tmp_campo').value = "";
        renderOrigenes(); actualizarSelectPesaje();
    }
    function removeOrigen(idx) { origenes.splice(idx, 1); renderOrigenes(); actualizarSelectPesaje(); }
    function renderOrigenes() {
        let h = "";
        origenes.forEach((o, i) => {
            h += `<div class="origen-row" style="background:#e3f2fd; padding:8px; border-radius:6px;"><div style="flex:1;"><strong>${o.proveedor}</strong><br><small>${o.campo}</small></div><button type="button" class="btn-trash" onclick="removeOrigen(${i})">🗑️</button></div>`;
        });
        document.getElementById('lista_origenes').innerHTML = h;
        document.getElementById('origenes_json').value = JSON.stringify(origenes);
    }
    function actualizarSelectPesaje() {
        let s = document.getElementById('select_origen_pesada'); s.innerHTML = "";
        if(origenes.length === 0) { let o=document.createElement('option'); o.value=""; o.text="-- Falta Origen --"; s.add(o); }
        else { origenes.forEach(o => { let opt=document.createElement('option'); let t=o.proveedor+(o.campo?" - "+o.campo:""); opt.value=t; opt.text=t; s.add(opt); }); }
    }

    // --- PESAJE ---
    let listP = [];
    function checkF(){ if(document.getElementById('tf').files[0]) document.getElementById('btn_temp_foto').classList.add('has-photo'); }
    function addP(){
        let o = document.getElementById('select_origen_pesada').value;
        let cat = document.getElementById('select_categoria').value; 
        if(o === "") { alert("Agrega origen primero"); tgl('c1'); return; }
        
        let j=parseFloat(document.getElementById('tj').value), p=parseFloat(document.getElementById('tp').value), f=document.getElementById('tf').files[0];
        if(!j||!p||!f) return alert("Faltan datos");
        
        listP.push({j:j, p:p, f:f, u:URL.createObjectURL(f), origen:o, cat:cat});
        document.getElementById('tj').value=""; document.getElementById('tp').value=""; document.getElementById('tf').value="";
        document.getElementById('btn_temp_foto').classList.remove('has-photo');
        renderP();
    }
    function renderP(){
        let h="", gtj=0, gtp=0;
        let k1=0, k2=0, kr=0;

        listP.forEach((x,i)=>{ 
            gtj+=x.j; gtp+=x.p;
            
            // Sumar al acumulador correspondiente
            if(x.cat === 'cat1') k1 += x.p;
            else if(x.cat === 'cat2') k2 += x.p;
            else if(x.cat === 'rastrojo') kr += x.p;

            // Etiquetas Visuales CORREGIDAS
            let badgeClass = x.cat === 'cat1' ? 'cat1' : (x.cat === 'cat2' ? 'cat2' : 'rastrojo');
            let badgeText = x.cat === 'cat1' ? 'C1-Gde' : (x.cat === 'cat2' ? 'C1-Chico' : 'Rastrojo'); // Aquí corregido

            h+=`<div class="pesada-item"><img src="${x.u}" class="pesada-thumb"><div style="flex:1;"><div style="font-size:0.8rem; color:#1565c0;">${x.origen} <span class="badge-cat ${badgeClass}">${badgeText}</span></div><div>#${i+1}: ${x.j}j / ${x.p}kg</div></div></div>`;
        });
        document.getElementById('listP').innerHTML=h;
        document.getElementById('gtj').innerText=gtj; document.getElementById('gtp').innerText=gtp.toFixed(2);
        
        document.getElementById('k_cat1').value = k1.toFixed(2);
        document.getElementById('k_cat2').value = k2.toFixed(2);
        document.getElementById('k_rastrojo').value = kr.toFixed(2);
        
        // Actualizamos también el total general oculto para compatibilidad
        document.getElementById('tot_fruta').value = gtp.toFixed(2);
        
        calcT(); 
    }

    // --- CÁLCULOS ---
    function calcT(){
        let k1 = parseFloat(document.getElementById('k_cat1').value)||0;
        let p1 = parseFloat(document.getElementById('p_cat1').value)||0;
        
        let k2 = parseFloat(document.getElementById('k_cat2').value)||0;
        let p2 = parseFloat(document.getElementById('p_cat2').value)||0;
        
        let kr = parseFloat(document.getElementById('k_rastrojo').value)||0;
        let pr = parseFloat(document.getElementById('p_rastrojo').value)||0;

        let total = (k1*p1) + (k2*p2) + (kr*pr);
        document.getElementById('txt_tot').innerText="S/ "+total.toFixed(2);
        document.getElementById('total_pagar_texto').value=total.toFixed(2);
    }

    function cP(){
        ['cosecha','cargadores','inspectores'].forEach(k=>{
            let p=parseFloat(document.getElementById(k=='cosecha'?'cp':(k=='cargadores'?'cap':'ip')).value)||0;
            let d=parseFloat(document.getElementById(k=='cosecha'?'cd':(k=='cargadores'?'cad':'id')).value)||0;
            let m=parseFloat(document.getElementById(k=='cosecha'?'cpr':(k=='cargadores'?'capr':'ipr')).value)||0;
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
        
        let fd=new FormData(document.getElementById('formAcopio'));
        fd.append('detalle_pesadas_json', JSON.stringify(listP.map(x=>({jabas:x.j, peso:x.p, origen:x.origen, categoria:x.cat}))));
        listP.forEach((x,i)=>fd.append('fotos_pesadas[]', x.f));

        // Si NO hay internet, guardar en cola offline
        if (!navigator.onLine) {
            try {
                const { local_id } = await window.GF_OFFLINE.queueAcopioFromCurrentForm(document.getElementById('formAcopio'), listP);
                alert("✅ Guardado OFFLINE (ID: " + local_id + "). Se enviará cuando vuelva el internet.");
                btn.disabled=false; btn.innerText="GUARDAR OPERACIÓN";
                window.location.reload();
                return;
            } catch (e) {
                alert("No se pudo guardar offline: " + e);
                btn.disabled=false; btn.innerText="GUARDAR OPERACIÓN";
                return;
            }
        }
        
        // Online: intentar enviar. Si falla, guardar offline.
        try {
            const r = await fetch('guardar_goldfruits.php', {method:'POST', body:fd});
            const d = await r.text();
            if (!r.ok || /^error\s*:/i.test((d||'').trim())) throw new Error(d || ('HTTP '+r.status));
            alert(d);
            window.location.reload();
        } catch(e){
            try {
                const { local_id } = await window.GF_OFFLINE.queueAcopioFromCurrentForm(document.getElementById('formAcopio'), listP);
                alert("⚠️ No se pudo enviar (sin conexión o servidor). Guardado OFFLINE (ID: " + local_id + "). Se enviará automáticamente.");
                btn.disabled=false; btn.innerText="GUARDAR OPERACIÓN";
                window.location.reload();
            } catch (err) {
                alert("Error al enviar y al guardar offline: " + err);
                btn.disabled=false; btn.innerText="GUARDAR OPERACIÓN";
            }
        }
    }

    // Indicador de conexión + auto-sync
    function ensureNetBanner(){
        let banner = document.getElementById('netBanner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'netBanner';
            banner.style.position = 'fixed';
            banner.style.left = '0';
            banner.style.right = '0';
            banner.style.bottom = '0';
            banner.style.padding = '10px 12px';
            banner.style.zIndex = '9999';
            banner.style.background = '#111';
            banner.style.color = '#fff';
            banner.style.fontSize = '0.9rem';
            banner.style.display = 'none';
            banner.innerHTML = '📴 Sin internet. Se guardará OFFLINE. <span id="pendingCount" style="opacity:0.9; margin-left:10px;"></span>';
            document.body.appendChild(banner);
        }
        return banner;
    }

    async function refreshNetUI(){
        const banner = ensureNetBanner();
        const pending = document.getElementById('pendingCount');
        try {
            const items = await window.GF_OFFLINE.listQueue();
            if (pending) pending.innerText = items.length ? ('Pendientes: ' + items.length) : '';
        } catch (e) {
            if (pending) pending.innerText = '';
        }
        banner.style.display = navigator.onLine ? 'none' : 'block';
    }

    window.addEventListener('online', async () => {
        await refreshNetUI();
        // En cuanto vuelve internet, sincronizamos
        try {
            const r = await window.GF_OFFLINE.syncQueue();
            if (r && r.synced) {
                alert('📡 Sincronizado: ' + r.synced + ' operación(es) enviada(s).');
            }
        } catch (e) {}
    });
    window.addEventListener('offline', refreshNetUI);

    // Intentar sync al cargar (si hay pendientes y hay internet)
    (async () => {
        await refreshNetUI();
        if (navigator.onLine) {
            try { await window.GF_OFFLINE.syncQueue(); } catch (e) {}
        }
    })();
</script>
</body>
</html>