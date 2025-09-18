<?php
namespace app\lib;

// Asegúrate de tener TCPDF instalado
require_once APP_R_PROY . 'app/lib/tcpdf/tcpdf.php';

class Motor3PDFGenerator
{
	private $log_path;
	private $logFile;
	private $errorLogFile;

    	private $o_f;

	public function __construct()
	{ 
		// Nombre del controlador actual abreviado para reconocer el archivo
		$nom_controlador = "Motor3PDFGenerator";
		// ____________________________________________________________________

		$this->log_path = APP_R_PROY . 'app/logs/Motor3/';

		if (!file_exists($this->log_path)) {
			mkdir($this->log_path, 0775, true);
			chgrp($this->log_path, 'www-data');
			chmod($this->log_path, 0775); // Asegurarse de que el directorio sea legible y escribible
		}

		$this->logFile = $this->log_path . $nom_controlador . '_' . date('Y-m-d') . '.log';
		$this->errorLogFile = $this->log_path . $nom_controlador . '_error_' . date('Y-m-d') . '.log';

		$this->initializeLogFile(file: $this->logFile);
		$this->initializeLogFile($this->errorLogFile);

		$this->verificarPermisos();

		// rotación automatica de log (Elimina logs > XX dias)
		$this->rotarLogs(15);

	}

	private function initializeLogFile($file)
	{
		if (!file_exists($file)) {
			$initialContent = "[" . date('Y-m-d H:i:s') . "] Archivo de log iniciado" . PHP_EOL;
			$created = file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
			if ($created === false) {
				$this->log("No se pudo crear el archivo de log: " . $file);
			} else {
				chmod($file, 0644); // Asegurarse de que el archivo sea legible y escribible
			}
			if (!is_writable($file)) {
				throw new \Exception("El archivo de log no es escribible: " . $file);
			}
		}
	}

	private function verificarPermisos()
	{
		if (!is_writable($this->log_path)) {
			$this->log("No hay permiso de escritura en: " . $this->log_path);
		}
	}

	private function rotarLogs($dias)
	{
		$archivos = glob($this->log_path . '*.log');
		$fechaLimite = time() - ($dias * 24 * 60 * 60);

		foreach ($archivos as $archivo) {
			if (filemtime($archivo) < $fechaLimite) {
				unlink($archivo);
			}
		}
	}

	private function log($message, $isError = false)
	{
		$file = $isError ? $this->errorLogFile : $this->logFile;
		if (!file_exists($file)) {
			$initialContent = "[" . date('Y-m-d H:i:s') . "] Archivo de log iniciado" . PHP_EOL;
			$created = file_put_contents($file, $initialContent, FILE_APPEND | LOCK_EX);
			if ($created === false) {
				$this->log("No se pudo crear el archivo de log: " . $file);
				return;
			}
			chmod($file, 0644); // Asegurarse de que el archivo sea legible y escribible
		}
		$logEntry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
		file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
	}

