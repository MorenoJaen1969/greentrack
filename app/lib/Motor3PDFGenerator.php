<?php
namespace app\lib;

// Asegúrate de tener TCPDF instalado
require_once APP_R_PROY . 'app/lib/tcpdf/tcpdf.php';

class Motor3PDFGenerator
{

    // === FUNCIÓN PARA GENERAR PDFs ===
    public function generarPDFsDesdeServicios($servicios, $fecha_servicio)
    {
        error_log('=== INICIO generarPDFsDesdeServicios ===');
        error_log('Total servicios recibidos: ' . count($servicios));

        $pdf_urls = [];
        $viajes = $this->agruparServiciosPorCrew($servicios);

        error_log('Total viajes agrupados: ' . count($viajes));

        // Directorio para PDFs
        $directorio_pdf = $_SERVER['DOCUMENT_ROOT'] . '/PDF/';
        error_log('Directorio PDF: ' . $directorio_pdf);

        if (!file_exists($directorio_pdf)) {
            error_log('Creando directorio PDF...');
            if (!mkdir($directorio_pdf, 0755, true)) {
                error_log('ERROR: No se pudo crear directorio: ' . $directorio_pdf);
                return $pdf_urls;
            }
        }

        // Generar PDF para cada crew
        foreach ($viajes as $crew => $viaje) {
            try {
                error_log('Generando PDF para crew: ' . $crew);
                $pdf_content = $this->crearPDFSimple($viaje, $fecha_servicio);
                $nombre_archivo = $this->generarNombreArchivoPDF($crew, $fecha_servicio);
                $ruta_completa = $directorio_pdf . $nombre_archivo;

                if (file_put_contents($ruta_completa, $pdf_content) !== false) {
                    error_log('PDF guardado: ' . $ruta_completa);
                    $pdf_urls[] = [
                        'crew' => $crew,
                        'url' => $nombre_archivo,
                        'servicios_count' => count($viaje['servicios'])
                    ];
                } else {
                    error_log('ERROR: No se pudo guardar PDF: ' . $ruta_completa);
                }

            } catch (\Exception $e) {
                error_log("Exception generando PDF para {$crew}: " . $e->getMessage());
            } catch (\Error $e) {
                 error_log("Error generando PDF para {$crew}: " . $e->getMessage());
            }
        }

        error_log('=== FIN generarPDFsDesdeServicios ===');
        return $pdf_urls;
    }

    private function agruparServiciosPorCrew($servicios)
    {
        $viajes = [];

        foreach ($servicios as $servicio) {
            $crew = $servicio['crew'] ?? 'Sin Crew';

            if (!isset($viajes[$crew])) {
                $viajes[$crew] = [
                    'crew' => $crew,
                    'vehiculo' => $servicio['vehiculo'] ?? 'No especificado',
                    'fecha' => $fecha_servicio ?? date('Y-m-d'),
                    'servicios' => []
                ];
            }

            $viajes[$crew]['servicios'][] = $servicio;
        }

        return $viajes;
    }

    private function crearPDFSimple($viaje, $fecha_servicio)
    {
        error_log('INICIO crearPDFSimple para crew: ' . $viaje['crew']);

        // Verificar si TCPDF está disponible
        if (class_exists('\TCPDF')) {
            error_log('Llamando a crearConTCPDF...');
            $resultado = $this->crearConTCPDF($viaje, $fecha_servicio);
            error_log('crearConTCPDF completado, tamaño resultado: ' . strlen($resultado));
            return $resultado;
        } else {
            error_log('TCPDF no disponible, devolviendo fallback...');
            // Fallback simple si TCPDF falla o no está disponible
            return "PDF no disponible. TCPDF no encontrado.";
        }
    }


