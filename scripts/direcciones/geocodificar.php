<?php
/**
 * SCRIPT DE VALIDACIÓN Y GEOCODIFICACIÓN DE DIRECCIONES - VERSIÓN INTELIGENTE v6.1
 * GreenTrack - Smart Geocoding Script v6.1
 * 
 * CORRECIONES:
 * - Penalización para clusters de 1 API (ya no ganan automáticamente)
 * - Mejor cálculo de puntajes ponderados por tamaño del cluster
 * - Logging mejorado para seguimiento de decisiones
 */

// ============================================
// CONFIGURACIÓN INICIAL
// ============================================

// VARIABLE DE CONTROL: FALSE = simulación, TRUE = ejecución real
$procesar = TRUE;  // ← CAMBIAR A TRUE SOLO DESPUÉS DE PRUEBAS

// CONFIGURACIÓN BASE DE DATOS
$db_config = [
    'host' => 'localhost',
    'user' => 'mmoreno',
    'pass' => 'Noloseno#2017',
    'name' => 'greentrack_live'
];

// TOLERANCIAS (ajustadas según lo observado en logs)
$tolerancia_metros = 50;        // 50 metros para considerar coordenadas "iguales"
$tolerancia_cluster = 150;      // 150 metros para agrupar APIs en el mismo cluster
$min_apis_exitosas = 2;         // Mínimo de APIs que deben encontrar la dirección

// CONFIGURACIÓN DE LOGS
$log_config = [
    'dir' => __DIR__,
    'prefix' => 'geocoding_smart_v61_',
    'date_format' => 'Ymd_His'
];

// ============================================
// CONFIGURACIÓN DE APIS
// ============================================

$apis = [
    'opencage' => [
        'nombre' => 'OpenCage',
        'url' => 'https://api.opencagedata.com/geocode/v1/json',
        'key' => 'e9cfb998b3a84cbd932923b3cff0e96e',
        'timeout' => 10,
        'delay' => 0.5,
        'peso' => 1.0, // Peso en el consenso (normal)
        'parser' => function($data) {
            if (isset($data['results'][0]['geometry'])) {
                return [
                    'lat' => $data['results'][0]['geometry']['lat'],
                    'lng' => $data['results'][0]['geometry']['lng']
                ];
            }
            return null;
        }
    ],
    'geoapify' => [
        'nombre' => 'GeoApify',
        'url' => 'https://api.geoapify.com/v1/geocode/search',
        'key' => '7064f603ca67459d916a909b74bca1cb',
        'timeout' => 10,
        'delay' => 0.5,
        'peso' => 1.0,
        'parser' => function($data) {
            if (isset($data['features'][0]['geometry']['coordinates'])) {
                $coords = $data['features'][0]['geometry']['coordinates'];
                return [
                    'lat' => $coords[1],
                    'lng' => $coords[0]
                ];
            }
            return null;
        }
    ],
    'locationiq' => [
        'nombre' => 'LocationIQ',
        'url' => 'https://us1.locationiq.com/v1/search.php',
        'key' => 'pk.1472af9e389d1d577738a28c25b3e620',
        'timeout' => 10,
        'delay' => 0.5,
        'peso' => 1.2, // Peso extra por su precisión demostrada
        'parser' => function($data) {
            if (isset($data[0]['lat'])) {
                return [
                    'lat' => $data[0]['lat'],
                    'lng' => $data[0]['lon']
                ];
            }
            return null;
        }
    ],
    'nominatim' => [
        'nombre' => 'Nominatim (OSM)',
        'url' => 'https://nominatim.openstreetmap.org/search',
        'require_key' => false,
        'user_agent' => 'GreenTrack-Smart-Script/6.1',
        'timeout' => 10,
        'delay' => 1,
        'peso' => 0.8, // Peso ligeramente menor por su menor precisión en algunos casos
        'parser' => function($data) {
            if (isset($data[0]['lat'])) {
                return [
                    'lat' => $data[0]['lat'],
                    'lng' => $data[0]['lon']
                ];
            }
            return null;
        }
    ]
];

// ============================================
// FUNCIONES DE UTILIDAD
// ============================================

