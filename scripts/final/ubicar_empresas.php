<?php
/**
 * ======================================================================
 * DETECTOR DE POSIBLES EMPRESAS
 * ======================================================================
 * 
 * Propósito: Identificar clientes que probablemente sean personas jurídicas
 *   • Analiza campo nombre en busca de palabras clave de empresas
 *   • Genera lista de candidatos para revisión manual
 *   • Propone migración: nombre → nombre_comercial
 */

$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  DETECTOR DE POSIBLES EMPRESAS                                   ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// Palabras clave que indican empresa
$palabras_empresa = [
    'INC', 'LLC', 'CORP', 'CO', 'LTD', 'LP', 'LLP', 'PC', 'PLLC',
    'INCORPORATED', 'LIMITED', 'COMPANY', 'CORPORATION',
    'SERVICES', 'SERVICE', 'CONSTRUCTION', 'CONTRACTING',
    'LANDSCAPING', 'LANDSCAPE', 'GARDEN', 'MAINTENANCE',
    'RESTAURANT', 'RESTAURANTS', 'CAFE', 'BAR', 'GRILL',
    'HOTEL', 'MOTEL', 'INN', 'LODGE', 'RESORT',
    'BANK', 'FINANCIAL', 'CREDIT', 'LOAN',
    'ASSOCIATES', 'ASSOCIATION', 'GROUP', 'HOLDING',
    'PROPERTIES', 'PROPERTY', 'REALTY', 'ESTATES',
    'MEDICAL', 'HEALTH', 'CLINIC', 'HOSPITAL',
    'SCHOOL', 'ACADEMY', 'UNIVERSITY', 'COLLEGE',
    '&', 'AND', 'OF', 'THE', 'LLC'
];

// Obtener clientes con nombre no vacío
$stmt = $m->prepare("
    SELECT id_cliente, nombre, nombre_comercial, id_tipo_persona
    FROM clientes
    WHERE TRIM(nombre) != ''
    ORDER BY nombre ASC
    LIMIT 5000
");
$stmt->execute();
$result = $stmt->get_result();

$candidatos_empresa = [];
$total_analizados = 0;

while ($row = $result->fetch_assoc()) {
    $total_analizados++;
    $nombre = strtoupper(trim($row['nombre']));
    
    // Verificar si contiene palabras clave de empresa
    $es_empresa = false;
    $palabras_detectadas = [];
    
    foreach ($palabras_empresa as $palabra) {
        if (strpos($nombre, $palabra) !== false) {
            $es_empresa = true;
            $palabras_detectadas[] = $palabra;
        }
    }
    
    // También verificar si tiene más de 2 palabras (común en empresas)
    $palabras = explode(' ', $nombre);
    if (count($palabras) > 3) {
        $es_empresa = true;
    }
    
    if ($es_empresa) {
        $candidatos_empresa[] = [
            'id_cliente' => $row['id_cliente'],
            'nombre' => $row['nombre'],
            'nombre_comercial' => $row['nombre_comercial'] ?? '',
            'id_tipo_persona' => $row['id_tipo_persona'] ?? 1,
            'palabras_detectadas' => $palabras_detectadas,
            'propuesta' => [
                'nombre_comercial' => $row['nombre'],
                'nombre' => NULL,
                'id_tipo_persona' => 2
            ]
        ];
    }
}
$stmt->close();

$total_candidatos = count($candidatos_empresa);

echo "[ANÁLISIS COMPLETADO]\n";
echo "  Total de clientes analizados: $total_analizados\n";
echo "  Posibles empresas detectadas: $total_candidatos\n\n";

// Generar reporte CSV
$csv_file = '/var/www/greentrack/scripts/candidatos_empresas.csv';
$fp = fopen($csv_file, 'w');
fputcsv($fp, [
    'ID_CLIENTE',
    'NOMBRE_ACTUAL',
    'NOMBRE_COMERCIAL_ACTUAL',
    'ID_TIPO_PERSONA_ACTUAL',
    'PALABRAS_DETECTADAS',
    'PROPUESTA_NOMBRE_COMERCIAL',
    'PROPUESTA_NOMBRE',
    'PROPUESTA_ID_TIPO_PERSONA',
    'VERIFICAR_MANUAL'
], ';');

foreach ($candidatos_empresa as $cand) {
    fputcsv($fp, [
        $cand['id_cliente'],
        $cand['nombre'],
        $cand['nombre_comercial'],
        $cand['id_tipo_persona'],
        implode(', ', $cand['palabras_detectadas']),
        $cand['propuesta']['nombre_comercial'],
        $cand['propuesta']['nombre'] ?? 'NULL',
        $cand['propuesta']['id_tipo_persona'],
        'SI'
    ], ';');
}
fclose($fp);

echo "[OK] Reporte generado: $csv_file\n\n";

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PRÓXIMOS PASOS                                                  ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";
echo "  1. Revisa el CSV generado en Excel\n";
echo "  2. Verifica manualmente cada candidato (columna VERIFICAR_MANUAL)\n";
echo "  3. Marca como 'NO' los que NO son empresas\n";
echo "  4. Guarda el CSV editado\n";
echo "  5. Ejecuta el script de migración con el CSV verificado\n\n";

$m->close();

echo "[FIN] Detección de posibles empresas completada\n\n";

?>