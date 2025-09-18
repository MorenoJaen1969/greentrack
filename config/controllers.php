<?php
require_once APP_R_PROY . 'app/controllers/serviciosController.php';
require_once APP_R_PROY . 'app/controllers/VerizonImportController.php';
//require_once APP_R_PROY . 'app/controllers/gpsController.php';
                    
//use app\controllers\gpsController;
use app\controllers\serviciosController;
use app\controllers\VerizonImportController;

//$gps = new gpsController();
$servicios = new serviciosController();
$verizon = new VerizonImportController();