#!/bin/bash
# Script para configurar el CRON

PHP_PATH=$(which php)
PROJECT_PATH="/ruta/a/tu/proyecto"
LOG_PATH="$PROJECT_PATH/logs"

echo "Configurando CRON para archivar GPS..."
echo ""
echo "CRON diario a las 2:00 AM:"
echo "0 2 * * * cd $PROJECT_PATH && $PHP_PATH scripts/cron_archivar_gps.php >> $LOG_PATH/cron_daily.log 2>&1"
echo ""
echo "Para agregar al crontab:"
echo "crontab -e"
echo "Y agregar la línea anterior"
echo ""
echo "Para verificar:"
echo "crontab -l"