    // === FUNCIÓN PARA CREAR PDF DIRECTAMENTE CON TCPDF ===
    // Corregida para mejor espaciado, texto multilínea y color de QR.
    private function crearConTCPDF($viaje, $fecha_servicio)
    {
        error_log('INICIO crearConTCPDF para: ' . $viaje['crew']);

        if (!class_exists('\TCPDF')) {
             error_log('TCPDF NO EXISTE');
             throw new \Exception('TCPDF no está disponible.');
        }

        try {
            error_log('Creando instancia TCPDF...');
            // === MANTENER orientación vertical ===
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false); // 'P' para Portrait
            error_log('Instancia TCPDF creada');

            $pdf->SetCreator('GreenTrack');
            $pdf->SetTitle('Viaje ' . $viaje['crew']);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            // Ajustar márgenes para Portrait
            $pdf->SetMargins(15, 15, 15); // Izquierda, Arriba, Derecha
            $pdf->SetAutoPageBreak(TRUE, 15); // Activar salto de página automático, margen inferior
            error_log('Configuración PDF completada');

            $pdf->AddPage();
            error_log('Página añadida');

            // === CREAR PDF DIRECTAMENTE CON FUNCIONES TCPDF ===

            // Encabezado
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetFillColor(52, 152, 219); // #3498db
            $pdf->SetTextColor(255);
            // Usar MultiCell para encabezados largos y centrado
            $pdf->MultiCell(0, 10, 'GREEN TRACK - ORDEN DE TRABAJO', 0, 'C', 1, 1, '', '', true, 0, false, true, 10, 'M');
            
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetFillColor(255);
            $pdf->SetTextColor(0);
            $pdf->MultiCell(0, 8, 'VIAJE DE CREW: ' . $viaje['crew'], 0, 'C', 0, 1, '', '', true, 0, false, true, 8, 'M');

            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 6, 'Fecha: ' . $fecha_servicio . ' | Vehículo: ' . $viaje['vehiculo'], 0, 'C', 0, 1, '', '', true, 0, false, true, 6, 'M');
            $pdf->Ln(3); // Pequeño espacio

            // Información del viaje
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(248, 249, 250); // #f8f9fa
            $pdf->SetTextColor(0);
            $pdf->Cell(0, 7, 'TOTAL SERVICIOS: ' . count($viaje['servicios']), 1, 1, 'L', 1); // 1,1,1: ancho, alto, borde, salto línea
            $pdf->Ln(3);

            // Tabla de servicios
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(44, 62, 80); // #2c3e50
            $pdf->SetTextColor(255);

            // === DEFINIR ALTURA DE FILA Y TAMAÑO DE QR ===
            // Esta altura debe ser suficiente para contener texto multilínea y el QR.
            $row_height = 25; // Altura de la fila en mm.
            $qr_size = 20;     // Tamaño del QR en mm.
            // Espacio vertical entre el borde inferior del QR y la línea divisoria de la siguiente fila
            $qr_bottom_margin = ($row_height - $qr_size) / 2;
            if ($qr_bottom_margin < 2) { // Garantizar un margen mínimo
                $qr_bottom_margin = 2;
                // Ajustar altura de fila si es necesario
                $row_height = $qr_size + 2 * $qr_bottom_margin;
            }
            
            // Anchuras de las columnas (deben sumar menos que el ancho de página útil en Portrait A4)
            // Ancho página útil A4 Portrait (210mm) - márgenes (15mm*2) = 180mm
            $w = array(8, 20, 45, 55, 32); // Total: 160mm (dejando espacio)
            // Asegurarse de que la última columna (QR) tenga el ancho suficiente
            if ($w[4] < $qr_size + 4) { // QR + márgenes
                 $w[4] = $qr_size + 4;
            }
            
            // Fila de encabezados de columnas
            $pdf->Cell($w[0], 7, '#', 1, 0, 'C', 1);
            $pdf->Cell($w[1], 7, 'ID Servicio', 1, 0, 'C', 1);
            $pdf->Cell($w[2], 7, 'Cliente', 1, 0, 'C', 1);
            $pdf->Cell($w[3], 7, 'Dirección', 1, 0, 'C', 1);
            $pdf->Cell($w[4], 7, 'QR', 1, 1, 'C', 1); // 1,1,1: ancho, alto, borde, salto línea

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor(255);
            $pdf->SetTextColor(0);