function writeLog($message, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $message . "\n";
}

/**
 * Prepara la dirección para mejorar resultados de APIs
 */
function prepararDireccionParaAPI($direccion_original) {
    $direccion = trim($direccion_original);
    $original = $direccion;
    
    // 1. Asegurar país (USA)
    if (!preg_match('/USA$/i', $direccion) && !preg_match('/United States$/i', $direccion)) {
        $direccion = $direccion . ', USA';
    }
    
    // 2. Expandir abreviaturas comunes de TEXAS (basado en tus datos)
    $reemplazos = [
        // Estados (prioridad Texas)
        ' TX ' => ' Texas ',
        ' TX,' => ' Texas,',
        'TX ' => 'Texas ',
        ' TX$' => ' Texas',
        ' Tx ' => ' Texas ',
        ' Tx,' => ' Texas,',
        
        // Tipos de vía (los más comunes en tus logs)
        ' HWY ' => ' Highway ',
        ' Hwy ' => ' Highway ',
        ' RD ' => ' Road ',
        ' Rd ' => ' Road ',
        ' DR ' => ' Drive ',
        ' Dr ' => ' Drive ',
        ' LN ' => ' Lane ',
        ' Ln ' => ' Lane ',
        ' CIR ' => ' Circle ',
        ' Cir ' => ' Circle ',
        ' CT ' => ' Court ',
        ' Ct ' => ' Court ',
        ' BLVD ' => ' Boulevard ',
        ' Blvd ' => ' Boulevard ',
        ' ST ' => ' Street ',
        ' St ' => ' Street ',
        ' AVE ' => ' Avenue ',
        ' Ave ' => ' Avenue ',
        ' PKWY ' => ' Parkway ',
        ' Pkwy ' => ' Parkway ',
        ' TRL ' => ' Trail ',
        ' Trl ' => ' Trail ',
        ' LP ' => ' Loop ',
        ' Lp ' => ' Loop ',
        ' FWY ' => ' Freeway ',
        ' Fwy ' => ' Freeway ',
        
        // Direcciones cardinales
        ' N ' => ' North ',
        ' S ' => ' South ',
        ' E ' => ' East ',
        ' W ' => ' West ',
        ' NE ' => ' Northeast ',
        ' NW ' => ' Northwest ',
        ' SE ' => ' Southeast ',
        ' SW ' => ' Southwest '
    ];
    
    $direccion = str_replace(array_keys($reemplazos), array_values($reemplazos), $direccion);
    
    // 3. Limpiar espacios múltiples y puntos
    $direccion = preg_replace('/\s+/', ' ', $direccion);
    $direccion = str_replace('.', '', $direccion); // Eliminar puntos (abreviaturas ya expandidas)
    $direccion = trim($direccion);
    
    return [
        'original' => $original,
        'mejorada' => $direccion,
        'fue_modificada' => ($original !== $direccion)
    ];
}

/**
 * Consulta una API específica con reintentos
 */
function consultarAPI($direccion, $api_nombre, $api_config, $log_file) {
    $url = $api_config['url'];
    $key = $api_config['key'] ?? '';
    $max_intentos = 2;
    
    for ($intento = 1; $intento <= $max_intentos; $intento++) {
        if ($intento > 1) {
            writeLog("   🔄 Reintento {$intento} para {$api_config['nombre']}...", $log_file);
            sleep($api_config['delay'] * 2);
        }
        
        // Construir URL según API
        switch ($api_nombre) {
            case 'opencage':
                $url_completa = $url . '?q=' . urlencode($direccion) . '&key=' . $key . '&limit=1';
                break;
            case 'geoapify':
                $url_completa = $url . '?text=' . urlencode($direccion) . '&apiKey=' . $key . '&limit=1';
                break;
            case 'locationiq':
                $url_completa = $url . '?key=' . $key . '&q=' . urlencode($direccion) . '&format=json&limit=1';
                break;
            case 'nominatim':
                $url_completa = $url . '?q=' . urlencode($direccion) . '&format=json&limit=1';
                break;
            default:
                return null;
        }
        
        $ch = curl_init();
        $opciones = [
            CURLOPT_URL => $url_completa,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $api_config['timeout'],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ];
        
        // Añadir User-Agent para Nominatim
        if ($api_nombre === 'nominatim') {
            $opciones[CURLOPT_USERAGENT] = $api_config['user_agent'];
        }
        
        curl_setopt_array($ch, $opciones);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            writeLog("   ⚠️ Error CURL en {$api_config['nombre']}: {$curl_error}", $log_file);
            continue;
        }
        
        if ($http_code !== 200) {
            writeLog("   ⚠️ {$api_config['nombre']} respondió con código {$http_code}", $log_file);
            continue;
        }
        
        $data = json_decode($response, true);
        $coordenadas = $api_config['parser']($data);
        
        if ($coordenadas) {
            return $coordenadas;
        }
    }
    
    writeLog("   ❌ {$api_config['nombre']} no encontró la dirección después de {$max_intentos} intentos", $log_file);
    return null;
}