	private function logWithBacktrace($message, $isError = true)
	{
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		$caller = $backtrace[1] ?? $backtrace[0];
		$logMessage = sprintf("[%s] %s - Called from %s::%s (Line %d)%s%s", date('Y-m-d H:i:s'), $message, $caller['class'] ?? '', $caller['function'], $caller['line'], PHP_EOL, "Stack trace:" . PHP_EOL . json_encode($backtrace, JSON_PRETTY_PRINT));
		file_put_contents($this->errorLogFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

    // === FUNCIÓN PARA GENERAR PDFs ===
    public function generarPDFsDesdeServicios($servicios, $fecha_servicio)
    {
        $this->log('=== INICIO generarPDFsDesdeServicios ===');
        $this->log('Total servicios recibidos: ' . count($servicios));

        $pdf_urls = [];
        $viajes = $this->agruparServiciosPorCrew($servicios);

        $this->log('Total viajes agrupados: ' . count($viajes));

        // Directorio para PDFs
        $directorio_pdf = $_SERVER['DOCUMENT_ROOT'] . '/PDF/';
        $this->log('Directorio PDF: ' . $directorio_pdf);

        if (!file_exists($directorio_pdf)) {
            $this->log('Creando directorio PDF...');
            if (!mkdir($directorio_pdf, 0755, true)) {
                $this->logWithBacktrace('ERROR: No se pudo crear directorio: ' . $directorio_pdf);
                return $pdf_urls;
            }
        }

        // Generar PDF para cada crew
        foreach ($viajes as $crew => $viaje) {
            try {
                $this->log('Generando PDF para crew: ' . $crew);
                $pdf_content = $this->crearPDFSimple($viaje, $fecha_servicio);
                $nombre_archivo = $this->generarNombreArchivoPDF($crew, $fecha_servicio);
                $ruta_completa = $directorio_pdf . $nombre_archivo;

                if (file_put_contents($ruta_completa, $pdf_content) !== false) {
                    $this->log('PDF guardado: ' . $ruta_completa);
                    $pdf_urls[] = [
                        'crew' => $crew,
                        'url' => $nombre_archivo,
                        'servicios_count' => count($viaje['servicios'])
                    ];
                } else {
                    $this->logWithBacktrace('ERROR: No se pudo guardar PDF: ' . $ruta_completa);
                }

            } catch (\Exception $e) {
                $this->logWithBacktrace("Exception generando PDF para {$crew}: " . $e->getMessage());
            } catch (\Error $e) {
                $this->logWithBacktrace("Error generando PDF para {$crew}: " . $e->getMessage());
            }
        }

        $this->log('=== FIN generarPDFsDesdeServicios ===');
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
        $this->log('INICIO crearPDFSimple para crew: ' . $viaje['crew']);

        // Verificar si TCPDF está disponible
        if (class_exists('\TCPDF')) {
            $this->log('Llamando a crearConTCPDF...');
            $resultado = $this->crearConTCPDF($viaje, $fecha_servicio);
            $this->log('crearConTCPDF completado, tamaño resultado: ' . strlen($resultado));
            return $resultado;
        } else {
            $this->logWithBacktrace('TCPDF no disponible, devolviendo fallback...');
            // Fallback simple si TCPDF falla o no está disponible
            return "PDF no disponible. TCPDF no encontrado.";
        }
    }


    // === FUNCIÓN PARA CREAR PDF DIRECTAMENTE CON TCPDF ===
    // Corregida para mejor espaciado, texto multilínea y color de QR.
    private function crearConTCPDF($viaje, $fecha_servicio)
    {
        $this->log('INICIO crearConTCPDF para: ' . $viaje['crew']);

        if (!class_exists('\TCPDF')) {
             $this->logWithBacktrace('TCPDF NO EXISTE');
             throw new \Exception('TCPDF no está disponible.');
        }

        try {
            $this->log('Creando instancia TCPDF...');
            // === MANTENER orientación vertical ===
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false); // 'P' para Portrait
            $this->log('Instancia TCPDF creada');

            $pdf->SetCreator('GreenTrack');
            $pdf->SetTitle('Viaje ' . $viaje['crew']);
            // === CONFIGURAR ENCABEZADO Y PIE DE PÁGINA ===
            // Establecer funciones para encabezado y pie si se desea contenido automático
            // Por ahora, los manejaremos manualmente para más control.
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            // Ajustar márgenes para Portrait
            $pdf->SetMargins(15, 15, 15); // Izquierda, Arriba, Derecha
            // Desactivar salto de página automático para control manual total
            $pdf->SetAutoPageBreak(FALSE, 0); // Desactivado salto de página automático, margen inferior
            $this->log('Configuración PDF completada');

            // === PARÁMETROS PARA PAGINACIÓN ===
            $max_records_per_page = 8; // Máximo número de registros por página
            $total_records = count($viaje['servicios']);
            $total_pages = ceil($total_records / $max_records_per_page);

            // === DEFINIR DIMENSIONES CONSISTENTES ===
            $row_height = 25; // Altura de la fila en mm.
            $qr_size = 20;     // Tamaño del QR en mm.
            // Anchuras de las columnas (deben sumar menos que el ancho de página útil en Portrait A4)
            // Ancho página útil A4 Portrait (210mm) - márgenes (15mm*2) = 180mm
            $w = array(8, 20, 45, 55, 32); // Total: 160mm (dejando espacio)


            // === VARIABLES PARA CONTROL DE PÁGINA ===
            $records_on_current_page = 0;
            $current_page_number = 1;

            // === FUNCIONES AUXILIARES PARA IMPRIMIR ENCABEZADOS ===
            $printMainHeaders = function() use (&$pdf, $viaje, $fecha_servicio, $w) {
                // Encabezado principal            
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
            };

            $printTableHeaders = function() use (&$pdf, $w) {
                // Fila de encabezados de columnas
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(44, 62, 80); // #2c3e50
                $pdf->SetTextColor(255);
                $pdf->Cell($w[0], 7, '#', 1, 0, 'C', 1);
                $pdf->Cell($w[1], 7, 'ID Servicio', 1, 0, 'C', 1);
                $pdf->Cell($w[2], 7, 'Cliente', 1, 0, 'C', 1);
                $pdf->Cell($w[3], 7, 'Dirección', 1, 0, 'C', 1);
                $pdf->Cell($w[4], 7, 'QR', 1, 1, 'C', 1); // 1,1,1: ancho, alto, borde, salto línea
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetFillColor(255);
                $pdf->SetTextColor(0);
            };
              
            $printFooter = function() use (&$pdf, &$current_page_number, $total_pages) {
                // Pie de página con numeración
                $pdf->SetY(-15); // Posicionar 15 mm desde el final de la página
                $pdf->SetFont('helvetica', 'I', 8);
                $pdf->SetTextColor(128);
                $pdf->Cell(0, 10, 'Página '.$current_page_number.' de '.$total_pages, 0, 0, 'C');
            };

            // === INICIAR PRIMERA PÁGINA ===
            $pdf->AddPage();
            $this->log('Página 1 añadida');
            $printMainHeaders();
            $printTableHeaders();
            $records_on_current_page = 0;
            $current_page_number = 1;

            // Calcular la posición X de la columna QR para uso posterior
            $margin_left = 15; // Debe coincidir con SetMargins
            $x_qr_columna = $margin_left + $w[0] + $w[1] + $w[2] + $w[3];
            $qr_x_offset = ($w[4] - $qr_size) / 2; // Para centrar el QR en su celda

            // === OBTENER POSICIÓN Y INICIAL DESPUÉS DE ENCABEZADOS ===
            $y_after_initial_headers = $pdf->GetY();
            $this->log("Y después de encabezados iniciales: " . $y_after_initial_headers);

            // === AGREGAR FILAS DE SERVICIOS ===
            foreach ($viaje['servicios'] as $index => $servicio) {
                // === CONTROL DE SALTO DE PÁGINA MANUAL ===
                if ($records_on_current_page >= $max_records_per_page) {
                    // Imprimir pie de página en la página actual
                    $printFooter();

                    // Incrementar número de página
                    $current_page_number++;

                    // Añadir nueva página
                    $pdf->AddPage();
                    $this->log("Página $current_page_number añadida");

                    // Imprimir encabezados en la nueva página
                    $printMainHeaders(); // Opcional: solo en la primera página, o encabezado reducido
                    $printTableHeaders();

                    // Reiniciar contador de registros en la página actual
                    $records_on_current_page = 0;

                    // Actualizar Y inicial para la nueva página
                    $y_after_initial_headers = $pdf->GetY();
                    $this->log("Nuevo Y después de encabezados: " . $y_after_initial_headers);
                }

                // === CALCULAR POSICIÓN Y PARA LA FILA ACTUAL ===
                $y_row_start = $y_after_initial_headers + ($records_on_current_page * $row_height);
                $this->log("Dibujando fila " . ($index+1) . " en Y=$y_row_start (registro #$records_on_current_page en página $current_page_number)");

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
                $cliente_text = $servicio['cliente'];
                $pdf->MultiCell($w[2], $row_height, $cliente_text, 1, 'L', 0, 0, '', '', true, 0, false, true, $row_height, 'M');

                // Columna Dirección (multilínea)
                $pdf->SetX($margin_left + $w[0] + $w[1] + $w[2]);
                $direccion_text = $servicio['direccion'];
                $pdf->MultiCell($w[3], $row_height, $direccion_text, 1, 'L', 0, 0, '', '', true, 0, false, true, $row_height, 'M');

                // Columna QR (vacía, se dibujará el código encima)
                $pdf->SetX($margin_left + $w[0] + $w[1] + $w[2] + $w[3]);
                $pdf->MultiCell($w[4], $row_height, '', 1, 'C', 0, 1, '', '', true, 0, false, true, $row_height, 'M');
                // El parámetro `1` en el salto de línea mueve el cursor Y a la siguiente posición

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
                    $this->log("Color QR para servicio {$servicio['id_servicio']}: RGB(" . implode(',', $qr_color) . ")");
                }

                // Posicionar QR
                $qr_x = $x_qr_columna + $qr_x_offset;
                $qr_y = $qr_y_centered;

                $this->log('Insertando QR para servicio ' . $servicio['id_servicio'] . ' en posición (' . $qr_x . ', ' . $qr_y . ') con color RGB(' . implode(',', $qr_color) . ')');

                // Insertar el código QR en la posición calculada
                $pdf->write2DBarcode(
                    $url_qr,                    // Datos del código QR (la URL)
                    'QRCODE,H',                 // Tipo de código de barras (H para High error correction)
                    $qr_x,                      // Posición X calculada
                    $qr_y,                      // Posición Y calculada
                    $qr_size,                   // Ancho en mm
                    $qr_size,                   // Alto en mm
                    array(
                        'border' => 0,                          // Sin borde
                        'vpadding' => 1,                        // Padding vertical
                        'hpadding' => 1,                        // Padding horizontal
                        'fgcolor' => $qr_color,                 // Color dinámico basado en color_crew
                        'bgcolor' => array(255, 255, 255),       // Fondo blanco
                        'module_width' => 0.6,                  // Ancho del módulo
                        'module_height' => 0.6                  // Alto del módulo
                    ),
                    'N' // Posición de alineación: 'N' No cambia la posición actual del cursor
                );

                // Incrementar el contador de registros en la página actual
                $records_on_current_page++;
            } // Fin del bucle foreach

            // === IMPRIMIR PIE DE PÁGINA EN LA ÚLTIMA PÁGINA ===
            $printFooter();

            // === AGREGAR INSTRUCCIONES AL FINAL (opcional: solo en la última página) ===
            // Puedes decidir si poner esto en cada página o solo al final.
            // Para ponerlo solo al final:
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(212, 237, 218); // #d4edda
            $pdf->Cell(0, 7, 'Instrucciones:', 1, 1, 'L', 1);

            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetFillColor(255);
            $instrucciones_texto = "• Escanea el QR de cada servicio al llegar\n" .
                                   "• Registra inicio y finalización individualmente\n" .
                                   "• Reporta incidencias específicas por servicio";

            if (!is_string($instrucciones_texto)) {
                $this->log('Advertencia: Instrucciones no son una cadena válida.');
                $instrucciones_texto = "Instrucciones no disponibles.";
            }

            $pdf->MultiCell(0, 6, $instrucciones_texto, 1, 'L', 1);

            // Generar el contenido del PDF
            $output = $pdf->Output('documento.pdf', 'S');
            $this->log('PDF generado, tamaño: ' . strlen($output));

            return $output;

        } catch (\Exception $e) {
            $this->logWithBacktrace('EXCEPTION en crearConTCPDF: ' . $e->getMessage());
            $this->logWithBacktrace('EXCEPTION trace: ' . $e->getTraceAsString());
            throw $e; 
        } catch (\Error $e) {
            $this->logWithBacktrace('ERROR FATAL en crearConTCPDF: ' . $e->getMessage());
            $this->logWithBacktrace('ERROR FATAL trace: ' . $e->getTraceAsString());
            throw $e; 
        } catch (\Throwable $e) {
            $this->logWithBacktrace('THROWABLE en crearConTCPDF: ' . $e->getMessage());
            $this->logWithBacktrace('THROWABLE trace: ' . $e->getTraceAsString());
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

        $this->log('Nombre de archivo generado: ' . $nombre_archivo);
        return $nombre_archivo;
    }
}
?>