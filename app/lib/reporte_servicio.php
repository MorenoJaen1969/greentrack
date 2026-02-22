<?php
/**
 * REPORTE PDF DE RUTAS - PRE-SERVICIOS
 * Genera 5 PDFs separados (uno por ruta) tipo checklist para operarios
 */

require_once('tcpdf/tcpdf.php');

// Configuración de base de datos
$m = new mysqli('localhost', 'mmoreno', 'Noloseno#2017', 'greentrack_live');
if ($m->connect_error) die("[ERROR] Conexión fallida: " . $m->connect_error . "\n");
$m->set_charset('utf8mb4');

// Fecha del reporte
$fecha_reporte = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$fecha_formateada = date('l, F jS', strtotime($fecha_reporte));

// Ruta del icono del cliente (ajústala según tu sistema)
$ruta_icono_cliente = 'assets/images/client_icon.png'; // <-- AJUSTA ESTA RUTA

// Colores de rutas extraídos del HTML
$colores_rutas = [
    'WEDNESDAY - Route 1' => '#9B59B6',
    'WEDNESDAY - Route 2' => '#8E44AD',
    'WEDNESDAY - Route 3' => '#5E35B1',
    'WEDNESDAY - Route 4' => '#3949AB',
    'WEDNESDAY - Route 5' => '#7E57C2'
];

// Consulta SQL - ADAPTA según tu estructura real
$sql = "SELECT 
            c.id_cliente,
            c.nombre as customer,
            c.direccion as address,
            c.ciudad,
            c.estado,
            c.zip,
            r.nombre_ruta as route_name,
            s.tiempo_servicio as service_time,
            s.frecuencia,
            s.ultimo_servicio
        FROM clientes c
        INNER JOIN rutas_clientes rc ON c.id_cliente = rc.id_cliente
        INNER JOIN rutas r ON rc.id_ruta = r.id_ruta
        LEFT JOIN servicios_clientes s ON c.id_cliente = s.id_cliente
        WHERE rc.dia_semana = 'WEDNESDAY'
        AND c.activo = 1
        ORDER BY r.orden_ruta, c.nombre";

$result = $m->query($sql);

// Agrupar clientes por ruta
$rutas = [];
while ($row = $result->fetch_assoc()) {
    $route_key = $row['route_name'];
    if (!isset($rutas[$route_key])) {
        $rutas[$route_key] = [];
    }
    $rutas[$route_key][] = $row;
}

// Generar un PDF por cada ruta
foreach ($rutas as $nombre_ruta => $clientes) {
    generarReporteRuta($nombre_ruta, $clientes, $fecha_formateada, $fecha_reporte, $ruta_icono_cliente);
}

$m->close();
echo "✓ Reportes generados exitosamente.\n";

/**
 * Genera el PDF de una ruta específica
 */
