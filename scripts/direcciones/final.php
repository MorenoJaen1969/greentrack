<?php
/**
 * SCRIPT DE CORRECCIÓN DE DIRECCIONES SEGÚN EXCEL DEL CLIENTE
 * VERSIÓN CON ID_DIRECCION EN LOG
 */

// ============================================
// CONFIGURACIÓN
// ============================================

$procesar = TRUE; // ← CAMBIAR A TRUE CUANDO ESTÉS LISTO

$db_config = [
    'host' => 'localhost',
    'user' => 'mmoreno',
    'pass' => 'Noloseno#2017',
    'name' => 'greentrack_live'
];

$log_config = [
    'dir' => __DIR__,
    'prefix' => 'correccion_excel_',
    'date_format' => 'Ymd_His'
];

// Configuración de APIs
$apis = [
    'opencage' => [
        'nombre' => 'OpenCage',
        'url' => 'https://api.opencagedata.com/geocode/v1/json',
        'key' => 'e9cfb998b3a84cbd932923b3cff0e96e',
        'activa' => true
    ],
    'geoapify' => [
        'nombre' => 'GeoApify',
        'url' => 'https://api.geoapify.com/v1/geocode/search',
        'key' => '7064f603ca67459d916a909b74bca1cb',
        'activa' => true
    ],
    'locationiq' => [
        'nombre' => 'LocationIQ',
        'url' => 'https://us1.locationiq.com/v1/search.php',
        'key' => 'pk.1472af9e389d1d577738a28c25b3e620',
        'activa' => true
    ]
];

// ============================================
// DATOS DESDE EXCEL
// ============================================

$correcciones = [
    ['id_cliente' => 1110, 'direccion_excel' => '8420 Kings View Ct, Montgomery, TX 77316'],
    ['id_cliente' => 1091, 'direccion_excel' => '5223 Sunshine Point, Willis, TX 77318'],
    ['id_cliente' => 1133, 'direccion_excel' => '29 Winged Foot Dr, Conroe, TX 77304'],
    ['id_cliente' => 1156, 'direccion_excel' => '18 Silver Lute PL, The Woodlands, TX 77381'],
    ['id_cliente' => 1127, 'direccion_excel' => '2912 WEST DAVIS CONROE, TX 77304'],
    ['id_cliente' => 1148, 'direccion_excel' => '2800 N FRAZIER ST CONROE TEXAS'],
    ['id_cliente' => 1053, 'direccion_excel' => '750 INTERSTATE 45 S, CONROE TEXAS 77301, USA'],
    ['id_cliente' => 1179, 'direccion_excel' => '9870 POZOS LN, CONROE TEXAS 77301, USA', 'fusionar' => 1181],
    ['id_cliente' => 1181, 'direccion_excel' => '9870 POZOS LN, CONROE TEXAS 77301, USA'],
    ['id_cliente' => 1015, 'direccion_excel' => '11231 JAKE PEARSON RD. CONROE, TX. 77304'],
    ['id_cliente' => 1083, 'direccion_excel' => '11387 JAKE PEARSON RD. CONROE, TX. 77304'],
    ['id_cliente' => 1035, 'direccion_excel' => '12329 LONGMIRE COVE TX., CONROE, TX 77304'],
    ['id_cliente' => 1069, 'direccion_excel' => '12323 LONGMIRE COVE TX., CONROE, TX 77304'],
    ['id_cliente' => 1111, 'direccion_excel' => '360 green cove dr, montgoery, texas 77356'],
    ['id_cliente' => 1176, 'direccion_excel' => '25415 OAKHURST DR, SPRING TEXAS 77386, USA'],
    ['id_cliente' => 1005, 'direccion_excel' => '13050 POINT AQUARIUS BLVD. WILLIS, TX 77318'],
    ['id_cliente' => 1129, 'direccion_excel' => '2803 HEMINGWAY DRIVE MONTGOMERY TEXAS 77356'],
    ['id_cliente' => 1095, 'direccion_excel' => '10586 HWY 75 N. WILLIS, TX. 77378'],
    ['id_cliente' => 1150, 'direccion_excel' => '56 waterberry ct, mongomery, tx 77356'],
    ['id_cliente' => 1189, 'direccion_excel' => '10586 HWY 75 WILLIS TX'],
    
    ['id_cliente' => 1119, 'direccion_incorrecta' => '703 EVERETT ST, CONROE TEXAS, USA, USA 77301, USA', 'accion' => 'reasignar'],
    ['id_cliente' => 1129, 'direccion_incorrecta' => '945 SGT ED HOLCOMB BLVD CONROE TEXAS, USA, USA 77301, USA', 'accion' => 'reasignar']
];

