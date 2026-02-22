<?php
// app/views/content/parametros-view.php
?>

<div class="container-fluid parametros-container">
    <!-- Header -->
    <div class="parametros-header">
        <h4>
            <i class="fas fa-cog mr-2"></i>General Parameters
        </h4>
        <div class="user-info">
            <span>
                <i class="fas fa-user mr-1"></i>
                <span id="userNombreDisplay"></span>
            </span>
            <button class="btn btn-sm btn-light" onclick="cerrarSesionParametros()">
                <i class="fas fa-sign-out-alt mr-1"></i>Close Session
            </button>
        </div>
    </div>

    <!-- Cuadrícula 2xN de celdas -->
    <div class="parametros-grid">
        <!-- Celda 1: Working Schedule -->
        <div class="parametro-card" data-target="schedule" onclick="mostrarParametro('schedule')">
            <i class="fas fa-clock"></i>
            <h5>Working Schedule</h5>
            <p>Working hours configuration</p>
        </div>

        <!-- Celda 2: Winter Period -->
        <div class="parametro-card" data-target="winter" onclick="mostrarParametro('winter')">
            <i class="fas fa-snowflake"></i>
            <h5>Winter Period</h5>
            <p>Winter services configuration</p>
        </div>

        <!-- Celda 3: GPS Tracking -->
        <div class="parametro-card" data-target="gps" onclick="mostrarParametro('gps')">
            <i class="fas fa-map-marker-alt"></i>
            <h5>GPS Tracking</h5>
            <p>GPS parameters and thresholds</p>
        </div>

        <!-- Celda 4: Map Settings -->
        <div class="parametro-card" data-target="map" onclick="mostrarParametro('map')">
            <i class="fas fa-globe"></i>
            <h5>Map Settings</h5>
            <p>Map base configuration</p>
        </div>
    </div>

    <!-- Contenido: Working Schedule -->
    <div class="parametro-content" id="schedule-content">
        <h5 class="mb-3"><i class="fas fa-clock mr-2"></i>Working Hours</h5>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="hora_inicio_jornada">Start Time</label>
                    <input type="time" class="form-control" id="hora_inicio_jornada" value="07:00" step="1">
                    <small class="form-text text-muted">Format: HH:mm:ss</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="hora_fin_jornada">End Time</label>
                    <input type="time" class="form-control" id="hora_fin_jornada" value="18:00" step="1">
                    <small class="form-text text-muted">Format: HH:mm:ss</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="hora_cierre_sesion">Session Close Time</label>
                    <input type="time" class="form-control" id="hora_cierre_sesion" value="18:30" step="1">
                    <small class="form-text text-muted">Format: HH:mm:ss</small>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="guardarParametro('schedule')">
            <i class="fas fa-save mr-2"></i>Save Schedule
        </button>
    </div>

    <!-- Contenido: Winter Period -->
    <div class="parametro-content" id="winter-content">
        <h5 class="mb-3"><i class="fas fa-snowflake mr-2"></i>Winter Configuration</h5>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="inicio_invierno">Winter Start Date</label>
                    <input type="text" class="form-control" id="inicio_invierno" placeholder="MM-DD" maxlength="5">
                    <small class="form-text text-muted">Format: MM-DD (e.g., 11-15)</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="fin_invierno">Winter End Date</label>
                    <input type="text" class="form-control" id="fin_invierno" placeholder="MM-DD" maxlength="5">
                    <small class="form-text text-muted">Format: MM-DD (e.g., 02-15)</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="reorganizar_invierno">Reorganize Winter Services</label>
                    <select class="form-control" id="reorganizar_invierno">
                        <option value="true">Enabled</option>
                        <option value="false">Disabled</option>
                    </select>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="guardarParametro('winter')">
            <i class="fas fa-save mr-2"></i>Save Winter Settings
        </button>
    </div>

    <!-- Contenido: GPS Tracking -->
    <div class="parametro-content" id="gps-content">
        <h5 class="mb-3"><i class="fas fa-map-marker-alt mr-2"></i>GPS Tracking Parameters</h5>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label for="umbral_metros">Distance Threshold (meters)</label>
                    <input type="number" class="form-control" id="umbral_metros" min="0">
                    <small class="form-text text-muted">Meters between client and vehicle stop</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="umbral_minutos">Time Threshold (minutes)</label>
                    <input type="number" class="form-control" id="umbral_minutos" min="0">
                    <small class="form-text text-muted">Minutes to calculate total stop</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="tiempo_minimo_parada">Minimum Stop Time (minutes)</label>
                    <input type="number" class="form-control" id="tiempo_minimo_parada" min="0">
                    <small class="form-text text-muted">Minimum minutes to consider a stop</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="umbral_course">Course Change Threshold</label>
                    <input type="number" class="form-control" id="umbral_course" min="0">
                    <small class="form-text text-muted">Threshold for course change detection</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label for="radio_geocerca">Geofence Radius (meters)</label>
                    <input type="number" class="form-control" id="radio_geocerca" min="0">
                    <small class="form-text text-muted">Radius in meters for geofence validation</small>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="guardarParametro('gps')">
            <i class="fas fa-save mr-2"></i>Save GPS Settings
        </button>
    </div>

    <!-- Contenido: Map Settings -->
    <div class="parametro-content" id="map-content">
        <h5 class="mb-3"><i class="fas fa-globe mr-2"></i>Map Configuration</h5>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="mapa_base">Map Base Type</label>
                    <select class="form-control" id="mapa_base">
                        <option value="OSM">OpenStreetMap (OSM)</option>
                        <option value="ESRI">ESRI Maps</option>
                        <option value="GMAP">Google Maps (GMAP)</option>
                    </select>
                    <small class="form-text text-muted">Select the base map provider</small>
                </div>
            </div>
        </div>
        <button class="btn btn-primary" onclick="guardarParametro('map')">
            <i class="fas fa-save mr-2"></i>Save Map Settings
        </button>
    </div>