/**
 * Calcula distancia entre dos puntos en metros (fórmula de Haversine)
 */
function calcularDistancia($lat1, $lng1, $lat2, $lng2) {
    if (!$lat1 || !$lng1 || !$lat2 || !$lng2) return PHP_INT_MAX;
    
    $radio_tierra = 6371000; // metros
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    
    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;
    
    $a = sin($dlat/2) * sin($dlat/2) + 
         cos($lat1) * cos($lat2) * 
         sin($dlng/2) * sin($dlng/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $radio_tierra * $c;
}

/**
 * ALGORITMO DE CONSENSO INTELIGENTE V6.1
 * Detecta clusters naturales y considera la precisión de LocationIQ
 * CORREGIDO: Penaliza clusters de 1 API
 */
function analizarConsensoInteligente($resultados, $tolerancia_metros, $tolerancia_cluster, $coordenadas_bd = null, $log_file = null) {
    $total_apis = count($resultados);
    
    if ($total_apis < 2) {
        return [
            'hay_consenso' => false,
            'coordenadas' => null,
            'apis_acuerdo' => array_column($resultados, 'api'),
            'porcentaje' => $total_apis * 25,
            'metodo' => 'insuficientes_apis'
        ];
    }
    
    if ($log_file) {
        writeLog("   🔍 INICIANDO ANÁLISIS DE CONSENSO INTELIGENTE v6.1", $log_file);
    }
    
    // 1. Construir matriz de distancias
    $n = count($resultados);
    $distancias = [];
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $dist = calcularDistancia(
                $resultados[$i]['lat'], $resultados[$i]['lng'],
                $resultados[$j]['lat'], $resultados[$j]['lng']
            );
            $distancias["{$i}-{$j}"] = $dist;
            
            if ($log_file) {
                writeLog(sprintf("      📏 %s ↔ %s: %.2f metros", 
                    $resultados[$i]['api'], $resultados[$j]['api'], $dist), $log_file);
            }
        }
    }
    
    // 2. Encontrar clusters usando agrupamiento jerárquico simple
    $clusters = [];
    $asignadas = [];
    
    for ($i = 0; $i < $n; $i++) {
        if (in_array($i, $asignadas)) continue;
        
        $cluster = [$i];
        $asignadas[] = $i;
        
        // Buscar APIs que estén cerca de ALGUNA del cluster
        $cambio = true;
        while ($cambio) {
            $cambio = false;
            for ($j = 0; $j < $n; $j++) {
                if (in_array($j, $asignadas)) continue;
                
                foreach ($cluster as $miembro) {
                    $dist = $distancias["{$miembro}-{$j}"] ?? $distancias["{$j}-{$miembro}"] ?? 
                            calcularDistancia(
                                $resultados[$miembro]['lat'], $resultados[$miembro]['lng'],
                                $resultados[$j]['lat'], $resultados[$j]['lng']
                            );
                    
                    if ($dist <= $tolerancia_cluster) {
                        $cluster[] = $j;
                        $asignadas[] = $j;
                        $cambio = true;
                        break;
                    }
                }
            }
        }
        
        $clusters[] = $cluster;
    }
    
    if ($log_file) {
        writeLog("   📊 Clusters detectados: " . count($clusters), $log_file);
        foreach ($clusters as $idx => $cluster) {
            $apis_cluster = array_map(function($i) use ($resultados) {
                return $resultados[$i]['api'];
            }, $cluster);
            writeLog("      Cluster " . ($idx + 1) . ": " . implode(', ', $apis_cluster) . " (" . count($cluster) . " APIs)", $log_file);
        }
    }
    
    // 3. Evaluar cada cluster
    $mejor_cluster = null;
    $mejor_puntaje = -1;
    $mejor_coordenadas = null;
    
    foreach ($clusters as $cluster_indices) {
        $apis_cluster = array_map(function($i) use ($resultados) {
            return $resultados[$i]['api'];
        }, $cluster_indices);
        
        $coords_cluster = array_map(function($i) use ($resultados) {
            return [
                'lat' => $resultados[$i]['lat'],
                'lng' => $resultados[$i]['lng']
            ];
        }, $cluster_indices);
        
        // Calcular coordenadas promedio del cluster (ponderado por peso de API)
        $sum_lat = 0;
        $sum_lng = 0;
        $sum_pesos = 0;
        
        foreach ($cluster_indices as $i) {
            $api = $resultados[$i]['api'];
            $peso = $GLOBALS['apis'][$api]['peso'] ?? 1.0;
            $sum_lat += $resultados[$i]['lat'] * $peso;
            $sum_lng += $resultados[$i]['lng'] * $peso;
            $sum_pesos += $peso;
        }
        
        $lat_prom = $sum_lat / $sum_pesos;
        $lng_prom = $sum_lng / $sum_pesos;
        
        // Calcular consistencia del cluster (desviación promedio ponderada)
        $desviaciones = [];
        foreach ($cluster_indices as $i) {
            $api = $resultados[$i]['api'];
            $peso = $GLOBALS['apis'][$api]['peso'] ?? 1.0;
            $dist = calcularDistancia($lat_prom, $lng_prom, $resultados[$i]['lat'], $resultados[$i]['lng']);
            $desviaciones[] = $dist * $peso;
        }
        $consistencia = array_sum($desviaciones) / $sum_pesos;
        
        // NUEVO CÁLCULO DE PUNTAJE (CORREGIDO)
        $factor_tamano = count($cluster_indices) / $total_apis; // Proporción del total
        $puntaje_base = (count($cluster_indices) * 100) / ($consistencia + 1);
        $puntaje = $puntaje_base * $factor_tamano; // Penaliza clusters pequeños
        
        // BONUS 1: Si LocationIQ está en el cluster
        if (in_array('locationiq', $apis_cluster)) {
            $puntaje *= 1.2;
            
            // BONUS 2: Si LocationIQ coincide con BD (Verizon)
            if ($coordenadas_bd) {
                $idx_locationiq = array_search('locationiq', array_column($resultados, 'api'));
                if ($idx_locationiq !== false) {
                    $dist_locationiq_bd = calcularDistancia(
                        $coordenadas_bd['lat'], $coordenadas_bd['lng'],
                        $resultados[$idx_locationiq]['lat'], $resultados[$idx_locationiq]['lng']
                    );
                    
                    if ($dist_locationiq_bd <= $tolerancia_metros) {
                        $puntaje *= 2; // ¡Doble puntaje! BD es correcta
                        if ($log_file) {
                            writeLog("      🏆 BONUS: LocationIQ coincide con BD (Verizon)", $log_file);
                        }
                    }
                }
            }
        }
        
        // BONUS 3: Cluster mayoritario (más de la mitad de las APIs)
        if (count($cluster_indices) > $n / 2) {
            $puntaje *= 1.5;
        }
        
        if ($log_file) {
            writeLog(sprintf("      📈 Cluster %s: puntaje=%.1f (base=%.1f, factor=%.2f), consistencia=±%.2fm, tamaño=%d", 
                implode(',', $apis_cluster), $puntaje, $puntaje_base, $factor_tamano, $consistencia, count($cluster_indices)), $log_file);
        }
        
        if ($puntaje > $mejor_puntaje) {
            $mejor_puntaje = $puntaje;
            $mejor_cluster = $apis_cluster;
            $mejor_coordenadas = ['lat' => $lat_prom, 'lng' => $lng_prom];
            $mejor_consistencia = $consistencia;
            $mejor_tamano = count($cluster_indices);
        }
    }
    
    // 4. Decisión final con lógica difusa
    $porcentaje_cluster = ($mejor_tamano / $n) * 100;
    
    // Criterios de consenso (ordenados de más estricto a más flexible)
    $hay_consenso = false;
    $metodo = '';
    
    if ($porcentaje_cluster >= 50) {
        $hay_consenso = true;
        $metodo = 'mayoría_absoluta';
    } elseif ($porcentaje_cluster >= 40 && $mejor_consistencia < 30) {
        $hay_consenso = true;
        $metodo = 'mayoría_relativa_alta_precisión';
    } elseif ($porcentaje_cluster >= 33 && $mejor_consistencia < 20) {
        $hay_consenso = true;
        $metodo = 'minoría_de_alta_precisión';
    } elseif ($porcentaje_cluster >= 25 && $mejor_consistencia < 10 && in_array('locationiq', $mejor_cluster)) {
        // 2 APIs (incluyendo LocationIQ) con consistencia excelente
        $hay_consenso = true;
        $metodo = 'dúo_de_alta_precisión_con_locationiq';
    }
    
    // Verificación final con BD (si aplica)
    if ($hay_consenso && $coordenadas_bd) {
        $dist_consenso_bd = calcularDistancia(
            $coordenadas_bd['lat'], $coordenadas_bd['lng'],
            $mejor_coordenadas['lat'], $mejor_coordenadas['lng']
        );
        
        // Si el consenso está cerca de BD, reforzar decisión
        if ($dist_consenso_bd <= $tolerancia_metros * 2) {
            if ($log_file) {
                writeLog("      ✅ Consenso validado por cercanía a BD (Verizon): " . round($dist_consenso_bd, 2) . "m", $log_file);
            }
        }
    }
    
    if ($log_file) {
        writeLog("   🎯 RESULTADO: " . ($hay_consenso ? "CONSENSO" : "SIN CONSENSO"), $log_file);
        writeLog("      Método: {$metodo}", $log_file);
        writeLog("      APIs de acuerdo: " . implode(', ', $mejor_cluster), $log_file);
        writeLog("      Porcentaje: " . round($porcentaje_cluster, 1) . "%", $log_file);
        writeLog("      Consistencia: ±" . round($mejor_consistencia, 2) . "m", $log_file);
    }
    
    return [
        'hay_consenso' => $hay_consenso,
        'coordenadas' => $mejor_coordenadas,
        'apis_acuerdo' => $mejor_cluster,
        'porcentaje' => round($porcentaje_cluster, 1),
        'consistencia' => round($mejor_consistencia, 2),
        'metodo' => $metodo,
        'tamano_cluster' => $mejor_tamano,
        'total_apis' => $n,
        'puntaje' => round($mejor_puntaje, 1)
    ];
}

