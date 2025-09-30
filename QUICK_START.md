# ⚡ SHRT - Quick Start Guide

Comandos esenciales para empezar rápido.

---

## 🚀 Setup Local en 5 minutos

```bash
# 1. Clonar repositorio
git clone https://github.com/tu-usuario/shrt.git
cd shrt

# 2. Instalar dependencias
composer install

# 3. Configurar environment
cp .env.example .env
php artisan key:generate

# 4. Setup base de datos (SQLite)
touch database/database.sqlite
php artisan migrate

# 5. Generar documentación API
php artisan scribe:generate

# 6. Iniciar servidor
php artisan serve
# → http://localhost:8000
```

**Verificar:**
```bash
curl http://localhost:8000/health
open http://localhost:8000/docs
```

---

## 🐳 Setup con Docker

```bash
# Iniciar todos los servicios
docker-compose up -d

# Ver logs
docker-compose logs -f

# Detener
docker-compose down
```

---

## ☁️ Deploy a AWS (Primera vez)

### 1. Pre-requisitos

```bash
# Instalar AWS CLI
brew install awscli  # macOS
# o
sudo apt install awscli  # Ubuntu

# Configurar credenciales
aws configure
```

### 2. Crear infraestructura

```bash
# Variables
export AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
export AWS_REGION=us-east-1

# ECR Repository
aws ecr create-repository --repository-name shrt-backend --region $AWS_REGION

# ECS Cluster
aws ecs create-cluster \
  --cluster-name shrt-production-cluster \
  --capacity-providers FARGATE

# RDS Database (simplificado - ver DEPLOYMENT.md para completo)
aws rds create-db-instance \
  --db-instance-identifier shrt-production-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --master-username admin \
  --master-user-password "CAMBIAR_PASSWORD" \
  --allocated-storage 20

# ElastiCache Redis (simplificado)
aws elasticache create-cache-cluster \
  --cache-cluster-id shrt-production-redis \
  --engine redis \
  --cache-node-type cache.t3.micro \
  --num-cache-nodes 1
```

### 3. Build y Deploy

```bash
# Login a ECR
aws ecr get-login-password --region $AWS_REGION | \
  docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com

# Build
docker build -t shrt-backend:latest .

# Tag y Push
docker tag shrt-backend:latest $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/shrt-backend:latest
docker push $AWS_ACCOUNT_ID.dkr.ecr.$AWS_REGION.amazonaws.com/shrt-backend:latest

# Crear/Actualizar servicio (requiere task definition - ver DEPLOYMENT.md)
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-production-service \
  --force-new-deployment
```

---

## 🔄 Deploy Updates (después de primera vez)

### Opción A: Automático con GitHub Actions

```bash
# Solo hacer push a main
git add .
git commit -m "Update feature"
git push origin main
# GitHub Actions hace el resto
```

### Opción B: Manual

```bash
# Build nueva imagen
docker build -t shrt-backend:$(git rev-parse --short HEAD) .

# Login, tag y push
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com

docker tag shrt-backend:$(git rev-parse --short HEAD) \
  $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest

docker push $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest

# Forzar nuevo deployment
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-production-service \
  --force-new-deployment

# Monitorear
aws ecs wait services-stable \
  --cluster shrt-production-cluster \
  --services shrt-production-service
```

### Opción C: Con Makefile

```bash
# Ver comandos disponibles
make help

# Deployment completo
make deploy

# O paso por paso
make build-prod    # Build imagen
make push-prod     # Push a ECR
make deploy-prod   # Deploy a ECS
```

---

## 📊 Comandos Útiles

### Local Development

```bash
# Iniciar servidor
php artisan serve

# Limpiar caches
php artisan optimize:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Migraciones
php artisan migrate
php artisan migrate:fresh --seed  # Reset completo

# Tests
php artisan test
./vendor/bin/pest

# Linting
./vendor/bin/pint  # Fix code style

# Regenerar documentación API
php artisan scribe:generate
```

### AWS Monitoring

