<?php
//app/views/content/clientes/address-form.php
//Modal con contenedor de mapa ABSOLUTAMENTE FIJO
?>
<div id="plat-modal-direccion" style="
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100vw;
    height:100vh;
    background:rgba(0,0,0,0.85);
    z-index:10000;
    overflow-y:auto;
">
    <div style="
        position:relative;
        height:90vh;
        width:90vw;
        max-width:95%;
        margin:20px auto;
        background:#fff;
        border-radius:8px;
        box-shadow:0 5px 25px rgba(0,0,0,0.5);
    ">
        <!--Header -->
        <div style="
            padding:15px 20px;
            border-bottom:3px solid #6d1a72;
            background:#f8f9fa;
            border-radius:8px 8px 0 0;
            display:flex;
            justify-content:space-between;
            align-items:center;
        ">
            <h3 style="margin:0;color:#6d1a72;font-size:1.3rem;">
                <i class="fas fa-map-marker-alt"></i> Add New Address
            </h3>
            <button type="button" onclick="platCerrarModalDireccion()" style="
                background:#dc3545;
                color:white;
                border:none;
                width:35px;
                height:35px;
                border-radius:4px;
                cursor:pointer;
                font-size:1.2rem;
            ">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <!--Body -->
        <div style="padding:20px;">
            <!--Grid principal -->
            <div style="
                display:grid;
                grid-template-columns:50% 50%;
                gap:20px;
                width:80vw;
                margin:0 auto;
            ">
                <!--Columna Izquierda:Formulario -->
                <div style="
                    width:100%;
                    background:#ffffff;
                    padding:20px;
                    border-radius:6px;
                    border:2px solid #dee2e6;
                    box-sizing:border-box;
                ">
                    <div class="grupo_modal_address">
                        <div class="grupo_modal_address01">
                            <h4 style="margin:0 0 15px 0;color:#6d1a72;font-size:1rem;border-bottom:2px solid #6d1a72;padding-bottom:8px;">
                                <i class="fas fa-globe-americas"></i> Geographic Location
                            </h4>
                            
                            <!--País -->
                            <div style="margin-bottom:12px;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">Country *</label>
                                <select id="plat-modal-pais" style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;box-sizing:border-box;">
                                    <option value="">Select a country...</option>
                                </select>
                            </div>
                            
                            <!--Estado -->
                            <div style="margin-bottom:12px;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">State/Province *</label>
                                <select id="plat-modal-estado" disabled style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;background:#e9ecef;box-sizing:border-box;">
                                    <option value="">Select state...</option>
                                </select>
                            </div>
                            
                            <!--Municipio -->
                            <div style="margin-bottom:12px;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">Municipality/County *</label>
                                <select id="plat-modal-municipio" disabled style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;background:#e9ecef;box-sizing:border-box;">
                                    <option value="">Select county...</option>
                                </select>
                            </div>
                            
                            <!--Ciudad -->
                            <div style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">City/Town *</label>
                                <select id="plat-modal-ciudad" disabled style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;background:#e9ecef;box-sizing:border-box;">
                                    <option value="">Select city...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grupo_modal_address02">
                            <h4 style="margin:0 0 15px 0;color:#6d1a72;font-size:1rem;border-bottom:2px solid #6d1a72;padding-bottom:8px;">
                                <i class="fas fa-home"></i> Property & Service Details
                            </h4>
                            
                            <!--Land Use -->
                            <div style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">Land Use *</label>
                                <select id="plat-modal-land-use" disabled style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;background:#e9ecef;box-sizing:border-box;">
                                    <option value="">Select a Land Use...</option>
                                </select>
                            </div>
                            
                            <!--Street Suffix -->
                            <div style="margin-bottom:15px;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">Street Suffix *</label>
                                <select id="plat-modal-suffix" disabled style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;background:#e9ecef;box-sizing:border-box;">
                                    <option value="">Select a Suffix...</option>
                                </select>
                            </div>
                            
                            <!--Calle -->
                            <div style="margin-bottom:12px;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">Street/Avenue Name *</label>
                                <input type="text" id="plat-modal-calle" class="form-control" placeholder="Ex: Harmony View" disabled required style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;box-sizing:border-box;">
                            </div>
                            
                            <!--Número (condicional) -->
                            <div id="wrapper-numero" style="margin-bottom:12px;visibility:hidden;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">House # *</label>
                                <input type="text" id="plat-modal-numero" class="form-control" placeholder="Ex: 3305" disabled required style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;box-sizing:border-box;">
                            </div>

                            <!-- campo visible para que el usuario vea/edite el ZIP -->
                            <input type="hidden" id="plat-modal-zip" name="zip">

                            <!-- Opcional: campo visible para que el usuario vea/edite el ZIP -->
                            <div class="form-group mt-2">
                                <label class="small text-muted">ZIP Code:</label>
                                <input type="text" id="plat-modal-zip-display" class="form-control form-control-sm"
                                    placeholder="ZIP" maxlength="5" pattern="[0-9]{5}"
                                    oninput="document.getElementById('plat-modal-zip').value = this.value">
                            </div>                            

                            <!--Nickname -->
                            <div style="margin-bottom:12px;">
                                <label style="display:block;margin-bottom:4px;font-weight:600;color:#333;font-size:0.9rem;">Site Nickname (Ex: Police Dept / North Median)</label>
                                <input type="text" id="plat-modal-nickname" class="input" placeholder="Optional identifier" disabled style="width:100%;padding:8px;border:1px solid #ced4da;border-radius:4px;font-size:0.9rem;box-sizing:border-box;">
                            </div>
                        </div>
                    </div>
                    
                    <!--Botones de acción -->
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-top:15px;">
                        <button type="button" id="plat-btn-verificar" onclick="platVerificarDireccionModal()" disabled style="
                            padding:10px 15px;
                            background:#6d1a72;
                            color:white;
                            border:none;
                            border-radius:4px;
                            cursor:pointer;
                            font-size:0.85rem;
                            font-weight:600;
                            opacity:0.5;
                        ">
                            <i class="fas fa-search-location"></i> Verify Address
                        </button>
                        
                        <button type="button" id="plat-btn-ruta" onclick="platVerRutaModal()" disabled style="
                            padding:10px 15px;
                            background:#17a2b8;
                            color:white;
                            border:none;
                            border-radius:4px;
                            cursor:pointer;
                            font-size:0.85rem;
                            font-weight:600;
                            opacity:0.5;
                        ">
                            <i class="fas fa-route"></i> View Route
                        </button>
                    </div>
                    
                    <!--Coordenadas ocultas -->
                    <input type="hidden" id="plat-modal-lat">
                    <input type="hidden" id="plat-modal-lng">
                    
                    <!--Display de coordenadas -->
                    <div style="background:#e7f3ff;border:1px solid #b8daff;border-radius:4px;padding:10px;margin-top:10px;">
                        <div style="color:#004085;font-size:0.8rem;margin-bottom:4px;">
                            <i class="fas fa-crosshairs"></i>
                            <strong>Coordinates:</strong>
                        </div>
                        <div style="color:#004085;font-family:monospace;font-size:0.8rem;">
                            Lat: <span id="plat-modal-display-lat">--</span> | 
                            Lng: <span id="plat-modal-display-lng">--</span>
                        </div>
                        <div style="color:#004085;font-family:monospace;font-size:0.8rem;">
                            <span id="plat-modal-display-address">--</span> | 
                        </div>
                    </div>
                </div>
                
                <!--Columna Derecha:Mapa -->
                <div style="
                    width:100%;
                    height:500px;
                    position:relative;
                ">
                    <!--Info header -->
                    <div style="
                        position:absolute;
                        top:0;
                        left:0;
                        right:0;
                        height:30px;
                        background:#fff3cd;
                        border:2px solid #ffc107;
                        border-bottom:none;
                        border-radius:6px 6px 0 0;
                        padding:5px 12px;
                        box-sizing:border-box;
                        z-index:10;
                    ">
                        <small style="color:#856404;font-size:0.8rem;">
                            <i class="fas fa-info-circle"></i> Click on the map to place the marker
                        </small>
                    </div>
                    
                    <!--Contenedor del mapa -->
                    <div id="plat-modal-mapa-wrapper" style="
                        position:absolute;
                        top:30px;
                        left:0;
                        width:100%;
                        height:100%;
                        border:2px solid #ffc107;
                        border-top:none;
                        border-radius:0 0 6px 6px;
                        background:#e9ecef;
                        box-sizing:border-box;
                        overflow:hidden;
                    ">
                        <!--El mapa se creará aquí con dimensiones exactas -->
                    </div>
                </div>
            </div>
        </div>
        
        <!--Footer -->
        <div style="
            padding:15px 20px;
            border-top:2px solid #dee2e6;
            background:#f8f9fa;
            border-radius:0 0 8px 8px;
            display:flex;
            justify-content:flex-end;
            gap:10px;
        ">
            <button type="button" onclick="platCerrarModalDireccion()" style="
                padding:10px 20px;
                background:#6c757d;
                color:white;
                border:none;
                border-radius:4px;
                cursor:pointer;
                font-size:0.9rem;
            ">
                <i class="fas fa-times"></i> Cancel
            </button>
            
            <button type="button" id="plat-btn-guardar" onclick="platGuardarDireccionModal()" disabled style="
                padding:10px 20px;
                background:#6d1a72;
                color:white;
                border:none;
                border-radius:4px;
                cursor:pointer;
                font-size:0.9rem;
                font-weight:600;
                opacity:0.5;
            ">
                <i class="fas fa-save"></i> Save Address
            </button>
        </div>
    </div>
</div>