// ============================================
// EJECUCIÓN PRINCIPAL
// ============================================

// Conectar a BD
$conn = new mysqli(
    $db_config['host'],
    $db_config['user'],
    $db_config['pass'],
    $db_config['name']
);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error . "\n");
}

// Inicializar log
$log_file = $log_config['dir'] . '/' . $log_config['prefix'] . date($log_config['date_format']) . '.txt';

// Inicializar arrays para IDs
$ids_no_validados = [];          // Direcciones no encontradas por APIs
$ids_sin_consenso = [];          // Direcciones validadas pero sin consenso
$ids_coordenadas_actualizadas = []; // Direcciones con coordenadas actualizadas
$ids_direccion_mejorada = [];     // Direcciones cuyo formato fue mejorado

writeLog("==================================================", $log_file);
writeLog("🚀 INICIANDO VALIDACIÓN INTELIGENTE v6.1", $log_file);
writeLog("📋 Modo: " . ($procesar ? "REAL (UPDATE)" : "SIMULACIÓN (solo log)"), $log_file);
writeLog("📏 Tolerancia coordenadas: {$tolerancia_metros} metros", $log_file);
writeLog("🔗 Tolerancia clusters: {$tolerancia_cluster} metros", $log_file);
writeLog("🔍 Mínimo APIs exitosas: {$min_apis_exitosas}", $log_file);
writeLog("==================================================", $log_file);