```bash
# Ver logs en tiempo real
aws logs tail /ecs/shrt-backend-production --follow

# Health check
curl http://YOUR_ALB_DNS/health | jq '.'

# Ver tasks corriendo
aws ecs list-tasks \
  --cluster shrt-production-cluster \
  --service-name shrt-production-service

# Estado del servicio
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-production-service \
  --query 'services[0].{Status: status, Running: runningCount, Desired: desiredCount}'

# Health de targets en ALB
aws elbv2 describe-target-health \
  --target-group-arn YOUR_TARGET_GROUP_ARN

# Ver eventos recientes
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-production-service \
  --query 'services[0].events[0:5]'
```

### Docker

```bash
# Build local
docker build -t shrt-backend:dev .

# Run local
docker run -p 8000:80 \
  -e APP_ENV=local \
  -e DB_CONNECTION=sqlite \
  shrt-backend:dev

# Ver logs
docker logs -f CONTAINER_ID

# Ejecutar comandos dentro del container
docker exec -it CONTAINER_ID /bin/sh
```

### Database

```bash
# Conectar a RDS
mysql -h YOUR_RDS_ENDPOINT -u admin -p

# Backup local
php artisan db:backup  # Si está configurado

# Console interactiva
php artisan tinker
```

---

## 🔍 Verificación Post-Deploy

```bash
# 1. Health check
curl http://YOUR_ALB_DNS/health

# 2. API Documentation
open http://YOUR_ALB_DNS/docs

# 3. Test API endpoint
curl -X POST http://YOUR_ALB_DNS/api/urls \
  -H "Content-Type: application/json" \
  -H "X-Device-Id: test-123" \
  -d '{"original_url": "https://google.com"}'

# 4. Ver logs
aws logs tail /ecs/shrt-backend-production --since 5m
```

---

## 🆘 Troubleshooting Rápido

### Container no inicia

```bash
# Ver logs de error
aws logs tail /ecs/shrt-backend-production --follow --since 10m

# Ver último task que falló
aws ecs describe-tasks \
  --cluster shrt-production-cluster \
  --tasks $(aws ecs list-tasks --cluster shrt-production-cluster --desired-status STOPPED --max-items 1 --query 'taskArns[0]' --output text)
```

### Base de datos no conecta

```bash
# Verificar security groups
aws ec2 describe-security-groups --group-ids sg-YOUR_RDS_SG

# Test de conexión
mysql -h YOUR_RDS_ENDPOINT -u admin -p

# Ver configuración en task
aws ecs describe-task-definition \
  --task-definition shrt-backend-production \
  --query 'taskDefinition.containerDefinitions[0].environment[?name==`DB_HOST`]'
```

### 404 en rutas

```bash
# Limpiar route cache
# Conectarse al container:
aws ecs execute-command \
  --cluster shrt-production-cluster \
  --task TASK_ID \
  --container app \
  --command "php artisan route:clear"

# O forzar nuevo deployment
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-production-service \
  --force-new-deployment
```

### Targets unhealthy en ALB

```bash
# Ver health de targets
aws elbv2 describe-target-health --target-group-arn YOUR_TG_ARN

# Ajustar health check
aws elbv2 modify-target-group \
  --target-group-arn YOUR_TG_ARN \
  --health-check-interval-seconds 30 \
  --healthy-threshold-count 2 \
  --unhealthy-threshold-count 5
```

**Para más detalles:** Ver [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

---

## 📚 Documentación Completa

- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Guía completa de deployment
- **[TROUBLESHOOTING.md](TROUBLESHOOTING.md)** - Solución de problemas
- **[.env.example](.env.example)** - Variables de entorno
- **[Makefile](Makefile)** - Comandos automatizados

---

## 🔗 Links Útiles

### Desarrollo
- API Docs local: http://localhost:8000/docs
- Health Check local: http://localhost:8000/health

### Producción
- API Docs: http://YOUR_ALB_DNS/docs
- Health Check: http://YOUR_ALB_DNS/health
- CloudWatch Logs: https://console.aws.amazon.com/cloudwatch/home?region=us-east-1#logsV2:log-groups/log-group/$252Fecs$252Fshrt-backend-production
- ECS Console: https://console.aws.amazon.com/ecs/v2/clusters/shrt-production-cluster/services

### GitHub
- Actions: https://github.com/tu-usuario/shrt/actions
- Issues: https://github.com/tu-usuario/shrt/issues

---

**¿Problemas?** → Ver [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

**¿Primera vez?** → Ver [DEPLOYMENT.md](DEPLOYMENT.md)

**Última actualización:** 30 de Septiembre, 2025
