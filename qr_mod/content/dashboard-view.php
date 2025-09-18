<?php
// ================================================= //
//          DASHBOARD PERSONALIZADO - QR MOD
//          - Solo para administradores
//          - Acceso tras login
//          - Interfaz para asignar arrendatarios
// ================================================= //

use app\models\mainModel;
use app\controllers\contratosController;

// Cargar idioma
$idioma = $_COOKIE['clang'] ?? 'en';
$palabras = parse_ini_file("../../app/views/idioma/{$idioma}.ini", true);

// Instanciar controlador
$contrato = new contratosController();
?>
<!DOCTYPE html>
<html lang="<?= $idioma ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $palabras['public']['asignar_inquilino'] ?? 'Assign Tenant' ?></title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="../../app/views/img/logo.png">

    <!-- CSS Local -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container">
        <h1 class="title"><?= $palabras['public']['asignar_inquilino'] ?></h1>

        <!-- Formulario de asignación -->
        <form id="form-asignar" class="form-card">
            <div class="form-group">
                <label for="nombre-arrendatario"><?= $palabras['public']['nombre_arrendatario'] ?></label>
                <input type="text" id="nombre-arrendatario" placeholder="<?= $palabras['public']['ej_cafe_del_sol'] ?>" required>
            </div>

            <div class="form-group">
                <label for="nombre-local"><?= $palabras['public']['nombre_local'] ?></label>
                <input type="text" id="nombre-local" placeholder="<?= $palabras['public']['ej_local_101'] ?>" required>
            </div>

            <button type="submit" id="btn-asignar">
                <i class="fas fa-user-plus"></i> <?= $palabras['public']['asignar'] ?>
            </button>
        </form>

        <!-- Historial reciente -->
        <div class="history-card">
            <h2><?= $palabras['public']['historial_reciente'] ?></h2>
            <div id="historial-lista">
                <p class="text-center"><?= $palabras['public']['cargando'] ?>...</p>
            </div>
        </div>
    </div>

    <script>
        const palabras = <?= json_encode($palabras, JSON_UNESCAPED_UNICODE) ?>;
        const APP_URL = '<?= APP_URL ?>';

        // Cargar historial reciente
        async function cargarHistorial() {
            try {
                const res = await fetch('../app/ajax/contratosAjax.php', {
                    method: 'POST',
                    body: JSON.stringify({
                        modulo_contratos: 'listar_ultimas_asignaciones',
                        limite: 3
                    })
                });
                const data = await res.json();

                const lista = document.getElementById('historial-lista');
                if (data.status === 'success' && data.data.length > 0) {
                    lista.innerHTML = data.data.map(item => `
                        <div class="history-item" data-id="${item.id_contrato}" data-codigo="${item.codigo_temporal}">
                            <strong>${item.nombre_arrendatario}</strong> → ${item.nombre_local}
                            <small>${new Date(item.fecha_qr_mod).toLocaleString()}</small>
                            <button class="btn-corregir" onclick="corregirRegistro('${item.codigo_temporal}')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    `).join('');
                } else {
                    lista.innerHTML = `<p class="text-center">${palabras.public.no_hay_registros}</p>`;
                }
            } catch (err) {
                console.error(err);
                document.getElementById('historial-lista').innerHTML = `<p class="text-error">${palabras.public.error_carga}</p>`;
            }
        }

        // Enviar asignación
        document.getElementById('form-asignar').addEventListener('submit', async (e) => {
            e.preventDefault();
            const nombre = document.getElementById('nombre-arrendatario').value.trim();
            const local = document.getElementById('nombre-local').value.trim();

            if (!nombre || !local) {
                Swal.fire(palabras.public.campos_obligatorios, '', 'warning');
                return;
            }

            const param = {
                modulo_contratos: 'asignar_desde_qr',
                nombre_arrendatario: nombre,
                nombre_local: local
            };

            try {
                const res = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(param)
                });
                const data = await res.json();

                if (data.status === 'success') {
                    Swal.fire(data.message, '', 'success');
                    document.getElementById('form-asignar').reset();
                    cargarHistorial(); // Actualizar historial
                } else {
                    Swal.fire(data.message, '', 'error');
                }
            } catch (err) {
                Swal.fire(palabras.public.error_interno, '', 'error');
            }
        });

        // Corregir registro
        async function corregirRegistro(codigo_temporal) {
            const { value: nuevoNombre } = await Swal.fire({
                title: palabras.public.corregir_registro,
                input: 'text',
                inputLabel: palabras.public.nuevo_nombre,
                inputValue: '',
                showCancelButton: true,
                confirmButtonText: palabras.public.guardar,
                cancelButtonText: palabras.public.cancelar
            });

            if (!nuevoNombre) return;

            const param = {
                modulo_contratos: 'corregir_desde_qr',
                codigo_temporal: codigo_temporal,
                nuevo_nombre: nuevoNombre
            };

            try {
                const res = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(param)
                });
                const data = await res.json();

                if (data.status === 'success') {
                    Swal.fire(data.message, '', 'success');
                    cargarHistorial();
                } else {
                    Swal.fire(data.message, '', 'error');
                }
            } catch (err) {
                Swal.fire(palabras.public.error_interno, '', 'error');
            }
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', cargarHistorial);
    </script>
</body>
</html>