// Obtener direcciones
$query = "SELECT id_direccion, direccion, lat, lng FROM direcciones WHERE direccion IS NOT NULL AND direccion != '' ORDER BY id_direccion";
$result = $conn->query($query);
$total = $result->num_rows;

writeLog("📊 TOTAL DIRECCIONES A PROCESAR: {$total}", $log_file);

$contador = 0;

while ($row = $result->fetch_assoc()) {
    $contador++;
    $id = $row['id_direccion'];
    $direccion_original = trim($row['direccion']);
    $lat_bd = $row['lat'];
    $lng_bd = $row['lng'];
    
    writeLog("────────────────────────────────────", $log_file);
    writeLog("📌 [{$contador}/{$total}] ID: {$id}", $log_file);
    writeLog("📍 Dirección ORIGINAL: {$direccion_original}", $log_file);
    
    // 1. Preparar dirección para APIs (SIEMPRE se hace)
    $direccion_data = prepararDireccionParaAPI($direccion_original);
    $direccion_mejorada = $direccion_data['mejorada'];
    $direccion_modificada = $direccion_data['fue_modificada'];
    
    if ($direccion_modificada) {
        writeLog("🔄 Dirección MEJORADA: {$direccion_mejorada}", $log_file);
        writeLog("   └── Se aplicó expansión de abreviaturas y formato USA", $log_file);
    } else {
        writeLog("   └── Dirección sin modificaciones necesarias", $log_file);
    }
    
    writeLog("🗺️ Coordenadas ACTUALES en BD (Verizon): Lat={$lat_bd}, Lng={$lng_bd}", $log_file);
    
    // 2. Consultar APIs usando la dirección MEJORADA
    $apis_exitosas = 0;
    $resultados_coordenadas = [];
    
    foreach ($apis as $api_nombre => $api_config) {
        $coords = consultarAPI($direccion_mejorada, $api_nombre, $api_config, $log_file);
        if ($coords) {
            $apis_exitosas++;
            $resultados_coordenadas[] = [
                'api' => $api_nombre,
                'lat' => $coords['lat'],
                'lng' => $coords['lng']
            ];
        }
        sleep($api_config['delay']);
    }
    
    // 3. Validar que la dirección existe
    if ($apis_exitosas < $min_apis_exitosas) {
        writeLog("❌ VALIDACIÓN DE DIRECCIÓN FALLIDA", $log_file);
        writeLog("   ├── APIs exitosas: {$apis_exitosas}/" . count($apis), $log_file);
        writeLog("   ├── Mínimo requerido: {$min_apis_exitosas}", $log_file);
        writeLog("   └── DECISIÓN: Se marcará para revisión manual", $log_file);
        $ids_no_validados[] = $id;
        continue;
    }
    
    writeLog("✅ VALIDACIÓN DE DIRECCIÓN EXITOSA", $log_file);
    writeLog("   ├── APIs que encontraron la dirección: {$apis_exitosas}/" . count($apis), $log_file);
    
    // 4. Mostrar distancias desde BD
    writeLog("   📏 DISTANCIAS desde coordenadas actuales (Verizon):", $log_file);
    $mejor_distancia = PHP_INT_MAX;
    $mejor_api = '';
    foreach ($resultados_coordenadas as $res) {
        $dist = calcularDistancia($lat_bd, $lng_bd, $res['lat'], $res['lng']);
        if ($dist < $mejor_distancia) {
            $mejor_distancia = $dist;
            $mejor_api = $res['api'];
        }
        writeLog(sprintf("      ├── %s: %.2f metros", 
            str_pad($res['api'], 12), $dist), $log_file);
    }
    writeLog("      └── 🏆 Mejor coincidencia: {$mejor_api} con " . round($mejor_distancia, 2) . "m", $log_file);
    
    // 5. ANALIZAR CONSENSO INTELIGENTE V6.1
    $bd_coords = ['lat' => $lat_bd, 'lng' => $lng_bd];
    $consenso = analizarConsensoInteligente(
        $resultados_coordenadas, 
        $tolerancia_metros, 
        $tolerancia_cluster, 
        $bd_coords,
        $log_file
    );
    
    // Variables para decisiones
    $actualizar_direccion = $direccion_modificada; // ¡SIEMPRE que sea mejorable!
    $actualizar_coordenadas = false;
    $coords_finales = null;
    $distancia_final = null;
    
    if ($consenso['hay_consenso']) {
        $coords_finales = $consenso['coordenadas'];
        $distancia_final = calcularDistancia($lat_bd, $lng_bd, $coords_finales['lat'], $coords_finales['lng']);
        
        // Decidir si actualizar coordenadas (solo si están fuera de tolerancia)
        $actualizar_coordenadas = ($distancia_final > $tolerancia_metros);
        
        writeLog("   📊 ANÁLISIS FINAL:", $log_file);
        writeLog("      ├── Coordenadas por consenso: Lat={$coords_finales['lat']}, Lng={$coords_finales['lng']}", $log_file);
        writeLog("      ├── Distancia desde BD: " . round($distancia_final, 2) . " metros", $log_file);
        writeLog("      ├── ¿Actualizar coordenadas? " . ($actualizar_coordenadas ? "SÍ" : "NO"), $log_file);
        writeLog("      └── Motivo: " . ($actualizar_coordenadas ? "Fuera de tolerancia" : "Dentro de tolerancia"), $log_file);
        
    } else {
        writeLog("   ⚠️ SIN CONSENSO DE COORDENADAS", $log_file);
        writeLog("      ├── Mejor cluster: " . implode(', ', $consenso['apis_acuerdo']), $log_file);
        writeLog("      ├── Porcentaje: {$consenso['porcentaje']}%", $log_file);
        writeLog("      ├── Consistencia: ±{$consenso['consistencia']}m", $log_file);
        writeLog("      ├── Puntaje: {$consenso['puntaje']}", $log_file);
        writeLog("      └── DECISIÓN: Se marcará para revisión manual", $log_file);
        $ids_sin_consenso[] = $id;
        continue;
    }
    
    // 6. DECISIONES FINALES
    writeLog("   📋 DECISIONES PARA ESTE REGISTRO:", $log_file);
    writeLog("      ├── Dirección utilizada para cálculos: " . ($direccion_modificada ? "MEJORADA" : "ORIGINAL"), $log_file);
    writeLog("      ├── ¿Actualizar dirección? " . ($actualizar_direccion ? "SÍ" : "NO"), $log_file);
    writeLog("      └── ¿Actualizar coordenadas? " . ($actualizar_coordenadas ? "SÍ" : "NO"), $log_file);
    
    // 7. EJECUTAR ACTUALIZACIONES (solo si $procesar = TRUE)
    if ($procesar && ($actualizar_direccion || $actualizar_coordenadas)) {
        $campos_update = [];
        $tipos = "";
        $valores = [];
        $campos_actualizados = [];
        
        if ($actualizar_direccion) {
            $campos_update[] = "direccion = ?";
            $tipos .= "s";
            $valores[] = $direccion_mejorada;
            $campos_actualizados[] = "dirección";
            writeLog("   🔤 ACTUALIZANDO dirección a formato mejorado", $log_file);
        }
        
        if ($actualizar_coordenadas) {
            $campos_update[] = "lat = ?";
            $campos_update[] = "lng = ?";
            $tipos .= "dd";
            $valores[] = $coords_finales['lat'];
            $valores[] = $coords_finales['lng'];
            $campos_actualizados[] = "coordenadas";
            writeLog("   🗺️ ACTUALIZANDO coordenadas (diferencia de " . round($distancia_final, 2) . " metros)", $log_file);
        }
        
        $valores[] = $id;
        $tipos .= "i";
        
        $update_sql = "UPDATE direcciones SET " . implode(", ", $campos_update) . " WHERE id_direccion = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param($tipos, ...$valores);
        
        if ($stmt->execute()) {
            writeLog("   ✅ UPDATE completado - Campos: " . implode(", ", $campos_actualizados), $log_file);
            
            if ($actualizar_coordenadas) $ids_coordenadas_actualizadas[] = $id;
            if ($actualizar_direccion) $ids_direccion_mejorada[] = $id;
        } else {
            writeLog("   ❌ Error en UPDATE: " . $stmt->error, $log_file);
        }
        $stmt->close();
        
    } elseif (!$procesar && ($actualizar_direccion || $actualizar_coordenadas)) {
        // MODO SIMULACIÓN
        $simulacion_updates = [];
        if ($actualizar_direccion) {
            $simulacion_updates[] = "dirección";
            writeLog("   📝 [SIMULACIÓN] Nueva dirección: {$direccion_mejorada}", $log_file);
        }
        if ($actualizar_coordenadas) {
            $simulacion_updates[] = "coordenadas";
            writeLog("   📝 [SIMULACIÓN] Nuevas coordenadas: Lat={$coords_finales['lat']}, Lng={$coords_finales['lng']}", $log_file);
        }
        writeLog("   📝 [SIMULACIÓN] Se actualizaría: " . implode(" y ", $simulacion_updates), $log_file);
    } else {
        writeLog("   ℹ️ No hay cambios para aplicar en este registro", $log_file);
    }
}

