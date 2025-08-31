<?php
	namespace app\models;

    trait IdiomaTrait {
        public function initializeIdioma() {
            if(isset($_COOKIE['clang'])){
                $idioma = $_COOKIE['clang'];			        
            }else{
                if (isset($_SESSION['lang'])) {
                    $idioma = $_SESSION['lang'];
                } else {
                    $idioma = "en";
                }
            }

            $otras_fun = new \app\models\otras_fun();
            return $otras_fun->cargar_idioma($idioma);
        }
    }