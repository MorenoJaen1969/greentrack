<?php
	namespace app\models;

	class otras_fun{
		function cargar_idioma($_idioma_act) {
			$palabras = [];
			$ruta_idioma = RUTA_REAL."/app/views/idioma/";
			$rut_obj = "";
			if(isset($_SESSION['id_objeto'])){
				switch ($_SESSION['id_objeto']) {
					case 1:
						$rut_obj = "objetivo1/";
						break;
					case 2:
						$rut_obj = "objetivo2/";
						break;
					case 3:
						$rut_obj = "objetivo3/";
						break;
					case 4:
						$rut_obj = "objetivo4/";
						break;
					case 5:
						$rut_obj = "objetivo5/";
						break;
					default:
					$rut_obj = "";
				}
			}

			$ruta_idioma = $ruta_idioma . $rut_obj;

			$archivo = $ruta_idioma.$_idioma_act.".ini";

			if (!file_exists($archivo)) {
				$archivo = $ruta_idioma."es.ini";
				if (!file_exists(filename: $archivo)) {
					$palabras[] = "";
					error_log("El archivo de idioma no existe en index: " . $archivo);
				} else {
					$palabras = parse_ini_file($archivo, true);
					if ($palabras === false) {
						$palabras[] = "";
						error_log("Error al analizar el archivo INI: " . $archivo);
					}        
				}   
			} else {
				if(!empty($archivo)){
					$palabras = parse_ini_file($archivo, true);
					if ($palabras === false) {
						$palabras[] = "";
						error_log("Error al analizar el archivo INI: " . $archivo);
					}
				}
			}
			return $palabras;
		}

		function cargar_errores($_idioma_act){
			$errores = [];    
			$ruta_idioma = RUTA_REAL."/app/views/idioma/";    
			$archivo = $ruta_idioma."errores_".$_idioma_act.".ini";
			if (!file_exists($archivo)) {
				$archivo = $ruta_idioma."errores_es.ini";
				if (!file_exists($archivo)) {
					error_log("El archivo de control de errores en el idioma no existe en index: " . $archivo);
				} else {
					$errores = parse_ini_file($archivo, true);
					if ($errores === false) {
						error_log("Error al analizar el archivo INI: " . $archivo);
					}        
				}   
			} else {
				$errores = parse_ini_file($archivo, true);
				if ($errores === false) {
					error_log("Error al analizar el archivo INI: " . $archivo);
				}
			}
			return $errores;
		}

		function xtalk_parse_ini_file( $filename ){
			$arr = array();
			$file = fopen( $filename, "r" );

			$section = "";
			$ident = "";
			$value = "";

			while ( !feof( $file ) ){           
				$linha = trim(fgets( $file )); 
				if (!$linha){
					continue;
				}   
				// replace comments
				if (substr($linha, 0, 1) == ";"){
					continue;
				}   
				if (substr($linha, 0, 1) == "["){
					$section = substr($linha, 1, strlen($linha)-2);
					continue;
				}
				$pos = strpos($linha, "=");
				if ($pos){
					$ident = trim(substr($linha, 0, $pos - 1));
					$value = trim(substr($linha, $pos + 1, strlen($linha) - $pos + 1 ));
					$pos = strpos($value, ";");
					if ( $pos ){
						$value = trim(substr($value, 0, $pos - 1 )); // replace comments    
					}
				}
				$arr[$section][$ident] = $value;
			}
			fclose($file);
			return $arr;
		}

		public function setIdiomaCookie(){
			try {
				// Posibles idiomas
				// alamacenara todos nuestro idiomas
				$idiomas_act = ["EN", "IT", "ES", "DE", "en", "it", "es", "de"];
				//Almacenamos dicho idioma en una variable.
				$user_language = $this->getUserLanguage();
				// pasamos el language a mayuscula para no tener errores.
				//verificamos que tengamos dicho idioma en nuestro arreglo con in_array.
				if (in_array(strtoupper($user_language), $idiomas_act)) {
					// ahora redirigimos al idioma correcto
					$webLang = $user_language;
				} else{ // en caso contrario mandamos un idioma por defecto 
					$webLang = "en";
				}
				// Revisar si el idioma es correcto y se almacena en la cookie
				if ($webLang <> "") {
					// Comprueba si las cabeceras han sido enviadas
					if (!headers_sent()) {
						// Verifica si la cookie ya existe y si el valor es diferente
						if (!isset($_COOKIE['clang']) || $_COOKIE['clang'] !== $webLang) {
							// Configuración de expiración
							$expire = time() + 60 * 60 * 24 * 30 * 6; // 6 meses
					
							// Intenta establecer la cookie
							$cookieResult = setcookie("clang", $webLang, 
								[
									"expires" => $expire,
									"path" => "/",
									"domain" => DOMINIO,
									"secure" => true,
									"httponly" => true,
									"samesite" => "Strict"
								]
							);
					
							// Verifica si la cookie se estableció correctamente
							if ($cookieResult) {
								error_log("Cookie 'clang' establecida correctamente.");
							} else {
								error_log("Error al establecer la cookie.");
							}
						} else {
							error_log("La cookie 'clang' ya existe con el mismo valor.");
						}
					}else{
						error_log("Las cabeceras ya han sido enviadas, no se puede establecer la cookie.");
					}
				} else {
					// Si ya existe una cookie de idioma
					if (isset($_COOKIE["clang"])) {
						// leer idioma en la cookie
						$webLang=$_COOKIE["clang"];
						// No hay ninguna cookie de idioma definida    
						error_log( "Cookie configurada correctamente: ".$webLang);
					} else {
						// Detectar idioma del navegador y establecer cookie si no existe
						$webLang = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);
						if (!in_array($webLang, $idiomas_act)) {
							$webLang = "es";  // Idioma por defecto
						}
						$expire = time() + 60 * 60 * 24 * 30 * 6; // 6 meses
						if (setcookie("clang", $webLang, [
							"expires" => $expire,
							"path" => "/",
							"domain" => DOMINIO1,
							"secure" => true,
							"httponly" => true,
							"samesite" => "Strict"
						])) {
							error_log( "Cookie configurada correctamente. Ya existia");
						} else {
							error_log("Error al configurar la cookie. Existe una previa");
						}
					}
				}
			} catch (Exception $e) {
				// Registrar el error y evitar que el programa se detenga
				error_log("Error al crear la cookie de idioma: " . $e->getMessage());
			}		
		}

		//Creamos una función que detecte el idioma del navegador del cliente.
		function getUserLanguage() { 
			$use_idioma = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"],0,2);
			return $use_idioma; 
		}
		
		public function act_cookie($idioma) {
			$webLang = $idioma;
			$expire = time() + 60 * 60 * 24 * 30 * 6; // 6 meses
			// Ajusta el dominio para que sea válido para todos los subdominios
			$domain = ".".DOMINIO; // Nota el punto inicial
			setcookie("clang", $webLang, [
				"expires" => $expire,
				"path" => "/",
				"domain" => $domain,
				"secure" => false, // Asegúrate de usar false si no estás en HTTPS
				"httponly" => true,
				"samesite" => "Strict"
			]);
		}
	}