// ============================================
// FUNCIONES
// ============================================

function writeLog($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $message . "\n";
}

function geocodificarDireccion($direccion, $apis, $log_file) {
    $resultados = [];
    
    foreach ($apis as $api_nombre => $api_config) {
        if (!$api_config['activa']) continue;
        
        $url = $api_config['url'];
        $key = $api_config['key'];
        
        switch ($api_nombre) {
            case 'opencage':
                $url .= '?q=' . urlencode($direccion) . '&key=' . $key . '&limit=1';
                break;
            case 'geoapify':
                $url .= '?text=' . urlencode($direccion) . '&apiKey=' . $key . '&limit=1';
                break;
            case 'locationiq':
                $url .= '?key=' . $key . '&q=' . urlencode($direccion) . '&format=json&limit=1';
                break;
        }
        
        writeLog("      🔄 Consultando {$api_config['nombre']}...", $log_file);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'GreenTrack-Correction/1.0',
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            writeLog("      ⚠️ {$api_config['nombre']} respondió {$http_code}", $log_file);
            continue;
        }
        
        $data = json_decode($response, true);
        
        $lat = null;
        $lng = null;
        
        switch ($api_nombre) {
            case 'opencage':
                if (isset($data['results'][0]['geometry'])) {
                    $lat = $data['results'][0]['geometry']['lat'];
                    $lng = $data['results'][0]['geometry']['lng'];
                }
                break;
            case 'geoapify':
                if (isset($data['features'][0]['geometry']['coordinates'])) {
                    $lng = $data['features'][0]['geometry']['coordinates'][0];
                    $lat = $data['features'][0]['geometry']['coordinates'][1];
                }
                break;
            case 'locationiq':
                if (isset($data[0]['lat'])) {
                    $lat = $data[0]['lat'];
                    $lng = $data[0]['lon'];
                }
                break;
        }
        
        if ($lat && $lng) {
            $resultados[] = ['api' => $api_nombre, 'lat' => $lat, 'lng' => $lng];
            writeLog("      ✅ {$api_config['nombre']}: {$lat}, {$lng}", $log_file);
        }
        
        sleep(1);
    }
    
    if (count($resultados) > 0) {
        $sum_lat = 0;
        $sum_lng = 0;
        foreach ($resultados as $r) {
            $sum_lat += $r['lat'];
            $sum_lng += $r['lng'];
        }
        return [
            'lat' => $sum_lat / count($resultados),
            'lng' => $sum_lng / count($resultados),
            'apis_ok' => count($resultados)
        ];
    }
    
    return null;
}

// ============================================
// CONEXIÓN A BASE DE DATOS
// ============================================

$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['pass'],
    $db_config['name']
);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error . "\n");
}

$log_file = $log_config['dir'] . '/' . $log_config['prefix'] . date($log_config['date_format']) . '.log';

writeLog("==================================================", $log_file);
writeLog("🚀 CORRECCIÓN DE DIRECCIONES SEGÚN EXCEL", $log_file);
writeLog("📋 Modo: " . ($procesar ? "REAL" : "SIMULACIÓN"), $log_file);
writeLog("==================================================", $log_file);

// ============================================
// 1. PROCESAR ACTUALIZACIONES DE DIRECCIÓN (CON ID)
// ============================================

writeLog("", $log_file);
writeLog("📝 ACTUALIZANDO DIRECCIONES DE CLIENTES...", $log_file);

