<?php
if (isset($url[0])) {
    $proceso_actual = $url[0];
} else {
    $proceso_actual = "serviciosLista";
}
if (isset($url[1])) {
    $ruta_retorno = RUTA_APP . "/" . $url[1];
} else {
    $ruta_retorno = RUTA_APP . "/dashboard";
}
if (isset($url[2])) {
    $pagina_retorno = $url[2];
} else {
    $pagina_retorno = 1;
}

switch ($ruta_retorno) {
    case RUTA_APP . "/clientes":
        if (isset($url[3])) {
            $id_cliente = $url[3];
        } else {
            $id_cliente = 0;
        }

        $datos = [
            "origen" => 'clientes',
            'id_codigo' => $id_cliente
        ];
        break;
    case RUTA_APP . "/direcciones":
        if (isset($url[3])) {
            $id_direccion = $url[3];
        } else {
            $id_direccion = 0;
        }

        $datos = [
            "origen" => 'direcciones',
            'id_codigo' => $id_direccion
        ];
        break;
    case RUTA_APP . "/vehiculos":
        break;
}

$row = $servicios->buscarLista($datos);

switch ($ruta_retorno) {
    case RUTA_APP . "/clientes":
        $cliente = $row[0]['cliente'];
        $relacionado = $row[0]['cliente'];
        $concepto = "Customer-related services ";
        $opt = 1;
        break;
    case RUTA_APP . "/direcciones":
        $direccion = $row[0]['direccion'];
        $relacionado = $row[0]['direccion'];
        $concepto = "Services related to the address: ";
        $opt = 2;
        break;
    case RUTA_APP . "/vehiculos":
        break;
}


$opcion = "serviciosLista";
$encabezadoVista = PROJECT_ROOT . "/app/views/inc/encabezadoVista.php";

function formatearFechaParaDisplay($fecha_mysql)
{
    if (!$fecha_mysql)
        return '';

    $fecha = new DateTime($fecha_mysql);
    $ano = $fecha->format('Y');

    // Meses en inglés abreviados
    $mesesIngles = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec'
    ];
    $mesAbrev = $mesesIngles[(int) $fecha->format('n') - 1]; // n = 1-12

    // Días en español
    $diasSemana = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $diaSemana = $diasSemana[(int) $fecha->format('w')]; // w = 0 (domingo) a 6

    $dia = $fecha->format('d');

    return [
        'ano' => $ano,
        'mes' => $mesAbrev,
        'dia' => "(" . $dia . ") " . $diaSemana
    ];
}

function getContrastColor($hexColor)
{
    // Eliminar el símbolo # si existe
    $hex = ltrim($hexColor, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return '#000'; // fallback
    }

    // Convertir a RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    // Fórmula de luminancia relativa (WCAG)
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

    // Si la luminancia es < 0.5, es oscuro → usar blanco; si no, negro
    return $luminance < 0.5 ? '#ffffff' : '#000000';
}

function getBackgoundStatus($id_status, $servicios)
{
    $lista_status = $servicios->status_servicios();

    foreach ($lista_status as $status) {
        if (isset($status['id_status']) && $status['id_status'] == $id_status) {
            return $status['color'];
        }
    }
    return '#cccccc'; // color por defecto si no se encuentra
}

function extraerHoraDesdeDatetime($fecha_hora)
{
    // Definir valores considerados "vacíos"
    $valores_vacios = ['', null, '0000-00-00', '0000-00-00 00:00:00'];

    if (in_array($fecha_hora, $valores_vacios, true)) {
        return '---';
    }

    // Intentar crear un objeto DateTime
    try {
        $dt = new DateTime($fecha_hora);
        return $dt->format('H:i'); // Ej: "14:30"
    } catch (Exception $e) {
        return '---'; // Si el formato es inválido
    }
}

function calcularTiempoTranscurrido($fecha_inicio, $fecha_fin)
{
    // Valores considerados "vacíos"
    $valores_vacios = ['', null, '0000-00-00 00:00:00', '0000-00-00'];

    if (in_array($fecha_inicio, $valores_vacios, true) || in_array($fecha_fin, $valores_vacios, true)) {
        return '---';
    }

    try {
        $inicio = new DateTime($fecha_inicio);
        $fin = new DateTime($fecha_fin);

        if ($fin < $inicio) {
            return '---'; // Fecha de fin no puede ser antes que la de inicio
        }

        $intervalo = $inicio->diff($fin);

        $partes = [];

        if ($intervalo->d > 0) {
            $partes[] = $intervalo->d . ' d' . ($intervalo->d > 1 ? 's' : '');
        }
        if ($intervalo->h > 0) {
            $partes[] = $intervalo->h . ' h' . ($intervalo->h > 1 ? 's' : '');
        }
        if ($intervalo->i > 0 || (empty($partes) && $intervalo->i === 0)) {
            // Si no hay días ni horas, al menos mostrar "0 minutes"
            $partes[] = $intervalo->i . ' m' . ($intervalo->i !== 1 ? 's' : '');
        }

        if (empty($partes)) {
            return '0 minutes';
        }

        return implode(' ', $partes);

    } catch (Exception $e) {
        return '---';
    }
}
?>


