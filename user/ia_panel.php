<?php
// ia_panel.php
require_once '../includes/auth.php'; //
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1b5e20">
    <title>Consultor IA | GoldFruits</title>
    <style>
        /* --- ESTILOS IDENTICOS A NUEVO ACOPIO --- */
        :root { --primary: #1b5e20; --gold: #fbc02d; --bg: #f5f5f5; --text: #212121; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); margin: 0; color: var(--text); padding-bottom: 80px; }
        
        /* Barra Superior */
        .app-bar { background: var(--primary); color: white; padding: 15px 20px; position: sticky; top: 0; z-index: 100; display: flex; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .menu-btn { font-size: 1.5rem; cursor: pointer; margin-right: 15px; }
        
        /* Menú Lateral (Copiado de nuevo_acopio.php) */
        .sidebar { height: 100%; width: 0; position: fixed; z-index: 200; top: 0; left: 0; background-color: #111; overflow-x: hidden; transition: 0.3s; padding-top: 60px; }
        .sidebar a { padding: 15px 20px; text-decoration: none; font-size: 1.1rem; color: #818181; display: block; border-bottom: 1px solid #333; }
        .sidebar .closebtn { position: absolute; top: 0; right: 25px; font-size: 36px; border: none; }
        #overlay { position: fixed; display: none; width: 100%; height: 100%; top: 0; left: 0; background: rgba(0,0,0,0.5); z-index: 150; }

        /* Contenedor Principal */
        .container { padding: 20px; max-width: 600px; margin: 0 auto; }
        
        /* Tarjeta de Chat */
        .chat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); border-top: 5px solid var(--gold); }
        h2 { margin-top: 0; color: var(--primary); font-size: 1.2rem; display: flex; align-items: center; gap: 10px; }
        
        textarea { width: 100%; padding: 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; margin: 15px 0; min-height: 100px; resize: none; font-family: inherit; background: #fafafa; }
        textarea:focus { border-color: var(--primary); outline: none; background: white; }
        
        .btn-consultar { width: 100%; padding: 15px; background: var(--primary); color: white; border: none; border-radius: 8px; font-weight: bold; font-size: 1rem; cursor: pointer; transition: background 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-consultar:active { transform: scale(0.98); }
        .btn-consultar:disabled { background: #ccc; cursor: wait; }

        /* Área de Respuesta */
        #box_respuesta { margin-top: 25px; padding: 20px; background: #e8f5e9; border-radius: 8px; border-left: 5px solid var(--primary); display: none; line-height: 1.6; font-size: 0.95rem; color: #2e7d32; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .loading-text { text-align: center; color: #666; font-style: italic; margin-top: 15px; display: none; }
        
        /* Botón flotante para volver */
        .float-btn { position: fixed; bottom: 20px; right: 20px; background: var(--gold); color: var(--primary); width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; text-decoration: none; box-shadow: 0 4px 10px rgba(0,0,0,0.3); font-weight: bold; }
    </style>
</head>
<body>

    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">×</a>
        <div style="text-align:center; color:var(--gold); font-weight:bold; margin-bottom:20px;">MENÚ GOLDFRUITS</div>
        <a href="nuevo_acopio.php">➕ Nueva Operación</a>
        <a href="mis_solicitudes.php">📂 Mis Solicitudes</a>
        <a href="ia_panel.php" style="color:white; background:#222;">🤖 Consultor IA</a>
        <a href="../logout.php" style="color:#ff5252;">🚪 Salir</a>
    </div>
    <div id="overlay" onclick="closeNav()"></div>

    <div class="app-bar">
        <span class="menu-btn" onclick="openNav()">☰</span>
        <h1 style="margin:0; font-size:1.2rem;">Consultor Inteligente</h1>
    </div>

    <div class="container">
        <div class="chat-card">
            <h2>🤖 Asistente GoldFruits</h2>
            <p style="color:#666; font-size:0.9rem;">Consulta sobre acopios, deudas a proveedores, kilos netos o estadísticas recientes.</p>
            
            <textarea id="pregunta_ia" placeholder="Ej: ¿Cuál fue el último acopio? o ¿Cuánto se pagó en total la semana pasada?"></textarea>
            
            <button class="btn-consultar" type="button" onclick="preguntarIA()">CONSULTAR AHORA</button>
            
            <div id="loading" class="loading-text">⏳ Analizando base de datos en tiempo real...</div>
            
            <div id="box_respuesta">
                <strong style="display:block; margin-bottom:10px;">Respuesta:</strong>
                <span id="texto_respuesta"></span>
            </div>
        </div>
    </div>
    
    <a href="mis_solicitudes.php" class="float-btn">↩</a>

    <script>
        function openNav(){ document.getElementById("mySidebar").style.width="250px"; document.getElementById("overlay").style.display="block"; }
        function closeNav(){ document.getElementById("mySidebar").style.width="0"; document.getElementById("overlay").style.display="none"; }

        function preguntarIA() {
            const btn = document.querySelector('.btn-consultar');
            const pregunta = document.getElementById('pregunta_ia').value.trim();
            const box = document.getElementById('box_respuesta');
            const txt = document.getElementById('texto_respuesta');
            const load = document.getElementById('loading');

            if(!pregunta) { alert("Por favor escribe una pregunta."); return; }

            // Estado de carga UI
            btn.disabled = true;
            btn.style.opacity = "0.7";
            load.style.display = "block";
            box.style.display = "none";
            txt.innerHTML = "";

            fetch('procesar_ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pregunta: pregunta })
            })
            .then(r => r.json())
            .then(data => {
                load.style.display = "none";
                box.style.display = "block";
                
                if(data.error) {
                    txt.innerHTML = "<span style='color:#d32f2f; font-weight:bold;'>⚠️ " + data.error + "</span>";
                } else {
                    // Formatear negritas de Markdown a HTML si la IA las envía
                    let respuestaLimpia = data.respuesta.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
                    respuestaLimpia = respuestaLimpia.replace(/\n/g, "<br>");
                    txt.innerHTML = respuestaLimpia;
                }
            })
            .catch(e => {
                load.style.display = "none";
                alert("Error de conexión con el servidor.");
                console.error(e);
            })
            .finally(() => {
                btn.disabled = false;
                btn.style.opacity = "1";
            });
        }
    </script>
</body>
</html>