            // Calcular la posición X de la columna QR
            $margin_left = 15; // Debe coincidir con SetMargins
            $x_qr_columna = $margin_left + $w[0] + $w[1] + $w[2] + $w[3];
            
            // Ajustar X para centrar el QR dentro de su celda
            $qr_x_offset = ($w[4] - $qr_size) / 2;

            // === OBTENER POSICIÓN Y INICIAL DESPUÉS DE ENCABEZADOS ===
            $y_after_headers = $pdf->GetY();

            // Agregar filas de servicios y generar QRs
            foreach ($viaje['servicios'] as $index => $servicio) {
                 // Calcular la posición Y para el inicio de esta fila
                $y_row_start = $y_after_headers + ($index * $row_height);
                
                // Calcular la posición Y para centrar el QR verticalmente en la fila
                $qr_y_centered = $y_row_start + (($row_height - $qr_size) / 2);

                // === DIBUJAR FILA CON TEXTO MULTILÍNEA ===
                // Establecer la posición Y para la fila actual
                $pdf->SetY($y_row_start);
                
                // Columna #
                $pdf->SetX($margin_left);
                $pdf->MultiCell($w[0], $row_height, $index + 1, 1, 'C', 0, 0, '', '', true, 0, false, true, $row_height, 'M');
                
                // Columna ID Servicio
                $pdf->SetX($margin_left + $w[0]);
                $pdf->MultiCell($w[1], $row_height, $servicio['id_servicio'], 1, 'L', 0, 0, '', '', true, 0, false, true, $row_height, 'M');
                
                // Columna Cliente (multilínea)
                $pdf->SetX($margin_left + $w[0] + $w[1]);
                // Limitar longitud para evitar texto demasiado largo si no se divide
                $cliente_text = $servicio['cliente']; // No truncamos, dejamos que MultiCell lo maneje
                $pdf->MultiCell($w[2], $row_height, $cliente_text, 1, 'L', 0, 0, '', '', true, 0, false, true, $row_height, 'M');
                
                // Columna Dirección (multilínea)
                $pdf->SetX($margin_left + $w[0] + $w[1] + $w[2]);
                $direccion_text = $servicio['direccion']; // No truncamos, dejamos que MultiCell lo maneje
                $pdf->MultiCell($w[3], $row_height, $direccion_text, 1, 'L', 0, 0, '', '', true, 0, false, true, $row_height, 'M');
                
                // Columna QR (vacía, se dibujará el código encima)
                $pdf->SetX($margin_left + $w[0] + $w[1] + $w[2] + $w[3]);
                $pdf->MultiCell($w[4], $row_height, '', 1, 'C', 0, 1, '', '', true, 0, false, true, $row_height, 'M');
                // El parámetro `1` en el salto de línea mueve el cursor Y a la siguiente posición
                
                // === OBTENER LA ALTURA REAL DE LA FILA DIBUJADA (opcional, para más precisión) ===
                // $y_after_row = $pdf->GetY();
                // $actual_row_height = $y_after_row - $y_row_start;
                // Si se necesita precisión extrema, se podría recalcular $qr_y_centered aquí.
                // Pero con MultiCell y altura fija, debería ser suficientemente preciso.

                // === GENERAR Y POSICIONAR QR INDIVIDUAL ===
                $url_qr = 'https://positron4tx.ddns.net:3004/QR/control.php?id_servicio=' . $servicio['id_servicio'];

                // === USAR color_crew PARA EL COLOR DEL QR ===
                $qr_color = [0, 102, 204]; // Color azul por defecto
                if (!empty($servicio['color_crew'])) {
                    // Suponiendo que color_crew viene en formato hex (ej: "#FF5733" o "FF5733")
                    $color_crew = ltrim($servicio['color_crew'], '#');
                    if (strlen($color_crew) == 6) {
                        $qr_color = [
                            hexdec(substr($color_crew, 0, 2)), // R
                            hexdec(substr($color_crew, 2, 2)), // G
                            hexdec(substr($color_crew, 4, 2))  // B
                        ];
                    }
                    // Si el formato no es válido, se mantiene el color por defecto.
                }

                // Posicionar QR
                $qr_x = $x_qr_columna + $qr_x_offset;
                $qr_y = $qr_y_centered;

                error_log('Insertando QR para servicio ' . $servicio['id_servicio'] . ' en posición (' . $qr_x . ', ' . $qr_y . ') con color RGB(' . implode(',', $qr_color) . ')');

                // Insertar el código QR en la posición calculada
                $pdf->write2DBarcode(
                    $url_qr,         // Datos del código QR (la URL)
                    'QRCODE,H',      // Tipo de código de barras (H para High error correction)
                    $qr_x,           // Posición X calculada
                    $qr_y,           // Posición Y calculada
                    $qr_size,        // Ancho en mm
                    $qr_size,        // Alto en mm
                    array(
                        'border' => 0,               // Sin borde
                        'vpadding' => 1,             // Padding vertical
                        'hpadding' => 1,             // Padding horizontal
                        'fgcolor' => $qr_color,      // Color dinámico basado en color_crew
                        'bgcolor' => array(255, 255, 255), // Fondo blanco
                        'module_width' => 0.6,       // Ancho del módulo
                        'module_height' => 0.6       // Alto del módulo
                    ),
                    'N' // Posición de alineación: 'N' No cambia la posición actual del cursor
                );
                
                // El cursor ya se movió a la siguiente línea por el MultiCell() de la columna QR.
            }