<main>

    <?php
    require_once $encabezadoVista;
    ?>

    <div class="form-container">
        <div class="form-group-ct-inline" style="margin-left: auto; margin-right: auto; justify-content: center;">
            <h3><?php echo $concepto; ?></h3>
            <h3 style="color: #6d1a72ff; font-weight: bold;"><label
                    class="ancho_label1"><?php echo htmlspecialchars(string: $relacionado); ?></label></h3>
        </div>
    </div>

    <div class="contenedor-scroll-80vh">
        <div class="encabezado-fijo">
            <div class="fila">
                <div class="celda-titulo"># Service</div>
                <div class="celda-titulo">Date</div>
                <?php
                switch ($opt) {
                    case 1:
                        ?>
                        <div class="celda-titulo">Address</div>
                        <div class="celda-titulo">Truck</div>
                        <?php
                        break;
                    case 2:
                        ?>
                        <div class="celda-titulo">Customer</div>
                        <div class="celda-titulo">Truck</div>
                        <?php
                        break;
                    case 3:
                        ?>
                        <div class="celda-titulo">Customer</div>
                        <div class="celda-titulo">Address</div>
                        <?php
                        break;
                }
                ?>
                <div class="celda-titulo">Crew</div>
                <div class="celda-titulo">Status</div>
                <div class="celda-titulo">Motors</div>
                <div class="celda-titulo"><i class="fa-solid fa-gear"></i> Serv. Det.</div>
            </div>
        </div>
        <div class="cuerpo-scroll">
            <!-- Aquí van los registros, que serán scrolleables -->
            <?php $contador = 1;
            foreach ($row as $key) { ?>
                <?php
                $fechaData = formatearFechaParaDisplay($key['fecha_programada']);
                ?>
                <div class="fila-dato" style="background: <?php echo getBackgoundStatus($key['id_status'], $servicios); ?>">
                    <div class="celda celda-dato field-servicio form-data">
                        <?php echo htmlspecialchars($key['id_servicio']); ?>
                    </div>
                    <div class="celda">
                        <div class="fecha-programada">
                            <div class="fecha-ano" id="fecha-ano"><?php echo $fechaData['ano']; ?></div>
                            <div class="fecha-mes" id="fecha-mes"><?php echo $fechaData['mes']; ?></div>
                            <div class="fecha-dia" id="fecha-dia"><?php echo $fechaData['dia']; ?></div>
                        </div>
                    </div>

                    <?php
                    switch ($opt) {
                        case 1:
                            $ruta_direcciones = "direccionesVista/" . $key['id_direccion'] . "/" . $key['id_address_clas'] . "/serviciosLista/clientes/" . $pagina_retorno . "/". $key['id_cliente'];
                            $ruta_det_servicio = RUTA_APP . "/" . $ruta_direcciones;
			            	$href = $ruta_det_servicio . $rows['id_cliente'] 
                            ?>
                            <div class="celda">
                                <div class="center">
                                    <div class="btn-2">
                                        <p>🗺️</p>
                                        <a href="<?php echo $href;?>"><span><?php echo htmlspecialchars(string: $key['direccion']); ?></span></a>
                                    </div>
                                </div>
                            </div>

                            <div class="celda">
                                <div class="div_truck"
                                    style="background: <?php echo $key['crew_color_principal']; ?>; color: <?php echo getContrastColor($key['crew_color_principal']); ?>;">
                                    <?php echo htmlspecialchars(string: $key['truck']); ?>
                                </div>
                            </div>
                            <?php
                            break;
                        case 2:
                            ?>
                            <div class="celda">
                                <div class="div_client"
                                    style="color: <?php echo getContrastColor(getBackgoundStatus($key['id_status'], $servicios)); ?>;">
                                    <?php echo htmlspecialchars(string: $key['cliente']); ?>
                                </div>
                            </div>

                            <div class="celda">
                                <div class="div_truck"
                                    style="background: <?php echo $key['crew_color_principal']; ?>; color: <?php echo getContrastColor($key['crew_color_principal']); ?>;">
                                    <?php echo htmlspecialchars(string: $key['truck']); ?>
                                </div>
                            </div>
                            <?php
                            break;
                        case 3:
                            ?>
                            <div class="celda-titulo">Customer</div>
                            <div class="celda-titulo">Address</div>
                            <?php
                            break;
                    }
                    ?>

                    <div class="celda">
                        <div class="grid_crew">
                            <?php
                            if (!empty(trim($key['crew_integrantes'][0]['nombre_completo']))) {
                                ?>
                                <div class="grid_crew_01"
                                    style="background: <?php echo $key['crew_integrantes'][0]['color']; ?>; color: <?php echo getContrastColor($key['crew_integrantes'][0]['color']); ?>;">
                                    <?php echo $key['crew_integrantes'][0]['nombre_completo']; ?>
                                </div>
                                <?php
                            }
                            ?>

                            <?php
                            if (!empty(trim($key['crew_integrantes'][1]['nombre_completo']))) {
                                ?>
                                <div class="grid_crew_02"
                                    style="background: <?php echo $key['crew_integrantes'][1]['color']; ?>; color: <?php echo getContrastColor($key['crew_integrantes'][1]['color']); ?>;">
                                    <?php echo $key['crew_integrantes'][1]['nombre_completo']; ?>
                                </div>
                                <?php
                            }
                            ?>

                            <?php
                            if (!empty(trim($key['crew_integrantes'][2]['nombre_completo']))) {
                                ?>
                                <div class="grid_crew_03"
                                    style="background: <?php echo $key['crew_integrantes'][2]['color']; ?>; color: <?php echo getContrastColor($key['crew_integrantes'][2]['color']); ?>;">
                                    <?php echo $key['crew_integrantes'][2]['nombre_completo']; ?>
                                </div>
                                <?php
                            }
                            ?>

                            <?php
                            if (!empty(trim($key['crew_integrantes'][3]['nombre_completo']))) {
                                ?>
                                <div class="grid_crew_04"
                                    style="background: <?php echo $key['crew_integrantes'][3]['color']; ?>; color: <?php echo getContrastColor($key['crew_integrantes'][3]['color']); ?>;">
                                    <?php echo $key['crew_integrantes'][3]['nombre_completo']; ?>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    <div class="celda">
                        <div class="div_status">
                            <?php echo htmlspecialchars(string: $key['s_status']); ?>
                        </div>
                    </div>

                    <div class="celda">
                        <div class="dis-tiempos">
                            <div class="grid_motores"
                                style="color: <?php echo getContrastColor(getBackgoundStatus($key['id_status'], $servicios)); ?>;">
                                <div class="grid_motores_01">
                                    Start/End
                                </div>
                                <div class="grid_motores_02">
                                    M1
                                </div>
                                <div class="grid_motores_03">
                                    M2
                                </div>

                                <div class="grid_motores_04">
                                    ▶️
                                </div>
                                <div class="grid_motores_05">
                                    <?php echo htmlspecialchars(extraerHoraDesdeDatetime($key['hora_aviso_usuario'])); ?>
                                </div>
                                <div class="grid_motores_06">
                                    <?php echo htmlspecialchars(extraerHoraDesdeDatetime($key['hora_inicio_gps'])); ?>
                                </div>
                                <div class="grid_motores_07">
                                    ⏹️
                                </div>
                                <div class="grid_motores_08">
                                    <?php echo htmlspecialchars(extraerHoraDesdeDatetime($key['hora_finalizado'])); ?>
                                </div>
                                <div class="grid_motores_09">
                                    <?php echo htmlspecialchars(extraerHoraDesdeDatetime($key['hora_fin_gps'])); ?>
                                </div>
                                <div class="grid_motores_10">
                                    ⏱️
                                </div>
                                <div class="grid_motores_11">
                                    <?php echo htmlspecialchars(calcularTiempoTranscurrido($key['hora_aviso_usuario'], $key['hora_finalizado'])); ?>
                                </div>
                                <div class="grid_motores_12">
                                    <?php echo htmlspecialchars(calcularTiempoTranscurrido($key['hora_inicio_gps'], $key['hora_fin_gps'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="celda">
                        <div class="center">
                            <div class="btn-2">
                                <p><i class="fa-solid fa-gear"></i></p>
                                <a href=""><span> Service Detail</span></a>
                            </div>
                        </div>                        
                    </div>
                </div>
                <?php
                $contador++;
            } ?>
        </div>
    </div>

</main>

<script src="<?= RUTA_REAL ?>/app/views/inc/js/func_comm.js?v=<?= time() ?>"></script>
<script type="text/javascript">
    const ruta_retorno = "<?php echo $ruta_retorno; ?>";
    const origen = "<?php echo $ruta_origen; ?>";

    function mostrarFechaProgramada(fechaInput = null) {
        // Si no se pasa una fecha, usa hoy
        const fecha = fechaInput ? new Date(fechaInput) : new Date();

        // 1. Año → se muestra tal cual
        const ano = fecha.getFullYear();

        // 2. Mes abreviado en inglés (Jan, Feb, ..., Dec)
        const mesesIngles = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const mesAbrev = mesesIngles[fecha.getMonth()];

        // 3. Día de la semana en español
        const diasSemana = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const diaSemana = diasSemana[fecha.getDay()];

        // Actualizar el DOM
        document.getElementById('fecha-ano').textContent = ano;
        document.getElementById('fecha-mes').textContent = mesAbrev;
        document.getElementById('fecha-dia').textContent = diaSemana;
    }

    document.addEventListener('DOMContentLoaded', async () => {
        mostrarFechaProgramada();
    });

</script>