$actualizadas = 0;
$geocodificadas = 0;

foreach ($correcciones as $item) {
    if (isset($item['accion']) && $item['accion'] == 'reasignar') continue;
    if (!isset($item['direccion_excel'])) continue;
    
    $id_cliente = $item['id_cliente'];
    $direccion_excel = $item['direccion_excel'];
    
    writeLog("────────────────────────────────────", $log_file);
    writeLog("📌 Cliente ID: {$id_cliente}", $log_file);
    writeLog("   Dirección Excel: {$direccion_excel}", $log_file);
    
    $buscar = $conn->query("
        SELECT id_direccion, direccion, lat, lng 
        FROM direcciones 
        WHERE id_cliente = {$id_cliente}
    ");
    
    if ($buscar->num_rows == 0) {
        writeLog("   ⚠️ Cliente no tiene dirección en BD - NADA QUE ACTUALIZAR", $log_file);
        continue;
    }
    
    $dir_actual = $buscar->fetch_assoc();
    $id_direccion = $dir_actual['id_direccion'];
    
    writeLog("   🆔 ID Dirección: {$id_direccion}", $log_file);
    writeLog("   📍 Dirección actual BD: {$dir_actual['direccion']}", $log_file);
    
    writeLog("   🌍 Geocodificando nueva dirección...", $log_file);
    $coordenadas = geocodificarDireccion($direccion_excel, $apis, $log_file);
    
    if (!$coordenadas) {
        writeLog("   ❌ No se pudo geocodificar - se omite", $log_file);
        continue;
    }
    
    $geocodificadas++;
    writeLog("   📍 Coordenadas promedio: {$coordenadas['lat']}, {$coordenadas['lng']} ({$coordenadas['apis_ok']} APIs)", $log_file);
    
    if ($procesar) {
        $stmt = $conn->prepare("
            UPDATE direcciones 
            SET direccion = ?, lat = ?, lng = ? 
            WHERE id_direccion = ?
        ");
        $stmt->bind_param("sddi", $direccion_excel, $coordenadas['lat'], $coordenadas['lng'], $id_direccion);
        $stmt->execute();
        $stmt->close();
        writeLog("   ✅ DIRECCIÓN ACTUALIZADA (ID {$id_direccion})", $log_file);
        $actualizadas++;
    } else {
        writeLog("   📝 [SIMULACIÓN] Se actualizaría dirección ID {$id_direccion}", $log_file);
        writeLog("      Nueva dirección: {$direccion_excel}", $log_file);
        writeLog("      Nuevas coordenadas: {$coordenadas['lat']}, {$coordenadas['lng']}", $log_file);
    }
}

// ============================================
// 2. PROCESAR REASIGNACIONES
// ============================================

writeLog("", $log_file);
writeLog("🔄 REASIGNANDO CONTRATOS/SERVICIOS...", $log_file);

$reasignadas = 0;

foreach ($correcciones as $item) {
    if (!isset($item['accion']) || $item['accion'] != 'reasignar') continue;
    
    $id_cliente_incorrecto = $item['id_cliente'];
    $direccion_incorrecta = $item['direccion_incorrecta'];
    
    writeLog("────────────────────────────────────", $log_file);
    writeLog("📌 Cliente INCORRECTO: {$id_cliente_incorrecto}", $log_file);
    writeLog("   Dirección: {$direccion_incorrecta}", $log_file);
    
    $buscar_dir = $conn->query("
        SELECT id_direccion 
        FROM direcciones 
        WHERE direccion = '{$conn->real_escape_string($direccion_incorrecta)}'
    ");
    
    if ($buscar_dir->num_rows == 0) {
        writeLog("   ⚠️ No se encontró la dirección en BD", $log_file);
        continue;
    }
    
    $id_direccion = $buscar_dir->fetch_assoc()['id_direccion'];
    
    // Buscar cliente correcto (el que tiene la misma dirección en Excel)
    $cliente_correcto = null;
    foreach ($correcciones as $c) {
        if (isset($c['direccion_excel']) && stripos($c['direccion_excel'], explode(',', $direccion_incorrecta)[0]) !== false) {
            $cliente_correcto = $c['id_cliente'];
            break;
        }
    }
    
    if (!$cliente_correcto) {
        writeLog("   ⚠️ No se encontró el cliente correcto", $log_file);
        continue;
    }
    
    writeLog("   🆔 ID Dirección: {$id_direccion}", $log_file);
    writeLog("   Cliente CORRECTO: {$cliente_correcto}", $log_file);
    
    if ($procesar) {
        $conn->query("
            UPDATE IGNORE contratos 
            SET id_cliente = {$cliente_correcto} 
            WHERE id_cliente = {$id_cliente_incorrecto} 
              AND id_direccion = {$id_direccion}
        ");
        writeLog("   ✅ Contratos reasignados: " . $conn->affected_rows, $log_file);
        
        $conn->query("
            UPDATE IGNORE servicios 
            SET id_cliente = {$cliente_correcto} 
            WHERE id_cliente = {$id_cliente_incorrecto} 
              AND id_direccion = {$id_direccion}
        ");
        writeLog("   ✅ Servicios reasignados: " . $conn->affected_rows, $log_file);
        
        $reasignadas++;
    } else {
        writeLog("   📝 [SIMULACIÓN] Se reasignarían contratos/servicios", $log_file);
    }
}

// ============================================
// 3. FUSIONAR CLIENTES
// ============================================

writeLog("", $log_file);
writeLog("🔗 FUSIONANDO CLIENTES DUPLICADOS...", $log_file);

$fusiones = 0;

foreach ($correcciones as $item) {
    if (!isset($item['fusionar'])) continue;
    
    $id_eliminar = $item['id_cliente'];
    $id_conservar = $item['fusionar'];
    
    writeLog("────────────────────────────────────", $log_file);
    writeLog("📌 Fusionar cliente {$id_eliminar} → {$id_conservar}", $log_file);
    
    if ($procesar) {
        $conn->query("UPDATE IGNORE contratos SET id_cliente = {$id_conservar} WHERE id_cliente = {$id_eliminar}");
        writeLog("   ✅ Contratos actualizados: " . $conn->affected_rows, $log_file);
        
        $conn->query("UPDATE IGNORE servicios SET id_cliente = {$id_conservar} WHERE id_cliente = {$id_eliminar}");
        writeLog("   ✅ Servicios actualizados: " . $conn->affected_rows, $log_file);
        
        $conn->query("UPDATE IGNORE direcciones SET id_cliente = {$id_conservar} WHERE id_cliente = {$id_eliminar}");
        writeLog("   ✅ Direcciones actualizadas: " . $conn->affected_rows, $log_file);
        
        $fusiones++;
    } else {
        writeLog("   📝 [SIMULACIÓN] Se fusionarían clientes", $log_file);
    }
}

// ============================================
// REPORTE FINAL
// ============================================

writeLog("", $log_file);
writeLog("==================================================", $log_file);
writeLog("📊 REPORTE FINAL", $log_file);
writeLog("==================================================", $log_file);
writeLog("   Direcciones actualizadas: {$actualizadas}", $log_file);
writeLog("   Direcciones geocodificadas: {$geocodificadas}", $log_file);
writeLog("   Reasignaciones realizadas: {$reasignadas}", $log_file);
writeLog("   Fusiones de clientes: {$fusiones}", $log_file);
writeLog("==================================================", $log_file);
writeLog("🏁 PROCESO FINALIZADO", $log_file);
writeLog("📋 Log generado: " . basename($log_file), $log_file);
writeLog("==================================================", $log_file);

$conn->close();

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Proceso completado\n";
echo "📊 Actualizadas: {$actualizadas}\n";
echo "📊 Reasignadas: {$reasignadas}\n";
echo "📊 Fusiones: {$fusiones}\n";
echo "📋 Log: " . basename($log_file) . "\n";
echo str_repeat("=", 50) . "\n";
?>