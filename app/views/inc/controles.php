<?php
    namespace app\views\inc;

    if(isset($url)){
        $programa = trim($url[0]);
    } else {
        $programa = "login";
    }

    $raices=[
        "address_clas",
        "address_type",
        "chat",
        "clientes",
        "contratos",
        "crew",
        "dashboard",
        "dias_no_actividad",
        "direcciones",
        "proveedores",
        "route_day",
        "rutas_mapa",
        "salas",
        "servicios",
        "vehiculos"
    ];

    $encontrado = false;
    $pos_ini = 0;
    $raiz = "";
    foreach ($raices as $valor) {
        $posicionCoincidencia = strpos($programa, $valor);
        if ($posicionCoincidencia !== false) {
            $encontrado = true;
            $raiz = $raices[$pos_ini];
            break;
        }	
        $pos_ini = $pos_ini + 1;
    }

    if ($programa=="login"){
    } else {
        if ($programa=="dashboard") {
            // Variables de dashboard
            $txt_titulo = "Dashboard";                
        } else {
            if($raiz=="address_clas"){
                $txt_titulo = "Address classification";                
            }elseif($raiz=="address_type"){
                $txt_titulo = "Address type";                
            }elseif($raiz=="clientes"){
                $txt_titulo = "Customer's";                
            }elseif($raiz=="contratos"){
                $txt_titulo = "Contract's";   
            }elseif($raiz=="dias_no_actividad"){
                $txt_titulo = "Non-Working Days or Holidays";                
            }elseif($raiz=="direcciones"){
                $txt_titulo = "Address";                
            }elseif($raiz=="proveedores"){
                $txt_titulo = "Suppliers";       
            }elseif($raiz=="rutas_mapa"){
                $txt_titulo = "Maps rute";       
            }elseif($raiz=="vehiculos"){
                $txt_titulo = "Vehicles";        
            }elseif($raiz=="route_day"){
                $txt_titulo = "Route assignment on specific days";        
            }elseif($raiz=="salas"){
                $txt_titulo = "Rooms";
            }else{
                $txt_titulo = $raiz;
            }                
        }
    }