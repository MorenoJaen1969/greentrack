<?php
switch ($opcion) {
    case 'address_clasVista':
        $titulo_act = "Address Classification view";
        $clase = "fa-solid fa-glasses";
        break;

    case 'address_typeVista':
        $titulo_act = "Address Type view";
        $clase = "fa-solid fa-tags";
        break;

    case 'clienteVista':
        $titulo_act = "Customer view";
        $clase = "fa-solid fa-user-pen";
        break;

    case 'contratosVista':
        $titulo_act = "Contract view";
        $clase = "fa-solid fa-file-signature";
        break;

    case 'dias_no_actividad':
        $titulo_act = "Non-Working Days or Holidays view";
        $clase = "fa-solid fa-circle-xmark";
        break;

    case 'direccionesVista':
        $titulo_act = "Address view";
        $clase = "fa-solid fa-map-location-dot";
        break;

    case 'proveedorVista':
        $titulo_act = "Supplier view";
        $clase = "fa-solid fa-boxes-packing";
        break;

    case 'serviciosLista':
        $titulo_act = "Service List";
        $clase = "fa-solid fa-person-digging";
        break;

    case 'vehiculoVista':
        $titulo_act = "Vehicle view";
        $clase = "fa-solid fa-truck-pickup";
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Opción no válida para Vista: ' . $opcion]);
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