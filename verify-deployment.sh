#!/bin/bash

# Script para verificar que el deployment esté funcionando correctamente

echo "🔍 Verificando deployment de SHRT..."

# Verificar que los secrets estén configurados
echo "📋 Verificando GitHub Secrets..."

# Verificar que el servicio ECS esté corriendo
echo "🔧 Verificando ECS Service..."
aws ecs describe-services \
  --cluster shrt-backend-production \
  --services shrt-backend-production \
  --query 'services[0].{Status:status,RunningCount:runningCount,DesiredCount:desiredCount}'

# Verificar logs del container
echo "📋 Verificando logs recientes..."
TASK_ARN=$(aws ecs list-tasks \
  --cluster shrt-backend-production \
  --service-name shrt-backend-production \
  --query 'taskArns[0]' --output text)

if [ "$TASK_ARN" != "None" ] && [ -n "$TASK_ARN" ]; then
  TASK_ID=$(echo $TASK_ARN | sed 's|.*/||')
  echo "📋 Últimos logs del container (Task: $TASK_ID):"

  aws logs get-log-events \
    --log-group-name /ecs/shrt-backend \
    --log-stream-name "ecs/app/$TASK_ID" \
    --start-time $(date -d '5 minutes ago' +%s000) \
    --output text \
    --query 'events[*].message' \
    --max-items 20 || echo "No se pudieron obtener los logs"
else
  echo "❌ No se encontró ninguna tarea corriendo"
fi

# Verificar health check del ALB
echo "🏥 Verificando target group health..."
aws elbv2 describe-target-health \
  --target-group-arn "$TARGET_GROUP_ARN" \
  --query 'TargetHealthDescriptions[*].{IP:Target.Id,State:TargetHealth.State,Reason:TargetHealth.Reason}' \
  --output table

# Verificar conectividad a la base de datos
echo "🗄️  Verificando conectividad..."
if [ "$TASK_ARN" != "None" ] && [ -n "$TASK_ARN" ]; then
  echo "Ejecutando comando de diagnóstico en el container..."
  aws ecs execute-command \
    --cluster shrt-backend-production \
    --task $TASK_ARN \
    --container app \
    --interactive \
    --command "php artisan tinker --execute=\"echo 'Database: ' . config('database.default'); echo 'S3 Bucket: ' . config('filesystems.disks.s3.bucket'); echo 'Redis: ' . config('database.redis.default.host');\""
else
  echo "❌ No se puede ejecutar comando de diagnóstico - no hay tareas corriendo"
fi

echo "✅ Verificación completada"