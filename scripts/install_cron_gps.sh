#!/bin/bash
# Script para instalar el CRON GPS

PROJECT_PATH="/var/www/greentrack"
PHP_PATH=$(which php)
SCRIPT_PATH="$PROJECT_PATH/scripts/cron_archivar_gps.php"
LOG_PATH="$PROJECT_PATH/app/logs/cron_gps_daily.log"

echo "=== INSTALADOR CRON GPS ==="
echo ""
echo "Ruta del proyecto: $PROJECT_PATH"
echo "PHP: $PHP_PATH"
echo "Script: $SCRIPT_PATH"
echo "Log: $LOG_PATH"
echo ""

# Verificar que existan los archivos
if [ ! -f "$SCRIPT_PATH" ]; then
    echo "✗ Error: El script $SCRIPT_PATH no existe"
    exit 1
fi

if [ ! -d "$PROJECT_PATH/app/logs" ]; then
    echo "✗ Error: El directorio de logs no existe"
    exit 1
fi

# Mostrar el comando CRON
echo "Comando CRON a instalar:"
echo "0 2 * * * cd $PROJECT_PATH && $PHP_PATH $SCRIPT_PATH >> $LOG_PATH 2>&1"
echo ""

# Preguntar si instalar
read -p "¿Instalar CRON para el usuario www-data? (s/n): " respuesta

if [ "$respuesta" = "s" ] || [ "$respuesta" = "S" ]; then
    # Crear archivo temporal con el CRON
    TEMP_CRON=$(mktemp)
    crontab -u www-data -l 2>/dev/null > "$TEMP_CRON"
    
    # Verificar si ya existe la línea
    if grep -q "cron_archivar_gps.php" "$TEMP_CRON"; then
        echo "✗ El CRON ya está instalado"
        rm "$TEMP_CRON"
        exit 0
    fi
    
    # Agregar nueva línea
    echo "0 2 * * * cd $PROJECT_PATH && $PHP_PATH $SCRIPT_PATH >> $LOG_PATH 2>&1" >> "$TEMP_CRON"
    
    # Instalar CRON
    crontab -u www-data "$TEMP_CRON"
    rm "$TEMP_CRON"
    
    echo "✓ CRON instalado exitosamente"
    echo ""
    echo "Verificar con: sudo crontab -u www-data -l"
else
    echo "Instalación cancelada"
fi