// ============================================
// REPORTE FINAL
// ============================================

writeLog("==================================================", $log_file);
writeLog("📊 REPORTE FINAL - CONSENSO INTELIGENTE v6.1", $log_file);
writeLog("==================================================", $log_file);
writeLog("📋 TOTAL PROCESADAS: {$total}", $log_file);
writeLog("", $log_file);

// Estadísticas generales
$direcciones_ok = $total - count($ids_no_validados) - count($ids_sin_consenso);
writeLog("✅ DIRECCIONES CON CONSENSO: {$direcciones_ok}", $log_file);
writeLog("   ├── Con coordenadas actualizadas: " . count($ids_coordenadas_actualizadas), $log_file);
writeLog("   └── Con dirección mejorada: " . count($ids_direccion_mejorada), $log_file);
writeLog("", $log_file);

// Direcciones con problemas
writeLog("⚠️ DIRECCIONES SIN CONSENSO: " . count($ids_sin_consenso), $log_file);
writeLog("❌ DIRECCIONES NO VALIDADAS: " . count($ids_no_validados), $log_file);
writeLog("", $log_file);

// Mostrar IDs específicos (con formato para fácil copia)
if (!empty($ids_sin_consenso)) {
    writeLog("📋 IDs SIN CONSENSO (" . count($ids_sin_consenso) . "):", $log_file);
    writeLog("    " . implode(", ", $ids_sin_consenso), $log_file);
}