</div>

<script>
    let configData = {};
    let parametroActivo = null;

    document.addEventListener('DOMContentLoaded', function() {
        verificarAccesoParametros();
        cargarConfiguracion();
    });

    function verificarAccesoParametros() {
        fetch('/app/ajax/datosgeneralesAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_DG: 'verificar_acceso' })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                //window.location.href = '/parametros-login';
            } else {
                document.getElementById('userNombreDisplay').textContent = data.nombre || data.username;
            }
        });
    }

    function cerrarSesionParametros() {
        fetch('/app/ajax/datosgeneralesAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_DG: 'cerrar_sesion' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/dashboard';
            }
        });
    }

    function mostrarParametro(seccion) {
        // Ocultar todos los contenidos
        document.querySelectorAll('.parametro-content').forEach(el => {
            el.classList.remove('active');
        });
        
        // Desactivar todas las celdas
        document.querySelectorAll('.parametro-card').forEach(el => {
            el.classList.remove('active');
        });
        
        // Mostrar el contenido seleccionado
        const content = document.getElementById(`${seccion}-content`);
        if (content) {
            content.classList.add('active');
        }
        
        // Activar la celda seleccionada
        const card = document.querySelector(`.parametro-card[data-target="${seccion}"]`);
        if (card) {
            card.classList.add('active');
        }
        
        parametroActivo = seccion;
    }

    function cargarConfiguracion() {
        fetch('/app/ajax/datosgeneralesAjax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ modulo_DG: 'obtener_configuracion' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                configData = data.config;
                cargarValoresFormulario();
            }
        });
    }

    function cargarValoresFormulario() {
        // Schedule
        if (configData.hora_inicio_jornada) {
            document.getElementById('hora_inicio_jornada').value = configData.hora_inicio_jornada.valor;
        }
        if (configData.hora_fin_jornada) {
            document.getElementById('hora_fin_jornada').value = configData.hora_fin_jornada.valor;
        }
        if (configData.hora_cierre_sesion) {
            document.getElementById('hora_cierre_sesion').value = configData.hora_cierre_sesion.valor;
        }

        // Winter
        if (configData.inicio_invierno) {
            document.getElementById('inicio_invierno').value = configData.inicio_invierno.valor;
        }
        if (configData.fin_invierno) {
            document.getElementById('fin_invierno').value = configData.fin_invierno.valor;
        }
        if (configData.reorganizar_invierno) {
            document.getElementById('reorganizar_invierno').value = configData.reorganizar_invierno.valor;
        }

        // GPS
        if (configData.umbral_metros) {
            document.getElementById('umbral_metros').value = configData.umbral_metros.valor;
        }
        if (configData.umbral_minutos) {
            document.getElementById('umbral_minutos').value = configData.umbral_minutos.valor;
        }
        if (configData.tiempo_minimo_parada) {
            document.getElementById('tiempo_minimo_parada').value = configData.tiempo_minimo_parada.valor;
        }
        if (configData.umbral_course) {
            document.getElementById('umbral_course').value = configData.umbral_course.valor;
        }
        if (configData.radio_geocerca) {
            document.getElementById('radio_geocerca').value = configData.radio_geocerca.valor;
        }

        // Map
        if (configData.mapa_base) {
            document.getElementById('mapa_base').value = configData.mapa_base.valor;
        }
    }

    function guardarParametro(seccion) {
        let parametros = [];

        switch (seccion) {
            case 'schedule':
                parametros = [
                    { clave: 'hora_inicio_jornada', valor: document.getElementById('hora_inicio_jornada').value },
                    { clave: 'hora_fin_jornada', valor: document.getElementById('hora_fin_jornada').value },
                    { clave: 'hora_cierre_sesion', valor: document.getElementById('hora_cierre_sesion').value }
                ];
                break;

            case 'winter':
                const inicio = document.getElementById('inicio_invierno').value;
                const fin = document.getElementById('fin_invierno').value;

                if (!/^([0-1]?[0-9])\-([0-3]?[0-9])$/.test(inicio)) {
                    alert('Invalid Winter Start Date format. Use MM-DD');
                    return;
                }
                if (!/^([0-1]?[0-9])\-([0-3]?[0-9])$/.test(fin)) {
                    alert('Invalid Winter End Date format. Use MM-DD');
                    return;
                }

                parametros = [
                    { clave: 'inicio_invierno', valor: inicio },
                    { clave: 'fin_invierno', valor: fin },
                    { clave: 'reorganizar_invierno', valor: document.getElementById('reorganizar_invierno').value }
                ];
                break;

            case 'gps':
                parametros = [
                    { clave: 'umbral_metros', valor: document.getElementById('umbral_metros').value },
                    { clave: 'umbral_minutos', valor: document.getElementById('umbral_minutos').value },
                    { clave: 'tiempo_minimo_parada', valor: document.getElementById('tiempo_minimo_parada').value },
                    { clave: 'umbral_course', valor: document.getElementById('umbral_course').value },
                    { clave: 'radio_geocerca', valor: document.getElementById('radio_geocerca').value }
                ];
                break;

            case 'map':
                parametros = [
                    { clave: 'mapa_base', valor: document.getElementById('mapa_base').value }
                ];
                break;
        }

        // Guardar cada parámetro
        let promises = parametros.map(param => {
            return fetch('/app/ajax/datosgeneralesAjax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    modulo_DG: 'guardar_configuracion',
                    clave: param.clave,
                    valor: param.valor
                })
            });
        });

        Promise.all(promises)
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(results => {
                const exitos = results.filter(r => r.success).length;
                const errores = results.filter(r => !r.success);

                if (errores.length > 0) {
                    alert('Errors saving some parameters:\n' + errores.map(e => e.message).join('\n'));
                }

                if (exitos > 0) {
                    alert(`${exitos} parameter(s) saved successfully`);
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Connection error. Please try again.');
            });
    }
</script>