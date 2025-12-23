<?php
	
	namespace app\models;

	class viewsModel{

		/*---------- Modelo obtener vista ----------*/
		protected function obtenerVistasModelo($vista_req){
			$cont_orig = $vista_req;

			$valores = explode("/", $vista_req);
			$vista = $valores[0];
			$contenido=[];

			$listaBlanca=[
				"address_clas",
				"address_clasNew",
				"address_type",
				"address_typeNew",
				"chat",
				"clientes",
				"clientesNew",
				"clientesVista",
				"contratos",
				"contratosVista",
				"dashboard",
				"dias_no_actividad",
				"dias_no_actividadNew",
				"dias_no_actividadVista",
				"direcciones",
				"direccionesNew",
				"direccionesVista",
				"proveedores",
				"rutas_mapa",
				"servicios",
				"serviciosLista",
				"status_all",
				"status_allNew",
				"vehiculos"
			];

            $raices = [
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
				"rutas_mapa",
				"servicios",
				"status_all",
				"vehiculos"
            ];

			if(in_array($vista, $listaBlanca)){
				$contenido=$vista;
				if($vista=="dashboard" || $vista=="index"){
                    if(is_file("./app/views/content/".$vista."-view.php")){
                        $contenido="./app/views/content/".$vista."-view.php";
                    }else{
                        $contenido="404";
                    }		
				}else{
					$encontrado = false;
					$pos_ini = 0;
					foreach ($raices as $valor) {
						$posicionCoincidencia = strpos($vista, $valor);
						if ($posicionCoincidencia !== false) {
							$encontrado = true;
							$raiz = $raices[$pos_ini];
							break;
						}	
						$pos_ini = $pos_ini + 1;
					}

					
					if ($encontrado) {
						$ruta_req = "./app/views/content/".$raiz."/".$cont_orig."-view.php"; 
						if(is_file($ruta_req)){
							$contenido=$ruta_req;
						}else{				
							error_log("No se encontro el archivo: " . $cont_orig . " Ruta: " . $ruta_req);
							$contenido="404";
						}
					} else {
						error_log("No se encontro la página:  ". $vista);
						$contenido="404";
					}
				}
			}
			return $contenido;
		}

	}