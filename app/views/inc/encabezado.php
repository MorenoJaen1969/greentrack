<?php
switch ($opcion) {
    case 'address_clas':
        $titulo_act = "Address Classification";
        $clase = "fa-solid fa-glasses";
        $nuevo_reg = "address_clasNew";
        break;

    case 'address_type':
        $titulo_act = "Address Type";
        $clase = "fa-solid fa-tags";
        $nuevo_reg = "address_typeNew";
        break;

    case 'clientes':
        $titulo_act = "Customers";
        $clase = "fa-solid fa-user-pen";
        $nuevo_reg = "clientesNew";
        break;

    case 'contratos':
        $titulo_act = "Contracts";
        $clase = "fa-solid fa-file-signature";
        $nuevo_reg = "contratosNew";
        break;

    case 'crew':
        $titulo_act = "Field Staff ";
        $clase = "fa-solid fa-truck-pickup";
        $nuevo_reg = "crewNew";
        break;

    case 'dias_no_actividad':
        $titulo_act = "Non-Working Days or Holidays";
        $clase = "fa-solid fa-circle-xmark";
        $nuevo_reg = "dias_no_actividadNew";
        break;

    case 'direcciones':
        $titulo_act = "Address";
        $clase = "fa-solid fa-map-location-dot";
        $nuevo_reg = "direccionNew";
        break;

    case 'proveedores':
        $titulo_act = "Suppliers";
        $clase = "fa-solid fa-boxes-packing";
        $nuevo_reg = "proveedoresNew";
        break;

    case 'route_day':
        $titulo_act = "Route assignment on specific days";
        $clase = "fa-solid fa-solid fa-route";
        $nuevo_reg = "";
        break;

    case 'salas':
        $titulo_act = "Rooms";
        $clase = "fa-solid fa-chalkboard-user";
        $nuevo_reg = "salasNew";
        break;

    case 'status_all':
        $titulo_act = "Status";
        $clase = "fa-solid fa-chart-column";
        $nuevo_reg = "status_allNew";
        break;

    case 'vehiculos':
        $titulo_act = "Vehicles";
        $clase = "fa-solid fa-truck-pickup";
        $nuevo_reg = "vehiculosNew";
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Módulo no válido: ' . $modulo]);
        exit();
}
?>

<div class="container-principal">
    <div class="dis_ver">
        <div class="tit_primero">
            <div class="grid-tit_primero_1">
                <div class="aling-left">
                    <a href="#" class="btn09" id="retorno" name="retorno">
                        <div class="transition">
                            <span class="fa-solid fa fa-reply">&nbsp</span>
                            Home
                        </div>
                    </a>
                </div>
            </div>
            <?php
                if ($opcion <> "route_day") {
            ?>
            <div class="grid-tit_primero_2">
                <div class="row_titulo-">
                    <div class="row_titulo-">
                        <div class="field is-grouped">
                            <p class="control is-expanded">
                                <input class="input is-rounded" type="text" id="txt_buscador" name="txt_buscador"
                                    placeholder="What are you looking for?" pattern="[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ ]{1,30}"
                                    maxlength="30">
                            </p>
                            <p class="control">
                                <button class="button1 is-info" type="button" id="btn-busca">Search</button>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <?php
                }
            ?>
        </div>

        <div class="grid_titulo">
            <?php
                if ($opcion <> "route_day") {
            ?>
            <div class="grid-titulo-item1">
                <div class="etiqueta is-link is-rounded is-small">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text">Show</span>
                            <select class="form-select-input" id="registrosPorPagina">
                                <option value="5" <?= $registrosPorPagina == 5 ? 'selected' : '' ?>>5</option>
                                <option value="10" <?= $registrosPorPagina == 10 ? 'selected' : '' ?>>10</option>
                                <option value="25" <?= $registrosPorPagina == 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $registrosPorPagina == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $registrosPorPagina == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                            <span class="input-group-text">Records</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php
                }
            ?>
            <div class="grid-titulo-item2">
                <h2 class="titulo_form">
                    <span class="<?php echo $clase; ?>">&nbsp</span>
                    <?php echo $titulo_act; ?>
                </h2>
            </div>
        </div>

        <?php
            if ($opcion <> "route_day") {
        ?>
        <div class="tit_segundo">
            <div class="row_titulo-01">
                <div class="row">
                    <a href="<?php echo RUTA_APP . "/". $nuevo_reg; ?>" class="button1 is-link is-rounded is-small">New
                        Registration</a>
                </div>
            </div>
        </div>
        <?php
            }
        ?>
    </div>
</div>