<?php
// actualizar_goldfruits.php
// FIX v5: Edición con TARA, PESO BRUTO y LIQUIDACIÓN POR PROVEEDOR
// - Guarda 'tara_asignada' y 'peso_bruto' si las columnas existen.
// - Recalcula totales financieros basados en la suma de proveedores.
// - Mantiene gestión de fotos (nuevas y antiguas).

header("Access-Control-Allow-Origin: *");
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Error: Sesión no iniciada.");
}

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

// Verificar columnas dinámicamente para evitar errores si la BD no se actualizó
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
    die("Método no permitido");
}

try {
    if (!$conn->inTransaction()) $conn->beginTransaction();

    $uid = (int)$_SESSION['user_id'];
    $id  = (int)($_POST['id_acopio'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido");

    // Permisos
    $chk = $conn->prepare("SELECT usuario_id FROM acopios_cabecera WHERE id = ?");
    $chk->execute([$id]);
    $owner = $chk->fetchColumn();
    if (!$owner || (int)$owner !== $uid) {
        throw new Exception("No tienes permiso para editar este registro.");
    }

    // 1. Recibir Datos
    // -----------------------------------------------------
    $origenes_json = $_POST['origenes_json'] ?? '[]';
    $origenes_array = json_decode($origenes_json, true);
    if (!is_array($origenes_array)) $origenes_array = [];

    $detalles = json_decode($_POST['detalle_pesadas_json'] ?? '[]', true);
    if (!is_array($detalles)) $detalles = [];


    // 2. Calcular Totales (Financieros + Kilos Netos)
    // -----------------------------------------------------
    $proveedores_nombres = [];
    $importe_total_global = 0.0;
    
    // Acumuladores globales
    $sum_k1 = 0; $sum_din1 = 0;
    $sum_k2 = 0; $sum_din2 = 0;
    $sum_kr = 0; $sum_dinr = 0;
    $total_peso_calc = 0.0;

    foreach ($origenes_array as $ori) {
        $n = trim((string)($ori['proveedor'] ?? ''));
        if ($n !== '') $proveedores_nombres[] = $n;

        // Extraer datos calculados en JS (Kilos NETOS)
        $k1 = gf_num($ori['kilos']['k1'] ?? 0);
        $k2 = gf_num($ori['kilos']['k2'] ?? 0);
        $kr = gf_num($ori['kilos']['kr'] ?? 0);
        
        $p1 = gf_num($ori['precios']['p1'] ?? 0);
        $p2 = gf_num($ori['precios']['p2'] ?? 0);
        $pr = gf_num($ori['precios']['pr'] ?? 0);
        
        // Subtotal de este proveedor
        $sub = ($k1*$p1) + ($k2*$p2) + ($kr*$pr);
        $importe_total_global += $sub;
        
        // Acumular globales
        $sum_k1 += $k1; $sum_din1 += ($k1*$p1);
        $sum_k2 += $k2; $sum_din2 += ($k2*$p2);
        $sum_kr += $kr; $sum_dinr += ($kr*$pr);
        $total_peso_calc += ($k1 + $k2 + $kr);
    }

    // Proveedor Texto
    $proveedores_nombres = array_values(array_unique($proveedores_nombres));
    $proveedor_texto = '';
    if (!empty($proveedores_nombres)) {
        $proveedor_texto = implode(', ', $proveedores_nombres);
    } elseif (!empty($_POST['proveedor'])) {
        $proveedor_texto = trim((string)$_POST['proveedor']);
    } else {
        $proveedor_texto = 'Desconocido';
    }
    $proveedor_principal = $proveedores_nombres[0] ?? $proveedor_texto; 

    // Jabas (referencial desde pesadas)
    $total_jabas = 0;
    foreach ($detalles as $d) {
        $total_jabas += (int)gf_num($d['jabas'] ?? 0);
    }

    // Promedios globales
    $avg_p1 = $sum_k1 > 0 ? ($sum_din1 / $sum_k1) : 0;
    $avg_p2 = $sum_k2 > 0 ? ($sum_din2 / $sum_k2) : 0;
    $avg_pr = $sum_kr > 0 ? ($sum_dinr / $sum_kr) : 0;
    $precio_x_kg = $total_peso_calc > 0 ? ($importe_total_global / $total_peso_calc) : 0;


    // 3. Actualizar Cabecera
    // -----------------------------------------------------
    $has_prov_comercial = gf_has_column($conn, 'acopios_cabecera', 'proveedor_comercial');

    $cols = [
        'proveedor = ?', 'origenes_detalle = ?', 'cuenta_bancaria = ?',
        'conductor = ?', 'placa = ?', 'precio_flete = ?', 'adelanto_flete = ?',
        'total_jabas = ?', 'total_peso_bruto = ?', 'total_kilos_neto = ?',
        'precio_x_kg = ?', 'importe_total_fruta = ?',
        'total_cat1 = ?', 'precio_cat1 = ?',
        'total_cat2 = ?', 'precio_cat2 = ?',
        'total_rastrojo = ?', 'precio_rastrojo = ?',
        'cosecha_personas = ?', 'cosecha_dias = ?', 'cosecha_precio = ?', 'subtotal_cosecha = ?',
        'cargadores_personas = ?', 'cargadores_dias = ?', 'cargadores_precio = ?', 'subtotal_cargadores = ?',
        'inspectores_personas = ?', 'inspectores_dias = ?', 'inspectores_precio = ?', 'subtotal_inspectores = ?',
        'viaticos = ?', 'gastos_operativos = ?', 'latitud = ?', 'longitud = ?'
    ];
    // NOTA: No necesitamos actualizar 'proveedor_comercial' si existe, ya que no se usa.

    $sqlUp = "UPDATE acopios_cabecera SET " . implode(', ', $cols) . " WHERE id = ? AND usuario_id = ?";

    $stmtUp = $conn->prepare($sqlUp);
    $stmtUp->execute([
        $proveedor_principal,
        $origenes_json,
        $_POST['cuenta'] ?? '',

        $_POST['conductor_nombre'] ?? '',
        $_POST['vehiculo_placa'] ?? '',
        gf_num($_POST['flete'] ?? 0),
        gf_num($_POST['adelanto_flete'] ?? 0),

        $total_jabas,
        $total_peso_calc, // Bruto Referencial en Cabecera (Usamos Neto acumulado)
        $total_peso_calc, // Neto
        $precio_x_kg,
        $importe_total_global, // <-- Total real sumado

        $sum_k1, $avg_p1,
        $sum_k2, $avg_p2,
        $sum_kr, $avg_pr,

        (int)gf_num($_POST['cosecha_personas'] ?? 0), (int)gf_num($_POST['cosecha_dias'] ?? 0), gf_num($_POST['cosecha_precio'] ?? 0), gf_num($_POST['sub_cosecha'] ?? 0),
        (int)gf_num($_POST['cargadores_personas'] ?? 0), (int)gf_num($_POST['cargadores_dias'] ?? 0), gf_num($_POST['cargadores_precio'] ?? 0), gf_num($_POST['sub_cargadores'] ?? 0),
        (int)gf_num($_POST['inspectores_personas'] ?? 0), (int)gf_num($_POST['inspectores_dias'] ?? 0), gf_num($_POST['inspectores_precio'] ?? 0), gf_num($_POST['sub_inspectores'] ?? 0),

        gf_num($_POST['viaticos'] ?? 0),
        gf_num($_POST['operativos'] ?? 0),
        $_POST['latitud'] ?? '',
        $_POST['longitud'] ?? '',
        $id,
        $uid
    ]);


    // 4. Actualizar Orígenes (Reemplazo Total + Tara)
    // -----------------------------------------------------
    $conn->prepare("DELETE FROM acopios_origenes WHERE acopio_id = ?")->execute([$id]);

    $has_details = gf_has_column($conn, 'acopios_origenes', 'k_cat1');
    $has_tara = gf_has_column($conn, 'acopios_origenes', 'tara_asignada');

    // Construcción dinámica INSERT
    $sqlOri = "INSERT INTO acopios_origenes (acopio_id, proveedor_id, campo";
    $valOri = " VALUES (?, ?, ?";
    
    if ($has_details) { $sqlOri .= ", k_cat1, p_cat1, k_cat2, p_cat2, k_rastrojo, p_rastrojo, subtotal"; $valOri .= ",?,?,?,?,?,?,?"; }
    if ($has_tara)    { $sqlOri .= ", tara_asignada"; $valOri .= ",?"; }
    $sqlOri .= ")"; $valOri .= ")";

    $stmtInsOrigen = $conn->prepare($sqlOri . $valOri);
    $mapOrigenNormToId = [];

    foreach ($origenes_array as $ori) {
        $p_nombre = trim((string)($ori['proveedor'] ?? ''));
        $p_campo  = trim((string)($ori['campo'] ?? ''));
        $p_cuenta = trim((string)($ori['cuenta'] ?? ($_POST['cuenta'] ?? '')));

        if ($p_nombre === '') continue;

        $prov_id = upsertProveedor($conn, $p_nombre, $p_cuenta);

        // Params base
        $paramsOri = [$id, $prov_id > 0 ? $prov_id : null, ($p_campo !== '' ? $p_campo : null)];

        if($has_details) {
            $k1=gf_num($ori['kilos']['k1']??0); $p1=gf_num($ori['precios']['p1']??0);
            $k2=gf_num($ori['kilos']['k2']??0); $p2=gf_num($ori['precios']['p2']??0);
            $kr=gf_num($ori['kilos']['kr']??0); $pr=gf_num($ori['precios']['pr']??0);
            $sub = ($k1*$p1)+($k2*$p2)+($kr*$pr);
            array_push($paramsOri, $k1, $p1, $k2, $p2, $kr, $pr, $sub);
        }
        
        if($has_tara) {
            array_push($paramsOri, gf_num($ori['tara'] ?? 1.6));
        }

        $stmtInsOrigen->execute($paramsOri);
        $origen_id = (int)$conn->lastInsertId();

        $label = $p_nombre . (($p_campo !== '') ? (' - ' . $p_campo) : '');
        $mapOrigenNormToId[gf_norm($label)] = $origen_id;
        $mapOrigenNormToId[gf_norm($p_nombre)] = $origen_id;
    }


    // 5. Actualizar Pesadas (Reemplazo Total + Peso Bruto)
    // -----------------------------------------------------
    $conn->prepare("DELETE FROM acopios_pesadas WHERE acopio_id = ?")->execute([$id]);

    $carpeta = "fotos_acopio/";
    if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

    $has_bruto = gf_has_column($conn, 'acopios_pesadas', 'peso_bruto');

    $sqlDet = "INSERT INTO acopios_pesadas (acopio_id, origen_id, numero_tanda, jabas, peso, foto_url, origen_referencia, categoria";
    if($has_bruto) $sqlDet .= ", peso_bruto";
    $sqlDet .= ") VALUES (?,?,?,?,?,?,?,?";
    if($has_bruto) $sqlDet .= ",?";
    $sqlDet .= ")";

    $stmtDet = $conn->prepare($sqlDet);

    foreach ($detalles as $index => $item) {
        $tanda = $index + 1;

        // Foto existente
        $ruta_final = (string)($item['foto_url'] ?? '');

        // === FOTO NUEVA ===
        if (!empty($item['es_nueva_fila'])) {
            $keyPost = 'foto_file_' . $index;
            if (isset($_FILES[$keyPost]) && $_FILES[$keyPost]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$keyPost]['name'], PATHINFO_EXTENSION);
                $nombre_final = "ID{$id}_TANDA{$tanda}_" . time() . "." . $ext;
                $destino = $carpeta . $nombre_final;
                if (move_uploaded_file($_FILES[$keyPost]['tmp_name'], $destino)) {
                    $ruta_final = $destino;
                }
            }
        }

        $cat = (string)($item['categoria'] ?? 'cat1');
        $origen_ref = trim((string)($item['origen'] ?? ''));
        $origen_id = null;

        if ($origen_ref !== '') {
            $k = gf_norm($origen_ref);
            if (isset($mapOrigenNormToId[$k])) $origen_id = (int)$mapOrigenNormToId[$k];
            else {
                $k2 = gf_norm(str_replace(['(',')'], ['',''], $origen_ref));
                if (isset($mapOrigenNormToId[$k2])) $origen_id = (int)$mapOrigenNormToId[$k2];
            }
        }

        $paramsDet = [
            $id,
            $origen_id,
            $tanda,
            (int)gf_num($item['jabas'] ?? 0),
            gf_num($item['peso'] ?? 0), // ESTO ES EL PESO NETO
            $ruta_final,
            $origen_ref,
            $cat
        ];
        
        if($has_bruto) {
            $paramsDet[] = gf_num($item['peso_bruto'] ?? 0);
        }

        $stmtDet->execute($paramsDet);
    }

    if ($conn->inTransaction()) $conn->commit();
    echo "✅ Actualización Exitosa";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>