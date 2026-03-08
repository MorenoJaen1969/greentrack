<!-- ====================== BOTÓN DE MENÚ (FLOTANTE - SIEMPRE VISIBLE) ====================== -->
<div id="btn-menu-toggle" class='menu_flot' title="Mostrar menú">
    ☰
</div>

<!-- ====================== PANEL DE MENÚ LATERAL (OCULTO POR DEFECTO) ====================== -->
<div id="menu-lateral" class="menu_lat">
    <!-- Encabezado del menú -->
    <div style="display: flex; align-items: center; margin-bottom: 25px;">
        <div style="font-size: 1.5em; margin-right: 10px;">🌿</div>
        <h3 style="margin: 0; font-size: 1.2em; font-weight: 600;">Sergios's Landscape</h3>
    </div>

    <!-- Botón para cerrar -->
    <div style="text-align: right; margin-bottom: 20px;">
        <span id="btn-menu-close" class="btn_cerrar" title="Cerrar menú">×</span>
    </div>

    <!-- Opciones del menú -->
    <nav>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <li style="margin-bottom: 12px;">
                <a href="#" id="menu-donde-esta" class="menu-item">
                    📍 Where are?
                </a>
            </li>
            <li class="li_1 has-submenu" aria-haspopup="true" aria-expanded="false">
                <a href="#" class="menu-item submenu-toggle" tabindex="0">
                    📄 <span class="menu-text">Service Contract</span>
                </a>

                <ul class="ul_1 submenu" role="menu" aria-label="Sub Processes">
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/clientes/"; ?>" class="menu-item">
                            👥 Customers
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/contratos/"; ?>" class="menu-item">
                            📄 Contract
                        </a>
                    </li>
                </ul>
            </li>
            <li style="margin-bottom: 12px;">
                <a href="<?php echo RUTA_APP."/vehiculos/"; ?>" class="menu-item">
                    🚚 Vehicle's
                </a>
            </li>
            <li style="margin-bottom: 12px;">
                <a href="<?php echo RUTA_APP."/proveedores/"; ?>" class="menu-item">
                    🛒 Suppliers
                </a>
            </li>
            <li style="margin-bottom: 12px;">
                <a href="<?php echo RUTA_APP."/direcciones/"; ?>" class="menu-item">
                    🗺️ Address
                </a>
            </li>
            <li class="li_1 has-submenu" aria-haspopup="true" aria-expanded="false">
                <a href="#" class="menu-item submenu-toggle" tabindex="0">
                    🌍 <span class="menu-text">Geography</span>
                </a>

                <ul class="ul_1 submenu" role="menu" aria-label="Sub Processes">
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/paises/"; ?>" class="menu-item">
                            🌐 Country
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/estados/"; ?>" class="menu-item">
                            🏘️ State
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/condados/"; ?>" class="menu-item">
                            🏛️ County
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/ciudades/"; ?>" class="menu-item">
                            🏢 City
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/zips/"; ?>" class="menu-item">
                            📬 Post Zone
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/rutas_mapa/"; ?>" class="menu-item">
                            🔲 Grid Zone
                        </a>
                    </li>
                </ul>
            </li>
            <li style="margin-bottom: 12px;">
                <a href="<?php echo RUTA_APP."/crew/"; ?>" class="menu-item">
                    🛻 Field Staff
                </a>
            </li>
            <li class="li_1 has-submenu" aria-haspopup="true" aria-expanded="false">
                <a href="#" class="menu-item submenu-toggle" tabindex="0">
                    ⚙️ <span class="menu-text">Sub Processes</span>
                </a>

                <ul class="ul_1 submenu" role="menu" aria-label="Sub Processes">
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/status_all/"; ?>" class="menu-item">
                            📊 Status
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/dias_no_actividad/"; ?>" class="menu-item">
                            ⛔ Non-Working Days or Holidays
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/address_clas/"; ?>" class="menu-item">
                            🏗️ Address classification
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/address_type/"; ?>" class="menu-item">
                            🏷️ Address type
                        </a>
                    </li>
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/route_day/"; ?>" class="menu-item">
                            📍 Route assignment on specific days
                        </a>
                    </li>

                </ul>
            </li>
            <li class="li_1 has-submenu" aria-haspopup="true" aria-expanded="false">
                <a href="#" class="menu-item submenu-toggle" tabindex="0">
                    🔐 <span class="menu-text">General Maintenance</span>
                </a>
                <ul class="ul_1 submenu" role="menu" aria-label="Sub Processes">
                    <li class="li_1 has-submenu" aria-haspopup="true" aria-expanded="false">
                        <a href="#" class="menu-item submenu-toggle" tabindex="0">
                            🕶️ <span class="menu-text">Critical Systems</span>
                        </a>
                        <ul class="ul_1 submenu" role="menu" aria-label="Sub Processes">
                            <!-- Datos Generales --> 
                            <li class="li_1" role="menuitem">
                                <a href="<?php echo RUTA_APP."/parametros/"; ?>" class="menu-item">
                                    🌐 General Parameters
                                </a>
                            </li>
                            <li class="li_1" role="menuitem">
                                <a href="<?php echo RUTA_APP."/usuarios/"; ?>" class="menu-item">
                                    🛂 User maintenance
                                </a>
                            </li>

                        </ul>
                    </li>
                    <!-- En la sección de "Mantenimiento" o "Administración" --> 
                    <li class="li_1" role="menuitem">
                        <a href="<?php echo RUTA_APP."/salas/"; ?>" class="menu-item">
                            💬 Chat Rooms
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
</div>

<!-- ====================== SUPERPOSICIÓN OSCURA (solo cuando menú está abierto) ====================== -->
<div id="menu-overlay" class="contraste"></div>

<!-- Modal: Selección y visualización de vehículo -->
<div id="modal-donde-esta" class="modal-overlay_gps"
    style="display:none; align-items:flex-start; justify-content:center;">
    <div class="modal-contenedor" style="margin-top:20vh; width:60vw; min-width:320px;">
        <button id="close_modal_donde_esta" class="modal-cerrar1">✕</button>
        <div id="contenido-modal-donde-esta">
            <h3>Select a vehicle</h3>
            <select id="select-vehiculo-donde-esta" style="width:100%;margin-bottom:16px;"></select>
            <div id="info-vehiculo-donde-esta"></div>
        </div>
    </div>
</div>