            $pdf->Ln(5); // Espacio antes de instrucciones

            // Instrucciones
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(212, 237, 218); // #d4edda
            $pdf->Cell(0, 7, 'Instrucciones:', 1, 1, 'L', 1); // 1,1,1: ancho, alto, borde, salto línea

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor(255);
            $instrucciones_texto = "• Escanea el QR de cada servicio al llegar\n" .
                                   "• Registra inicio y finalización individualmente\n" .
                                   "• Reporta incidencias específicas por servicio";

            if (!is_string($instrucciones_texto)) {
                 error_log('Advertencia: Instrucciones no son una cadena válida.');
                 $instrucciones_texto = "Instrucciones no disponibles.";
            }

            $pdf->MultiCell(0, 6, $instrucciones_texto, 1, 'L', 1); 

            // Generar el contenido del PDF
            $output = $pdf->Output('documento.pdf', 'S');
            error_log('PDF generado, tamaño: ' . strlen($output));

            return $output;

        } catch (\Exception $e) {
            error_log('EXCEPTION en crearConTCPDF: ' . $e->getMessage());
            error_log('EXCEPTION trace: ' . $e->getTraceAsString());
            throw $e; 
        } catch (\Error $e) {
            error_log('ERROR FATAL en crearConTCPDF: ' . $e->getMessage());
            error_log('ERROR FATAL trace: ' . $e->getTraceAsString());
            throw $e; 
        } catch (\Throwable $e) {
            error_log('THROWABLE en crearConTCPDF: ' . $e->getMessage());
            error_log('THROWABLE trace: ' . $e->getTraceAsString());
            throw $e; 
        }
    }
    // === FIN FUNCIÓN TCPDF ===


    private function generarNombreArchivoPDF($crew, $fecha_servicio)
    {
        $nombre_limpio = preg_replace('/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑüÜ\-_]/', '', $crew);
        $nombre_limpio = trim($nombre_limpio);
        $nombre_limpio = str_replace([' ', ','], '_', $nombre_limpio);

        if (empty($nombre_limpio)) {
            $nombre_limpio = 'crew_desconocido';
        }

        $nombre_archivo = 'viaje_' . strtolower($nombre_limpio) . '_' . str_replace('-', '', $fecha_servicio) . '.pdf';

        if (strlen($nombre_archivo) > 100) {
            $nombre_archivo = substr($nombre_archivo, 0, 100) . '.pdf';
        }

        error_log('Nombre de archivo generado: ' . $nombre_archivo);
        return $nombre_archivo;
    }
}
?>