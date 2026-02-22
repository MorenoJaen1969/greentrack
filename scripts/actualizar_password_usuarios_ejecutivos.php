<?php
/**
 * Script para actualizar el password_hash en la tabla usuarios_ejecutivos
 * Contraseña base: Noloseno#2017
 */

// Configuración de la base de datos
define('DB_SERVER', "localhost"); 
define('DB_HOST', 'localhost');
define('DB_NAME', "greentrack_live");
define('DB_USER', "mmoreno");
define('DB_PASS', "Noloseno#2017");
define('DB_CHARSET', 'utf8mb4');
	
// Contraseña a hashear
$contrasena_base = "Noloseno#2017";

try {
    // Conexión a la base de datos
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    echo "========================================\n";
    echo "ACTUALIZACIÓN DE PASSWORD HASH\n";
    echo "========================================\n\n";
    
    // Generar el hash de la contraseña
    $password_hash = password_hash($contrasena_base, PASSWORD_DEFAULT);
    
    echo "✓ Contraseña base: " . $contrasena_base . "\n";
    echo "✓ Hash generado: " . $password_hash . "\n\n";
    
    // Verificar estructura de la tabla
    echo "Verificando estructura de la tabla 'usuarios_ejecutivos'...\n";
    $stmt_check = $pdo->query("SHOW COLUMNS FROM usuarios_ejecutivos LIKE 'password_hash'");
    
    if ($stmt_check->rowCount() === 0) {
        echo "✗ ERROR: La tabla 'usuarios_ejecutivos' no tiene el campo 'password_hash'\n";
        exit(1);
    }
    
    echo "✓ Campo 'password_hash' encontrado en la tabla\n\n";
    
    // Contar registros actuales
    $stmt_count = $pdo->query("SELECT COUNT(*) as total FROM usuarios_ejecutivos");
    $total_registros = $stmt_count->fetch()['total'];
    
    echo "Estadísticas actuales:\n";
    echo "  - Total de registros: " . $total_registros . "\n";
    
    // Contar registros con password_hash NULL o vacío
    $stmt_null = $pdo->query("SELECT COUNT(*) as total FROM usuarios_ejecutivos WHERE password_hash IS NULL OR password_hash = ''");
    $total_sin_hash = $stmt_null->fetch()['total'];
    
    echo "  - Registros sin password_hash: " . $total_sin_hash . "\n\n";
    
    // Preguntar al usuario qué desea hacer
    echo "Opciones de actualización:\n";
    echo "  1. Actualizar TODOS los registros\n";
    echo "  2. Actualizar SOLO registros con password_hash NULL o vacío\n";
    echo "  3. Actualizar un registro específico por ID\n";
    echo "  4. Salir sin realizar cambios\n\n";
    
    echo "Selecciona una opción (1-4): ";
    
    // Leer entrada del usuario
    if (php_sapi_name() === 'cli') {
        $opcion = trim(fgets(STDIN));
    } else {
        $opcion = isset($_GET['opcion']) ? $_GET['opcion'] : '4';
    }
    
    echo "\n";
    
    $registros_actualizados = 0;
    
    switch ($opcion) {
        case '1':
            // Actualizar TODOS los registros
            echo "ADVERTENCIA: Se actualizarán TODOS los registros (" . $total_registros . ")\n";
            echo "¿Estás seguro? (s/n): ";
            
            if (php_sapi_name() === 'cli') {
                $confirmacion = trim(strtolower(fgets(STDIN)));
            } else {
                $confirmacion = isset($_GET['confirm']) && $_GET['confirm'] === 's' ? 's' : 'n';
            }
            
            if ($confirmacion === 's') {
                $stmt_update = $pdo->prepare("UPDATE usuarios_ejecutivos SET password_hash = ?");
                $stmt_update->execute([$password_hash]);
                $registros_actualizados = $stmt_update->rowCount();
                
                echo "✓ Actualización completada exitosamente\n";
                echo "✓ Registros actualizados: " . $registros_actualizados . "\n\n";
            } else {
                echo "✗ Operación cancelada por el usuario\n\n";
                exit(0);
            }
            break;
            
        case '2':
            // Actualizar SOLO registros con password_hash NULL o vacío
            echo "Actualizando registros sin password_hash (" . $total_sin_hash . " registros)...\n";
            
            $stmt_update = $pdo->prepare("UPDATE usuarios_ejecutivos SET password_hash = ? WHERE password_hash IS NULL OR password_hash = ''");
            $stmt_update->execute([$password_hash]);
            $registros_actualizados = $stmt_update->rowCount();
            
            echo "✓ Actualización completada exitosamente\n";
            echo "✓ Registros actualizados: " . $registros_actualizados . "\n\n";
            break;
            
        case '3':
            // Actualizar un registro específico por ID
            echo "Ingresa el ID del usuario a actualizar: ";
            
            if (php_sapi_name() === 'cli') {
                $id_usuario = trim(fgets(STDIN));
            } else {
                $id_usuario = isset($_GET['id']) ? $_GET['id'] : null;
            }
            
            if ($id_usuario && is_numeric($id_usuario)) {
                // Verificar si existe el registro
                $stmt_check_id = $pdo->prepare("SELECT * FROM usuarios_ejecutivos WHERE id = ?");
                $stmt_check_id->execute([$id_usuario]);
                $usuario = $stmt_check_id->fetch();
                
                if ($usuario) {
                    echo "Usuario encontrado:\n";
                    echo "  - ID: " . $usuario['id'] . "\n";
                    echo "  - Nombre: " . ($usuario['nombre'] ?? 'N/A') . "\n";
                    echo "  - Email: " . ($usuario['email'] ?? 'N/A') . "\n\n";
                    
                    echo "¿Actualizar este registro? (s/n): ";
                    
                    if (php_sapi_name() === 'cli') {
                        $confirmacion = trim(strtolower(fgets(STDIN)));
                    } else {
                        $confirmacion = isset($_GET['confirm']) && $_GET['confirm'] === 's' ? 's' : 'n';
                    }
                    
                    if ($confirmacion === 's') {
                        $stmt_update = $pdo->prepare("UPDATE usuarios_ejecutivos SET password_hash = ? WHERE id = ?");
                        $stmt_update->execute([$password_hash, $id_usuario]);
                        $registros_actualizados = $stmt_update->rowCount();
                        
                        echo "✓ Registro actualizado exitosamente\n";
                        echo "✓ ID actualizado: " . $id_usuario . "\n\n";
                    } else {
                        echo "✗ Operación cancelada por el usuario\n\n";
                    }
                } else {
                    echo "✗ ERROR: No se encontró ningún registro con ID = " . $id_usuario . "\n\n";
                }
            } else {
                echo "✗ ERROR: ID inválido\n\n";
            }
            break;
            
        case '4':
            echo "Operación cancelada. No se realizaron cambios.\n\n";
            exit(0);
            break;
            
        default:
            echo "✗ Opción inválida. Operación cancelada.\n\n";
            exit(0);
    }
    
    // Verificación final
    if ($registros_actualizados > 0) {
        echo "========================================\n";
        echo "VERIFICACIÓN DE ACTUALIZACIÓN\n";
        echo "========================================\n\n";
        
        // Verificar algunos registros actualizados
        $stmt_verify = $pdo->query("SELECT id, username, email, password_hash FROM usuarios_ejecutivos LIMIT 5");
        $registros = $stmt_verify->fetchAll();
        
        echo "Muestra de registros actualizados (primeros 5):\n\n";
        
        foreach ($registros as $registro) {
            $valido = password_verify($contrasena_base, $registro['password_hash']) ? '✓ VÁLIDO' : '✗ INVÁLIDO';
            
            echo "ID: " . str_pad($registro['id'], 4) . " | ";
            echo "Username: " . str_pad($registro['username'] ?? 'N/A', 15) . " | ";
            echo "Email: " . str_pad($registro['email'] ?? 'N/A', 25) . " | ";
            echo $valido . "\n";
        }
        
        echo "\n";
        
        // Verificar cuántos registros tienen password_hash ahora
        $stmt_count_hash = $pdo->query("SELECT COUNT(*) as total FROM usuarios_ejecutivos WHERE password_hash IS NOT NULL AND password_hash != ''");
        $total_con_hash = $stmt_count_hash->fetch()['total'];
        
        echo "Resumen final:\n";
        echo "  - Registros con password_hash: " . $total_con_hash . "\n";
        echo "  - Total de registros: " . $total_registros . "\n";
        echo "  - Porcentaje completado: " . round(($total_con_hash / $total_registros) * 100, 2) . "%\n\n";
        
        echo "✓ Proceso completado exitosamente\n";
    }
    
} catch (PDOException $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "========================================\n";
echo "FIN DEL PROCESO\n";
echo "========================================\n";
?>