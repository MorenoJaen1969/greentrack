<?php
switch ($opcion) {
    case 'address_clasNew':
        $titulo_act = "New Address Classification";
        $clase = "fa-solid fa-glasses";
        break;

    case 'address_typeNew':
        $titulo_act = "New Address Type";
        $clase = "fa-solid fa-tags";
        break;

    case 'clienteNew':
        $titulo_act = "New Customer";
        $clase = "fa-solid fa-user-pen";
        break;

    case 'contratoNew':
        $titulo_act = "New Contract";
        $clase = "fa-solid fa-file-signature";
        break;

    case 'dias_no_actividad':
        $titulo_act = "New Non-Working Days or Holidays";
        $clase = "fa-solid fa-circle-xmark";
        break;

    case 'direccionNew':
        $titulo_act = "New Address";
        $clase = "fa-solid fa-map-location-dot";
        break;

    case 'proveedorNew':
        $titulo_act = "New Supplier";
        $clase = "fa-solid fa-boxes-packing";
        break;

    case 'vehiculoNew':
        $titulo_act = "New Vehicle";
        $clase = "fa-solid fa-truck-pickup";
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Opción no válida: ' . $opcion]);
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
                            Origin
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <div class="grid_titulo">
            <div class="grid-titulo-item2">
                <h2 class="titulo_form">
                    <span class="<?php echo $clase; ?>">&nbsp</span>
                    <?php echo $titulo_act; ?>
                </h2>
            </div>
        </div>
    </div>
</div>