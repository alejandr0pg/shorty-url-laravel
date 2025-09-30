# 🔧 SHRT - Guía de Troubleshooting

Guía rápida para resolver los problemas más comunes.

---

## 📑 Índice Rápido

1. [Container no inicia](#container-no-inicia)
2. [Base de datos no conecta](#base-de-datos-no-conecta)
3. [Redis no disponible](#redis-no-disponible)
4. [Errores 404 en rutas](#errores-404-en-rutas)
5. [ALB Targets unhealthy](#alb-targets-unhealthy)
6. [GitHub Actions falla](#github-actions-falla)
7. [Performance Issues](#performance-issues)
8. [Errores de permisos](#errores-de-permisos)

---

## 🚨 Container no inicia

### Síntomas
- Task se detiene inmediatamente después de iniciar
- Estado: `STOPPED` en ECS
- Health checks fallan

### Diagnóstico

```bash
# Ver logs del container
aws logs tail /ecs/shrt-backend-production --follow --since 30m

# Ver detalles del último task detenido
aws ecs list-tasks --cluster shrt-production-cluster --desired-status STOPPED --max-items 1
aws ecs describe-tasks --cluster shrt-production-cluster --tasks TASK_ARN
```

### Causas comunes

#### 1. Entrypoint script con errores

**Error:**
```
exec /entrypoint.sh: no such file or directory
```

**Solución:**
```bash
# Verificar shebang en entrypoint.sh
head -1 docker/entrypoint.sh
# Debe ser: #!/bin/sh (NO #!/bin/bash en Alpine)

# Verificar permisos
ls -la docker/entrypoint.sh
# Debe ser ejecutable: -rwxr-xr-x

# Corregir si es necesario
chmod +x docker/entrypoint.sh
```

#### 2. Extensión PHP faltante

**Error:**
```
Class 'Redis' not found
```

**Solución:**
En `Dockerfile`, cambiar instalación de Redis:
```dockerfile
# ❌ INCORRECTO
RUN apk add php83-redis

# ✅ CORRECTO
RUN pecl install redis && docker-php-ext-enable redis
```

#### 3. Variable de entorno faltante

**Error:**
```
APP_KEY is not set
```

**Solución:**
```bash
# Generar nueva key
php artisan key:generate --show

# Agregar a task definition
"environment": [
  {"name": "APP_KEY", "value": "base64:GENERATED_KEY"}
]
```

---

## 🗄️ Base de datos no conecta

### Síntomas
- Application usa SQLite en vez de MySQL
- Logs muestran: "Database connection failed, but continuing with SQLite fallback"
- Datos no persisten entre deploys

### Diagnóstico

```bash
# Verificar health check
curl http://YOUR_ALB/health | jq '.services.database'

# Debe mostrar:
# {
#   "status": "healthy",
#   "version": "8.0.42",
#   "connection": "mysql"
# }

# Ver logs de conexión
aws logs filter-log-events \
  --log-group-name /ecs/shrt-backend-production \
  --filter-pattern "Testing database" \
  --max-items 10
```

### Causas y Soluciones

#### 1. Security Group bloqueando conexión

```bash
# Verificar reglas del SG de RDS
aws ec2 describe-security-groups \
  --group-ids sg-RDS_SG_ID \
  --query 'SecurityGroups[0].IpPermissions[?FromPort==`3306`]'

# Debe permitir:
# - Security group de ECS tasks (sg-ECS_SG_ID)
# - O CIDR de la VPC (172.31.0.0/16)

# Agregar regla si falta
aws ec2 authorize-security-group-ingress \
  --group-id sg-RDS_SG_ID \
  --protocol tcp \
  --port 3306 \
  --source-group sg-ECS_SG_ID
```

#### 2. Contraseña incorrecta

```bash
# Cambiar contraseña de RDS
aws rds modify-db-instance \
  --db-instance-identifier shrt-production-db \
  --master-user-password "NUEVA_PASSWORD_SEGURA" \
  --apply-immediately

# Actualizar task definition
# Editar task-definition.json y cambiar DB_PASSWORD

# Re-registrar y desplegar
aws ecs register-task-definition --cli-input-json file://task-definition.json
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-production-service \
  --force-new-deployment
```

#### 3. Endpoint incorrecto

```bash
# Obtener endpoint correcto
aws rds describe-db-instances \
  --db-instance-identifier shrt-production-db \
  --query 'DBInstances[0].Endpoint.Address' \
  --output text

# Verificar en task definition
aws ecs describe-task-definition \
  --task-definition shrt-backend-production \
  --query 'taskDefinition.containerDefinitions[0].environment[?name==`DB_HOST`]'
```

#### 4. Base de datos no existe

```bash
# Conectarse al RDS y verificar
mysql -h RDS_ENDPOINT -u admin -p

# Dentro de MySQL:
SHOW DATABASES;
CREATE DATABASE IF NOT EXISTS shrt;
```

---

## 🔴 Redis no disponible

### Síntomas
- Cache no funciona
- Sessions no persisten
- Logs: "Redis connection failed, falling back to database cache"

### Diagnóstico

```bash
# Test de conexión Redis
redis-cli -h REDIS_ENDPOINT ping
# Debe responder: PONG

# Desde ECS task
aws ecs execute-command \
  --cluster shrt-production-cluster \
  --task TASK_ID \
  --container app \
  --command "redis-cli -h $REDIS_HOST ping"
```

### Soluciones

#### 1. Security Group

```bash
# Verificar que Redis SG permite conexiones desde ECS
aws ec2 authorize-security-group-ingress \
  --group-id sg-REDIS_SG_ID \
  --protocol tcp \
  --port 6379 \
  --source-group sg-ECS_SG_ID
```

#### 2. Endpoint incorrecto

```bash
# Obtener endpoint correcto
aws elasticache describe-cache-clusters \
  --cache-cluster-id shrt-production-redis \
  --show-cache-node-info \
  --query 'CacheClusters[0].CacheNodes[0].Endpoint.Address' \
  --output text
```

#### 3. Usar database cache temporalmente

En `.env` o task definition:
```env
CACHE_STORE=database
SESSION_DRIVER=database
```

---

## ❌ Errores 404 en rutas

### Síntomas
- `/docs` retorna 404
- API routes no funcionan
- Solo funciona homepage

### Causas y Soluciones

#### 1. Route cache mal generado

```bash
# En entrypoint.sh, NO cachear routes:
php artisan route:clear
# NO ejecutar: php artisan route:cache

# O regenerar correctamente:
php artisan route:cache
php artisan route:list  # Verificar que todas las rutas están
```

#### 2. Nginx no pasa requests a PHP

Verificar `docker/nginx/default.conf`:
```nginx
# Debe tener:
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass 127.0.0.1:9000;  # En container único
    # O: fastcgi_pass php:9000;    # En docker-compose
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

#### 3. Documentación no generada

```bash
# Regenerar documentación
php artisan scribe:generate --no-interaction

# Verificar archivos
ls -la storage/app/private/scribe/
ls -la resources/views/scribe/
```

---

## 🔴 ALB Targets unhealthy

### Síntomas
- Service muestra: `0 running, 1 desired`
- Targets en ALB marcados como "unhealthy"
- Health checks fallan

### Diagnóstico

```bash
# Ver health del target group
aws elbv2 describe-target-health \
  --target-group-arn TARGET_GROUP_ARN

# Ver eventos del servicio
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-production-service \
  --query 'services[0].events[0:10]'
```

### Soluciones

#### 1. Health check endpoint no responde

```bash
# Test manual
curl http://ALB_DNS/health

# Debe retornar JSON con status 200
```

#### 2. Health check muy estricto

```bash
# Ajustar thresholds
aws elbv2 modify-target-group \
  --target-group-arn TARGET_GROUP_ARN \
  --health-check-interval-seconds 30 \
  --health-check-timeout-seconds 10 \
  --healthy-threshold-count 2 \
  --unhealthy-threshold-count 5
```

#### 3. Container tarda en iniciar

En task definition, aumentar `startPeriod`:
```json
"healthCheck": {
  "command": ["CMD-SHELL", "curl -f http://localhost/health || exit 1"],
  "interval": 30,
  "timeout": 10,
  "retries": 3,
  "startPeriod": 120  # Aumentar de 60 a 120 segundos
}
```

#### 4. Múltiples targets viejos

```bash
# Listar todos los targets
aws elbv2 describe-target-health --target-group-arn TARGET_GROUP_ARN

# Deregister targets viejos manualmente
aws elbv2 deregister-targets \
  --target-group-arn TARGET_GROUP_ARN \
  --targets Id=IP_ADDRESS,Port=80
```

---

## ⚙️ GitHub Actions falla

### Diagnóstico

Ver logs en: Repository → Actions → Select failed workflow

### Errores comunes

#### 1. "Unable to assume role"

**Causa:** Configuración OIDC incorrecta

**Solución:**
```bash
# Verificar que el OIDC provider existe
aws iam list-open-id-connect-providers

# Verificar trust policy del rol
aws iam get-role --role-name GitHubActionsRole

# Debe tener el repositorio correcto en la condición:
# "StringLike": {
#   "token.actions.githubusercontent.com:sub": "repo:usuario/shrt:*"
# }
```

#### 2. "Image not found in ECR"

**Causa:** Build falló o imagen no se subió

**Solución:**
```bash
# Ver imágenes en ECR
aws ecr describe-images --repository-name shrt-backend

# Build y push manual
docker build -t shrt-backend:test .
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com
docker tag shrt-backend:test ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest
docker push ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest
```

#### 3. Secrets no configurados

**Solución:**

Repository → Settings → Secrets and variables → Actions

Verificar que estén configurados:
- `AWS_ACCOUNT_ID`
- `AWS_REGION`
- `ECR_REPOSITORY`
- `ECS_CLUSTER_PRODUCTION`
- `ECS_SERVICE_PRODUCTION`
- `TARGET_GROUP_ARN`
- `DB_PASSWORD`

---

## 🐌 Performance Issues

### Síntomas
- Respuestas lentas (>2 segundos)
- Timeouts
- CPU o memoria al 100%

### Diagnóstico

```bash
# Ver métricas de ECS task
aws ecs describe-tasks \
  --cluster shrt-production-cluster \
  --tasks TASK_ID \
  --query 'tasks[0].{CPU: cpu, Memory: memory}'

# Ver métricas detalladas en CloudWatch
aws cloudwatch get-metric-statistics \
  --namespace AWS/ECS \
  --metric-name CPUUtilization \
  --dimensions Name=ServiceName,Value=shrt-production-service \
  --start-time 2025-09-30T00:00:00Z \
  --end-time 2025-09-30T23:59:59Z \
  --period 300 \
  --statistics Average
```

### Soluciones

#### 1. Aumentar recursos

En task definition:
```json
"cpu": "1024",     # De 512 a 1024
"memory": "2048"   # De 1024 a 2048
```

#### 2. Habilitar opcache

En `docker/php/php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
```

#### 3. Optimizar queries

```bash
# Habilitar query log temporalmente
# En .env:
DB_LOG_QUERIES=true

# Analizar queries lentas
aws logs filter-log-events \
  --log-group-name /ecs/shrt-backend-production \
  --filter-pattern "slow query"
```

#### 4. Escalar horizontalmente

```bash
# Aumentar número de tasks
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-production-service \
  --desired-count 2
```

---

## 🔒 Errores de permisos

### Síntomas
- `Permission denied` en logs
- No puede escribir en storage/
- Cache no funciona

### Solución

#### En Dockerfile

```dockerfile
# Asegurar ownership correcto
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache
```

#### En entrypoint.sh

```bash
# Re-aplicar permisos en cada inicio
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage
```

#### Verificar desde el container

```bash
# Ejecutar comando en task
aws ecs execute-command \
  --cluster shrt-production-cluster \
  --task TASK_ID \
  --container app \
  --command "/bin/sh"

# Dentro del container:
ls -la storage/
whoami  # Debe ser: www-data
```

---

## 📞 Obtener Ayuda Adicional

### Logs completos

```bash
# Ver todos los logs recientes
aws logs tail /ecs/shrt-backend-production --follow --since 1h

# Filtrar por nivel de error
aws logs filter-log-events \
  --log-group-name /ecs/shrt-backend-production \
  --filter-pattern "ERROR" \
  --start-time $(($(date +%s) - 3600))000
```

### Estado del sistema

```bash
# Health check completo
curl http://ALB_DNS/health | jq '.'

# Ver todas las tasks
aws ecs list-tasks --cluster shrt-production-cluster --service-name shrt-production-service

# Describir servicio completo
aws ecs describe-services --cluster shrt-production-cluster --services shrt-production-service > service-state.json
```

### Crear Issue en GitHub

Al reportar un problema, incluir:
1. Descripción del error
2. Logs relevantes (`aws logs tail`)
3. Salida de `curl /health`
4. Task definition en uso
5. Pasos para reproducir

---

**Última actualización:** 30 de Septiembre, 2025
