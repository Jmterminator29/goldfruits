<?php
// guardar_goldfruits.php
// OPTIMIZADO: Inserción Masiva (Bulk Insert) para pesadas + Fotos Originales

header("Access-Control-Allow-Origin: *");
session_start();
require_once '../includes/db_connect.php'; // Asegúrate que esta ruta sea correcta según tu estructura

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Error: Sesión no iniciada.");
}

// --- Funciones de Ayuda ---
function gf_num($v) {
    if ($v === null) return 0;
    if (is_string($v)) {
        $v = str_replace(['S/', 's/', ' ', ','], ['', '', '', ''], $v);
    }
    return is_numeric($v) ? (float)$v : 0.0;
}

function gf_norm($s) {
    $s = (string)$s;
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}

function gf_has_column(PDO $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function upsertProveedor(PDO $conn, string $nombre, string $cuenta = ''): int {
    $nombre = trim($nombre);
    if ($nombre === '') return 0;

    $stmtSel = $conn->prepare("SELECT id, cuenta_bancaria FROM proveedores WHERE nombre = ? LIMIT 1");
    $stmtSel->execute([$nombre]);
    $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['id'])) {
        $id = (int)$row['id'];
        if ($cuenta !== '' && (string)$row['cuenta_bancaria'] !== $cuenta) {
            $stmtUpd = $conn->prepare("UPDATE proveedores SET cuenta_bancaria = ? WHERE id = ?");
            $stmtUpd->execute([$cuenta, $id]);
        }
        return $id;
    }

    $stmtIns = $conn->prepare("INSERT INTO proveedores (nombre, cuenta_bancaria, activo, created_at) VALUES (?, ?, 1, NOW())");
    $stmtIns->execute([$nombre, $cuenta !== '' ? $cuenta : null]);
    return (int)$conn->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