if (!empty($ids_no_validados)) {
    writeLog("📋 IDs NO VALIDADOS (" . count($ids_no_validados) . "):", $log_file);
    writeLog("    " . implode(", ", $ids_no_validados), $log_file);
}

if (!empty($ids_coordenadas_actualizadas)) {
    writeLog("📋 IDs CON COORDENADAS ACTUALIZADAS (" . count($ids_coordenadas_actualizadas) . "):", $log_file);
    writeLog("    " . implode(", ", $ids_coordenadas_actualizadas), $log_file);
}

if (!empty($ids_direccion_mejorada)) {
    writeLog("📋 IDs CON DIRECCIÓN MEJORADA (" . count($ids_direccion_mejorada) . "):", $log_file);
    writeLog("    " . implode(", ", $ids_direccion_mejorada), $log_file);
}

writeLog("==================================================", $log_file);
writeLog("🏁 SCRIPT FINALIZADO", $log_file);
writeLog("==================================================", $log_file);

$conn->close();

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ PROCESO COMPLETADO\n";
echo "📋 Log generado: " . basename($log_file) . "\n";
echo "📊 Total procesadas: {$total}\n";
echo "⚠️ Para revisión manual: " . (count($ids_no_validados) + count($ids_sin_consenso)) . " direcciones\n";
echo str_repeat("=", 50) . "\n";