function generarReporteRuta($nombre_ruta, $clientes, $fecha_formateada, $fecha_reporte, $ruta_icono) {
    global $colores_rutas;
    
    // Obtener color de la ruta
    $color_hex = isset($colores_rutas[$nombre_ruta]) ? $colores_rutas[$nombre_ruta] : '#9B59B6';
    $color_rgb = hexToRgb($color_hex);
    
    // Crear color tenue (versión clara para filas alternadas)
    $color_tenue_rgb = [
        'r' => min(255, $color_rgb['r'] + 140),
        'g' => min(255, $color_rgb['g'] + 140),
        'b' => min(255, $color_rgb['b'] + 140)
    ];
    
    // Crear PDF Landscape (Horizontal)
    $pdf = new TCPDF('L', 'mm', 'LETTER', true, 'UTF-8', false);
    
    $pdf->SetCreator('GreenTrack');
    $pdf->SetAuthor('GreenTrack System');
    $pdf->SetTitle('Route Report - ' . $nombre_ruta);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(false);
    
    $pdf->AddPage();
    
    // === ENCABEZADO CON COLOR DE RUTA ===
    $pdf->SetFillColor($color_rgb['r'], $color_rgb['g'], $color_rgb['b']);
    $pdf->Rect(0, 0, 280, 18, 'F');
    
    // Fecha en blanco, grande, mayúsculas
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, strtoupper($fecha_formateada), 0, 1, 'C', true);
    
    // Línea para responsable
    $pdf->Ln(2);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(35, 6, 'Driver:', 0, 0, 'L');
    $pdf->Cell(80, 6, '', 'B', 0, 'L');
    $pdf->Cell(20, 6, '', 0, 0, 'L');
    $pdf->Cell(35, 6, 'Truck #:', 0, 0, 'L');
    $pdf->Cell(0, 6, '', 'B', 1, 'L');
    
    // Nombre de ruta
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($color_rgb['r'], $color_rgb['g'], $color_rgb['b']);
    $pdf->Cell(0, 8, strtoupper($nombre_ruta), 0, 1, 'L');
    
    // Línea divisoria negra gruesa
    $pdf->SetLineWidth(0.5);
    $pdf->Line(8, $pdf->GetY(), 270, $pdf->GetY());
    $pdf->Ln(2);
    
    // === TABLA ===
    // Anchos de columnas (total 262mm para carta horizontal con márgenes)
    $w_num = 6;
    $w_customer = 45;
    $w_address = 75;
    $w_worksite = 18;
    $w_truck = 12;
    $w_start = 14;
    $w_end = 14;
    $w_lawn = 35; // 5 columnas x 7mm
    $w_fert = 14; // 2 columnas x 7mm
    $w_spray = 12;
    $w_comments = 25;
    
    // Encabezados
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetLineWidth(0.2);
    
    $h_header = 10;
    
    // Primera fila de encabezados
    $pdf->Cell($w_num, $h_header, '#', 1, 0, 'C', true);
    $pdf->Cell($w_customer, $h_header, 'CUSTOMER', 1, 0, 'C', true);
    $pdf->Cell($w_address, $h_header, 'ADDRESS', 1, 0, 'C', true);
    $pdf->Cell($w_worksite, $h_header, 'Work Site', 1, 0, 'C', true);
    $pdf->Cell($w_truck, $h_header, 'TRUCK #', 1, 0, 'C', true);
    $pdf->Cell($w_start, $h_header, 'Start', 1, 0, 'C', true);
    $pdf->Cell($w_end, $h_header, 'End', 1, 0, 'C', true);
    
    // Sub-encabezado Lawn Care (M,E,B,W,T)
    $x_lawn = $pdf->GetX();
    $y_lawn = $pdf->GetY();
    $pdf->Cell($w_lawn, 5, 'LAWN CARE', 1, 0, 'C', true);
    $pdf->SetXY($x_lawn, $y_lawn + 5);
    $pdf->Cell(7, 5, 'M', 1, 0, 'C', true);
    $pdf->Cell(7, 5, 'E', 1, 0, 'C', true);
    $pdf->Cell(7, 5, 'B', 1, 0, 'C', true);
    $pdf->Cell(7, 5, 'W', 1, 0, 'C', true);
    $pdf->Cell(7, 5, 'T', 1, 0, 'C', true);
    $pdf->SetXY($x_lawn + $w_lawn, $y_lawn);
    
    // Sub-encabezado Fertilizer (G,P)
    $x_fert = $pdf->GetX();
    $y_fert = $pdf->GetY();
    $pdf->Cell($w_fert, 5, 'FERT', 1, 0, 'C', true);
    $pdf->SetXY($x_fert, $y_fert + 5);
    $pdf->Cell(7, 5, 'G', 1, 0, 'C', true);
    $pdf->Cell(7, 5, 'P', 1, 0, 'C', true);
    $pdf->SetXY($x_fert + $w_fert, $y_fert);
    
    $pdf->Cell($w_spray, $h_header, 'SPRAY', 1, 0, 'C', true);
    $pdf->Cell($w_comments, $h_header, 'COMMENTS', 1, 1, 'C', true);
    
    // === 20 FILAS DE DATOS ===
    $pdf->SetFont('helvetica', '', 7);
    $altura_fila = 22; // ~60px en mm
    
    for ($i = 1; $i <= 20; $i++) {
        // Color alternado
        if ($i % 2 == 0) {
            $pdf->SetFillColor(255, 255, 255);
        } else {
            $pdf->SetFillColor($color_tenue_rgb['r'], $color_tenue_rgb['g'], $color_tenue_rgb['b']);
        }
        
        $cliente = isset($clientes[$i - 1]) ? $clientes[$i - 1] : null;
        
        // Número
        $pdf->Cell($w_num, $altura_fila, $i, 1, 0, 'C', true);
        
        // Customer con icono
        $x_cust = $pdf->GetX();
        $y_cust = $pdf->GetY();
        $customer = $cliente ? strtoupper($cliente['customer']) : '';
        $pdf->Cell($w_customer, $altura_fila, $customer, 1, 0, 'L', true);
        
        // Icono del cliente (pequeño, esquina superior izquierda de la celda)
        if ($cliente && file_exists($ruta_icono)) {
            $pdf->Image($ruta_icono, $x_cust + 1, $y_cust + 1, 4, 4, '', '', '', false, 300, '', false, false, 0, false, false, false);
        }
        
        // Address (completa, sin truncar)
        $address = '';
        if ($cliente) {
            $address = strtoupper($cliente['address']);
            if ($cliente['ciudad']) $address .= ', ' . strtoupper($cliente['ciudad']);
            if ($cliente['estado']) $address .= ', ' . strtoupper($cliente['estado']);
        }
        $pdf->Cell($w_address, $altura_fila, $address, 1, 0, 'L', true);
        
        // Work Site
        $worksite = $cliente ? 'WED' : '';
        $pdf->Cell($w_worksite, $altura_fila, $worksite, 1, 0, 'C', true);
        
        // TRUCK # (vacío)
        $pdf->Cell($w_truck, $altura_fila, '', 1, 0, 'C', true);
        
        // Start Time (vacío)
        $pdf->Cell($w_start, $altura_fila, '', 1, 0, 'C', true);
        
        // End Time (vacío)
        $pdf->Cell($w_end, $altura_fila, '', 1, 0, 'C', true);
        
        // Lawn Care - 5 checkboxes (M,E,B,W,T)
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        for ($j = 0; $j < 5; $j++) {
            // Borde de celda
            $pdf->Rect($x + ($j * 7), $y, 7, $altura_fila, 'D');
            // Checkbox pequeño centrado
            $pdf->Rect($x + ($j * 7) + 2, $y + 9, 3, 3, 'D');
        }
        $pdf->SetX($x + 35);
        
        // Fertilizer - 2 checkboxes (G,P)
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        for ($j = 0; $j < 2; $j++) {
            $pdf->Rect($x + ($j * 7), $y, 7, $altura_fila, 'D');
            $pdf->Rect($x + ($j * 7) + 2, $y + 9, 3, 3, 'D');
        }
        $pdf->SetX($x + 14);
        
        // SPRAY
        $pdf->Cell($w_spray, $altura_fila, '', 1, 0, 'C', true);
        
        // COMMENTS
        $pdf->Cell($w_comments, $altura_fila, '', 1, 1, 'L', true);
    }
    
    // Pie de página
    $pdf->SetY(-10);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i') . ' | GreenTrack System | Route: ' . $nombre_ruta, 0, 0, 'C');
    
    // Guardar/Download
    $nombre_archivo = 'Route_' . preg_replace('/[^A-Za-z0-9]/', '_', $nombre_ruta) . '_' . $fecha_reporte . '.pdf';
    $pdf->Output($nombre_archivo, 'D');
}

// Funciones auxiliares
function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return ['r' => $r, 'g' => $g, 'b' => $b];
}
?>