try {
    // Aumentar memoria y tiempo para cargas grandes
    ini_set('memory_limit', '256M');
    set_time_limit(300);

    if (!$conn->inTransaction()) $conn->beginTransaction();

    $uid = (int)$_SESSION['user_id'];

    // 1. RECEPCIÓN DE DATOS
    $origenes_json = $_POST['origenes_json'] ?? '[]';
    $origenes_array = json_decode($origenes_json, true);
    if (!is_array($origenes_array)) $origenes_array = [];
    
    $detalles = json_decode($_POST['detalle_pesadas_json'] ?? '[]', true);
    if (!is_array($detalles)) $detalles = [];

    // 2. CÁLCULOS (Lógica de Negocio)
    $proveedor_lista = [];
    $importe_total_global = 0.0;
    
    $sum_k1 = 0; $sum_din1 = 0;
    $sum_k2 = 0; $sum_din2 = 0;
    $sum_kr = 0; $sum_dinr = 0;
    $total_peso_calc = 0.0;

    foreach ($origenes_array as $ori) {
        $n = trim((string)($ori['proveedor'] ?? ''));
        if ($n !== '') $proveedor_lista[] = $n;

        $k1 = gf_num($ori['kilos']['k1'] ?? 0);
        $k2 = gf_num($ori['kilos']['k2'] ?? 0);
        $kr = gf_num($ori['kilos']['kr'] ?? 0);
        
        $p1 = gf_num($ori['precios']['p1'] ?? 0);
        $p2 = gf_num($ori['precios']['p2'] ?? 0);
        $pr = gf_num($ori['precios']['pr'] ?? 0);
        
        $sub = ($k1*$p1) + ($k2*$p2) + ($kr*$pr);
        $importe_total_global += $sub;
        
        $sum_k1 += $k1; $sum_din1 += ($k1*$p1);
        $sum_k2 += $k2; $sum_din2 += ($k2*$p2);
        $sum_kr += $kr; $sum_dinr += ($kr*$pr);
        $total_peso_calc += ($k1 + $k2 + $kr);
    }
    
    $proveedor_lista = array_values(array_unique($proveedor_lista));
    $proveedor_principal = !empty($proveedor_lista) ? implode(', ', $proveedor_lista) : "Desconocido";

    $total_jabas = 0;
    foreach ($detalles as $d) $total_jabas += (int)gf_num($d['jabas'] ?? 0);

    $avg_p1 = $sum_k1 > 0 ? ($sum_din1 / $sum_k1) : 0;
    $avg_p2 = $sum_k2 > 0 ? ($sum_din2 / $sum_k2) : 0;
    $avg_pr = $sum_kr > 0 ? ($sum_dinr / $sum_kr) : 0;
    $precio_x_kg = $total_peso_calc > 0 ? ($importe_total_global / $total_peso_calc) : 0;

    // 3. INSERT CABECERA (Una sola inserción)
    $has_prov_comercial = gf_has_column($conn, 'acopios_cabecera', 'proveedor_comercial');

    $cols = [
        'usuario_id','codigo_unico','proveedor','origenes_detalle','cuenta_bancaria',
        'conductor','placa','precio_flete','adelanto_flete',
        'total_jabas','total_peso_bruto','total_kilos_neto',
        'precio_x_kg','importe_total_fruta',
        'total_cat1','precio_cat1',
        'total_cat2','precio_cat2',
        'total_rastrojo','precio_rastrojo',
        'cosecha_personas','cosecha_dias','cosecha_precio','subtotal_cosecha',
        'cargadores_personas','cargadores_dias','cargadores_precio','subtotal_cargadores',
        'inspectores_personas','inspectores_dias','inspectores_precio','subtotal_inspectores',
        'viaticos','gastos_operativos','latitud','longitud'
    ];
    if ($has_prov_comercial) array_splice($cols, 3, 0, ['proveedor_comercial']);

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO acopios_cabecera (" . implode(',', $cols) . ") VALUES (" . $placeholders . ")";
    $stmt = $conn->prepare($sql);

    $params = [
        $uid, $_POST['codigo_unico'] ?? ('GF-' . time()), $proveedor_principal,
    ];
    if ($has_prov_comercial) $params[] = null;

    $params = array_merge($params, [
        $origenes_json,
        $_POST['cuenta'] ?? '',
        $_POST['conductor_nombre'] ?? '',
        $_POST['vehiculo_placa'] ?? '',
        gf_num($_POST['flete'] ?? 0),
        gf_num($_POST['adelanto_flete'] ?? 0),
        $total_jabas,
        $total_peso_calc, 
        $total_peso_calc, 
        $precio_x_kg,
        $importe_total_global, 
        $sum_k1, $avg_p1,
        $sum_k2, $avg_p2,
        $sum_kr, $avg_pr,
        (int)gf_num($_POST['cosecha_personas'] ?? 0), (int)gf_num($_POST['cosecha_dias'] ?? 0), gf_num($_POST['cosecha_precio'] ?? 0), gf_num($_POST['sub_cosecha'] ?? 0),
        (int)gf_num($_POST['cargadores_personas'] ?? 0), (int)gf_num($_POST['cargadores_dias'] ?? 0), gf_num($_POST['cargadores_precio'] ?? 0), gf_num($_POST['sub_cargadores'] ?? 0),
        (int)gf_num($_POST['inspectores_personas'] ?? 0), (int)gf_num($_POST['inspectores_dias'] ?? 0), gf_num($_POST['inspectores_precio'] ?? 0), gf_num($_POST['sub_inspectores'] ?? 0),
        gf_num($_POST['viaticos'] ?? 0),
        gf_num($_POST['operativos'] ?? 0),
        $_POST['latitud'] ?? '',
        $_POST['longitud'] ?? ''
    ]);

    $stmt->execute($params);
    $acopio_id = (int)$conn->lastInsertId();

    // 4. INSERT PROVEEDORES (Se mantiene individual porque necesitamos el ID para las pesadas)
    // No son muchos registros, así que no afecta la velocidad.
    $has_details = gf_has_column($conn, 'acopios_origenes', 'k_cat1');
    $has_tara = gf_has_column($conn, 'acopios_origenes', 'tara_asignada');

    $sqlOri = "INSERT INTO acopios_origenes (acopio_id, proveedor_id, campo";
    $valOri = " VALUES (?, ?, ?";
    if ($has_details) { $sqlOri .= ", k_cat1, p_cat1, k_cat2, p_cat2, k_rastrojo, p_rastrojo, subtotal"; $valOri .= ", ?, ?, ?, ?, ?, ?, ?"; }
    if ($has_tara) { $sqlOri .= ", tara_asignada"; $valOri .= ", ?"; }
    $sqlOri .= ")"; $valOri .= ")";
    
    $stmtInsOrigen = $conn->prepare($sqlOri . $valOri);
    $mapOrigenNormToId = [];

    foreach ($origenes_array as $ori) {
        $p_nombre = trim((string)($ori['proveedor'] ?? ''));
        if ($p_nombre === '') continue;

        $p_campo  = trim((string)($ori['campo'] ?? ''));
        $p_cuenta = trim((string)($ori['cuenta'] ?? ($_POST['cuenta'] ?? '')));
        $prov_id = upsertProveedor($conn, $p_nombre, $p_cuenta);

        $paramsOri = [ $acopio_id, $prov_id > 0 ? $prov_id : null, ($p_campo !== '' ? $p_campo : null) ];

        if ($has_details) {
            $k1 = gf_num($ori['kilos']['k1'] ?? 0); $p1 = gf_num($ori['precios']['p1'] ?? 0);
            $k2 = gf_num($ori['kilos']['k2'] ?? 0); $p2 = gf_num($ori['precios']['p2'] ?? 0);
            $kr = gf_num($ori['kilos']['kr'] ?? 0); $pr = gf_num($ori['precios']['pr'] ?? 0);
            $sub = ($k1*$p1) + ($k2*$p2) + ($kr*$pr);
            array_push($paramsOri, $k1, $p1, $k2, $p2, $kr, $pr, $sub);
        }
        if ($has_tara) { array_push($paramsOri, gf_num($ori['tara'] ?? 1.6)); }

        $stmtInsOrigen->execute($paramsOri);
        $origen_id = (int)$conn->lastInsertId();

        $label = $p_nombre . (($p_campo !== '') ? (' - ' . $p_campo) : '');
        $mapOrigenNormToId[gf_norm($label)] = $origen_id;
        $mapOrigenNormToId[gf_norm($p_nombre)] = $origen_id;
    }

    // 5. INSERT PESADAS (OPTIMIZADO: BULK INSERT) 🚀
    // Aquí es donde ganamos la velocidad. Guardamos fotos una a una, pero insertamos SQL todo junto.
    
    // Configuración de fotos (Igual a tu código anterior)
    $carpeta = "fotos_acopio/"; // Ruta original, no se cambia.
    if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);
    
    $has_bruto = gf_has_column($conn, 'acopios_pesadas', 'peso_bruto');

    // Preparar arrays para Bulk Insert
    $values_pool = [];
    $placeholders_pool = [];
    
    foreach ($detalles as $i => $d) {
        // A. Subida de Foto (Se mantiene igual)
        $foto = "";
        if (isset($_FILES['fotos_pesadas']['name'][$i])) {
            $ext = pathinfo($_FILES['fotos_pesadas']['name'][$i], PATHINFO_EXTENSION);
            $name = "UID{$uid}_ID{$acopio_id}_T{$i}_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['fotos_pesadas']['tmp_name'][$i], $carpeta . $name)) {
                $foto = $carpeta . $name;
            }
        }

        // B. Mapeo de Origen
        $origen_ref = trim((string)($d['origen'] ?? ''));
        $origen_id = null;
        if ($origen_ref !== '') {
            $k = gf_norm($origen_ref);
            if (isset($mapOrigenNormToId[$k])) $origen_id = (int)$mapOrigenNormToId[$k];
            else {
                $k2 = gf_norm(str_replace(['(',')'], ['',''], $origen_ref));
                if (isset($mapOrigenNormToId[$k2])) $origen_id = (int)$mapOrigenNormToId[$k2];
            }
        }

        // C. Acumular datos para Bulk Insert
        $cat = (string)($d['categoria'] ?? 'cat1');
        
        // Campos base
        array_push($values_pool, 
            $acopio_id, 
            $origen_id, 
            $i + 1, 
            (int)gf_num($d['jabas'] ?? 0), 
            gf_num($d['peso'] ?? 0), 
            $foto, 
            $origen_ref, 
            $cat
        );
        
        // Placeholder base (?,?,?,?,?,?,?,?)
        $ph = "(?, ?, ?, ?, ?, ?, ?, ?"; 
        
        // Campo opcional peso_bruto
        if ($has_bruto) {
            array_push($values_pool, gf_num($d['peso_bruto'] ?? 0));
            $ph .= ", ?";
        }
        $ph .= ")";
        
        $placeholders_pool[] = $ph;
    }

    // EJECUCIÓN MASIVA (EL SECRETO DE LA VELOCIDAD)
    if (!empty($placeholders_pool)) {
        // Insertamos en bloques de 50 para no saturar si son muchísimas fotos
        $chunk_size = 50;
        $params_per_row = $has_bruto ? 9 : 8;
        $total_rows = count($placeholders_pool);

        // Construcción base del query
        $sqlBase = "INSERT INTO acopios_pesadas (acopio_id, origen_id, numero_tanda, jabas, peso, foto_url, origen_referencia, categoria";
        if ($has_bruto) $sqlBase .= ", peso_bruto";
        $sqlBase .= ") VALUES ";

        for ($i = 0; $i < $total_rows; $i += $chunk_size) {
            $current_placeholders = array_slice($placeholders_pool, $i, $chunk_size);
            $current_values = array_slice($values_pool, $i * $params_per_row, $chunk_size * $params_per_row);
            
            $sqlFinal = $sqlBase . implode(', ', $current_placeholders);
            $stmtBulk = $conn->prepare($sqlFinal);
            $stmtBulk->execute($current_values);
        }
    }

    if ($conn->inTransaction()) $conn->commit();
    echo "✅ Registro Exitoso (Velocidad Turbo 🚀)";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>