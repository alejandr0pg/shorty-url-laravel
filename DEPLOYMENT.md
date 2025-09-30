# 🚀 SHRT - Guía Completa de Deployment

Esta guía cubre **todos** los pasos necesarios para levantar el servidor SHRT tanto en **local** como en **AWS**.

---

## 📋 Tabla de Contenidos

1. [Requisitos Previos](#requisitos-previos)
2. [Setup Local (Desarrollo)](#setup-local-desarrollo)
3. [Setup AWS (Producción)](#setup-aws-producción)
4. [Despliegue con GitHub Actions](#despliegue-con-github-actions)
5. [Variables de Entorno](#variables-de-entorno)
6. [Troubleshooting](#troubleshooting)

---

## 🔧 Requisitos Previos

### Para Desarrollo Local

- **PHP** >= 8.2
- **Composer** >= 2.0
- **Node.js** >= 18.x
- **Docker** >= 20.10 (opcional, para servicios)
- **MySQL** 8.0 o **SQLite** (local)
- **Redis** >= 7.0 (opcional, para cache)
- **Git**

### Para Deployment en AWS

- **Cuenta de AWS** con acceso programático
- **AWS CLI** instalado y configurado
- **Docker** instalado localmente
- **GitHub** account (para CI/CD)
- **Permisos IAM** necesarios:
  - ECR (push/pull de imágenes)
  - ECS (gestión de clusters y servicios)
  - RDS (gestión de base de datos)
  - ElastiCache (gestión de Redis)
  - VPC (gestión de networking)
  - IAM (gestión de roles)

---

## 💻 Setup Local (Desarrollo)

### 1. Clonar el Repositorio

```bash
git clone https://github.com/tu-usuario/shrt.git
cd shrt
```

### 2. Instalar Dependencias

#### Backend (Laravel)

```bash
# Instalar dependencias de PHP
composer install

# Copiar archivo de configuración
cp .env.example .env

# Generar key de aplicación
php artisan key:generate
```

#### Frontend (si aplica)

```bash
cd frontend
npm install
cd ..
```

### 3. Configurar Base de Datos

#### Opción A: SQLite (más simple para desarrollo)

```bash
# Crear archivo de base de datos
touch database/database.sqlite

# Configurar en .env
DB_CONNECTION=sqlite
DB_DATABASE=/ruta/completa/a/database/database.sqlite
```

#### Opción B: MySQL (recomendado para staging)

```bash
# Iniciar MySQL con Docker
docker run -d \
  --name shrt-mysql \
  -e MYSQL_ROOT_PASSWORD=secret \
  -e MYSQL_DATABASE=shrt \
  -e MYSQL_USER=shrt \
  -e MYSQL_PASSWORD=secret \
  -p 3306:3306 \
  mysql:8.0

# Configurar en .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shrt
DB_USERNAME=shrt
DB_PASSWORD=secret
```

### 4. Configurar Redis (Opcional)

```bash
# Iniciar Redis con Docker
docker run -d \
  --name shrt-redis \
  -p 6379:6379 \
  redis:7-alpine

# Configurar en .env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis
```

### 5. Ejecutar Migraciones

```bash
# Ejecutar migraciones
php artisan migrate

# (Opcional) Ejecutar seeders para datos de prueba
php artisan db:seed
```

### 6. Generar Documentación de API

```bash
# Generar documentación con Scribe
php artisan scribe:generate

# Verificar que se crearon los archivos
ls -la storage/app/private/scribe/
ls -la resources/views/scribe/
```

### 7. Iniciar Servidor de Desarrollo

#### Usando PHP Built-in Server

```bash
php artisan serve
# Servidor disponible en http://localhost:8000
```

#### Usando Docker Compose (Recomendado)

```bash
docker-compose up -d

# Ver logs
docker-compose logs -f

# Detener servicios
docker-compose down
```

#### Usando Makefile

```bash
# Ver comandos disponibles
make help

# Iniciar desarrollo
make dev
```

### 8. Verificar Instalación

```bash
# Verificar health check
curl http://localhost:8000/health

# Acceder a documentación API
open http://localhost:8000/docs
```

---

## ☁️ Setup AWS (Producción)

### 1. Configurar AWS CLI

```bash
# Instalar AWS CLI (si no está instalado)
# macOS
brew install awscli

# Ubuntu/Debian
sudo apt install awscli

# Configurar credenciales
aws configure
# AWS Access Key ID: TU_ACCESS_KEY
# AWS Secret Access Key: TU_SECRET_KEY
# Default region: us-east-1
# Default output format: json
```

### 2. Crear Infraestructura AWS

#### A. Crear VPC y Subnets (si no existen)

```bash
# Crear VPC
aws ec2 create-vpc \
  --cidr-block 172.31.0.0/16 \
  --tag-specifications 'ResourceType=vpc,Tags=[{Key=Name,Value=shrt-vpc}]'

# Crear Subnets (mínimo 2 en diferentes AZs)
aws ec2 create-subnet \
  --vpc-id vpc-XXXXX \
  --cidr-block 172.31.1.0/24 \
  --availability-zone us-east-1a

aws ec2 create-subnet \
  --vpc-id vpc-XXXXX \
  --cidr-block 172.31.2.0/24 \
  --availability-zone us-east-1b
```

#### B. Crear Security Groups

```bash
# Security Group para ECS Tasks
aws ec2 create-security-group \
  --group-name shrt-ecs-sg \
  --description "Security group for SHRT ECS tasks" \
  --vpc-id vpc-XXXXX

# Guardar el Security Group ID
ECS_SG_ID=sg-XXXXX

# Permitir tráfico HTTP desde el ALB
aws ec2 authorize-security-group-ingress \
  --group-id $ECS_SG_ID \
  --protocol tcp \
  --port 80 \
  --source-group $ALB_SG_ID

# Security Group para RDS
aws ec2 create-security-group \
  --group-name shrt-rds-sg \
  --description "Security group for SHRT RDS" \
  --vpc-id vpc-XXXXX

RDS_SG_ID=sg-YYYYY

# Permitir MySQL desde ECS
aws ec2 authorize-security-group-ingress \
  --group-id $RDS_SG_ID \
  --protocol tcp \
  --port 3306 \
  --source-group $ECS_SG_ID

# Permitir desde CIDR de VPC
aws ec2 authorize-security-group-ingress \
  --group-id $RDS_SG_ID \
  --protocol tcp \
  --port 3306 \
  --cidr 172.31.0.0/16
```

#### C. Crear RDS MySQL

```bash
# Crear DB Subnet Group
aws rds create-db-subnet-group \
  --db-subnet-group-name shrt-db-subnet \
  --db-subnet-group-description "Subnet group for Shrt database" \
  --subnet-ids subnet-XXXXX subnet-YYYYY

# Crear RDS Instance
aws rds create-db-instance \
  --db-instance-identifier shrt-production-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0.42 \
  --master-username admin \
  --master-user-password "TU_PASSWORD_SEGURO" \
  --allocated-storage 20 \
  --storage-type gp2 \
  --vpc-security-group-ids $RDS_SG_ID \
  --db-subnet-group-name shrt-db-subnet \
  --backup-retention-period 7 \
  --publicly-accessible \
  --storage-encrypted \
  --db-name shrt

# Esperar a que esté disponible (5-10 minutos)
aws rds wait db-instance-available --db-instance-identifier shrt-production-db

# Obtener endpoint
aws rds describe-db-instances \
  --db-instance-identifier shrt-production-db \
  --query 'DBInstances[0].Endpoint.Address' \
  --output text
```

#### D. Crear ElastiCache Redis

```bash
# Crear Subnet Group para Redis
aws elasticache create-cache-subnet-group \
  --cache-subnet-group-name shrt-redis-subnet \
  --cache-subnet-group-description "Subnet group for Shrt Redis" \
  --subnet-ids subnet-XXXXX subnet-YYYYY

# Crear Redis Cluster
aws elasticache create-cache-cluster \
  --cache-cluster-id shrt-production-redis \
  --engine redis \
  --cache-node-type cache.t3.micro \
  --num-cache-nodes 1 \
  --engine-version 7.0 \
  --cache-subnet-group-name shrt-redis-subnet \
  --security-group-ids $ECS_SG_ID

# Obtener endpoint
aws elasticache describe-cache-clusters \
  --cache-cluster-id shrt-production-redis \
  --show-cache-node-info \
  --query 'CacheClusters[0].CacheNodes[0].Endpoint.Address' \
  --output text
```

#### E. Crear ECR Repository

```bash
# Crear repositorio
aws ecr create-repository \
  --repository-name shrt-backend \
  --region us-east-1

# Obtener URI del repositorio
aws ecr describe-repositories \
  --repository-names shrt-backend \
  --query 'repositories[0].repositoryUri' \
  --output text
```

#### F. Crear Application Load Balancer

```bash
# Crear ALB
aws elbv2 create-load-balancer \
  --name shrt-production-alb \
  --subnets subnet-XXXXX subnet-YYYYY \
  --security-groups $ALB_SG_ID \
  --scheme internet-facing \
  --type application \
  --ip-address-type ipv4

# Guardar ARN del ALB
ALB_ARN=$(aws elbv2 describe-load-balancers \
  --names shrt-production-alb \
  --query 'LoadBalancers[0].LoadBalancerArn' \
  --output text)

# Crear Target Group
aws elbv2 create-target-group \
  --name shrt-production-tg \
  --protocol HTTP \
  --port 80 \
  --vpc-id vpc-XXXXX \
  --target-type ip \
  --health-check-path /health \
  --health-check-interval-seconds 30 \
  --health-check-timeout-seconds 5 \
  --healthy-threshold-count 2 \
  --unhealthy-threshold-count 3

# Guardar ARN del Target Group
TG_ARN=$(aws elbv2 describe-target-groups \
  --names shrt-production-tg \
  --query 'TargetGroups[0].TargetGroupArn' \
  --output text)

# Crear Listener
aws elbv2 create-listener \
  --load-balancer-arn $ALB_ARN \
  --protocol HTTP \
  --port 80 \
  --default-actions Type=forward,TargetGroupArn=$TG_ARN
```

#### G. Crear ECS Cluster

```bash
# Crear cluster para producción
aws ecs create-cluster \
  --cluster-name shrt-production-cluster \
  --capacity-providers FARGATE \
  --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1
```

#### H. Crear IAM Roles

```bash
# Crear rol de ejecución para ECS
cat > ecs-task-execution-role.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": "ecs-tasks.amazonaws.com"
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
EOF

aws iam create-role \
  --role-name ecsTaskExecutionRole \
  --assume-role-policy-document file://ecs-task-execution-role.json

aws iam attach-role-policy \
  --role-name ecsTaskExecutionRole \
  --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy
```

#### I. Crear Task Definition

```bash
# Obtener todos los valores necesarios
ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
DB_ENDPOINT=$(aws rds describe-db-instances --db-instance-identifier shrt-production-db --query 'DBInstances[0].Endpoint.Address' --output text)
REDIS_ENDPOINT=$(aws elasticache describe-cache-clusters --cache-cluster-id shrt-production-redis --show-cache-node-info --query 'CacheClusters[0].CacheNodes[0].Endpoint.Address' --output text)
ALB_DNS=$(aws elbv2 describe-load-balancers --names shrt-production-alb --query 'LoadBalancers[0].DNSName' --output text)

# Crear archivo de task definition
cat > task-definition-production.json <<EOF
{
  "family": "shrt-backend-production",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "512",
  "memory": "1024",
  "executionRoleArn": "arn:aws:iam::${ACCOUNT_ID}:role/ecsTaskExecutionRole",
  "containerDefinitions": [
    {
      "name": "app",
      "image": "${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest",
      "essential": true,
      "portMappings": [
        {
          "containerPort": 80,
          "protocol": "tcp"
        }
      ],
      "environment": [
        {"name": "APP_NAME", "value": "SHRT"},
        {"name": "APP_ENV", "value": "production"},
        {"name": "APP_DEBUG", "value": "false"},
        {"name": "APP_URL", "value": "http://${ALB_DNS}"},
        {"name": "LOG_CHANNEL", "value": "stderr"},
        {"name": "LOG_LEVEL", "value": "error"},
        {"name": "DB_CONNECTION", "value": "mysql"},
        {"name": "DB_HOST", "value": "${DB_ENDPOINT}"},
        {"name": "DB_PORT", "value": "3306"},
        {"name": "DB_DATABASE", "value": "shrt"},
        {"name": "DB_USERNAME", "value": "admin"},
        {"name": "DB_PASSWORD", "value": "TU_PASSWORD_SEGURO"},
        {"name": "REDIS_HOST", "value": "${REDIS_ENDPOINT}"},
        {"name": "REDIS_PORT", "value": "6379"},
        {"name": "CACHE_STORE", "value": "redis"},
        {"name": "SESSION_DRIVER", "value": "redis"},
        {"name": "QUEUE_CONNECTION", "value": "database"}
      ],
      "logConfiguration": {
        "logDriver": "awslogs",
        "options": {
          "awslogs-group": "/ecs/shrt-backend-production",
          "awslogs-region": "us-east-1",
          "awslogs-stream-prefix": "ecs"
        }
      }
    }
  ]
}
EOF

# Crear log group
aws logs create-log-group --log-group-name /ecs/shrt-backend-production

# Registrar task definition
aws ecs register-task-definition --cli-input-json file://task-definition-production.json
```

#### J. Crear ECS Service

```bash
# Crear servicio
aws ecs create-service \
  --cluster shrt-production-cluster \
  --service-name shrt-production-service \
  --task-definition shrt-backend-production \
  --desired-count 1 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[subnet-XXXXX,subnet-YYYYY],securityGroups=[$ECS_SG_ID],assignPublicIp=ENABLED}" \
  --load-balancers "targetGroupArn=$TG_ARN,containerName=app,containerPort=80"
```

### 3. Build y Push de la Imagen Docker

```bash
# Login a ECR
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com

# Build de la imagen
docker build -t shrt-backend:latest .

# Tag de la imagen
docker tag shrt-backend:latest ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest
docker tag shrt-backend:latest ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:$(git rev-parse --short HEAD)

# Push a ECR
docker push ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest
docker push ${ACCOUNT_ID}.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:$(git rev-parse --short HEAD)
```

### 4. Verificar Deployment

```bash
# Ver status del servicio
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-production-service

# Ver tasks corriendo
aws ecs list-tasks \
  --cluster shrt-production-cluster \
  --service-name shrt-production-service

# Ver logs
aws logs tail /ecs/shrt-backend-production --follow

# Test del endpoint
curl http://${ALB_DNS}/health
curl http://${ALB_DNS}/docs
```

---

## 🔄 Despliegue con GitHub Actions

### 1. Configurar GitHub Secrets

Ve a tu repositorio en GitHub → Settings → Secrets and variables → Actions

Configura los siguientes secrets:

```
AWS_ACCOUNT_ID=109995068952
AWS_REGION=us-east-1
ECR_REPOSITORY=shrt-backend
ECS_CLUSTER_PRODUCTION=shrt-production-cluster
ECS_SERVICE_PRODUCTION=shrt-production-service
TARGET_GROUP_ARN=arn:aws:elasticloadbalancing:...
ALB_DNS_NAME=shrt-production-alb-....elb.amazonaws.com
DB_PASSWORD=tu_password_seguro
```

### 2. Configurar OIDC (Recomendado para autenticación sin credenciales)

```bash
# Crear proveedor de identidad OIDC para GitHub
aws iam create-open-id-connect-provider \
  --url https://token.actions.githubusercontent.com \
  --client-id-list sts.amazonaws.com \
  --thumbprint-list 6938fd4d98bab03faadb97b34396831e3780aea1

# Crear rol para GitHub Actions
cat > github-actions-trust-policy.json <<EOF
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::${ACCOUNT_ID}:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "token.actions.githubusercontent.com:sub": "repo:tu-usuario/shrt:*"
        }
      }
    }
  ]
}
EOF

aws iam create-role \
  --role-name GitHubActionsRole \
  --assume-role-policy-document file://github-actions-trust-policy.json

# Adjuntar políticas necesarias
aws iam attach-role-policy \
  --role-name GitHubActionsRole \
  --policy-arn arn:aws:iam::aws:policy/AmazonEC2ContainerRegistryPowerUser

aws iam attach-role-policy \
  --role-name GitHubActionsRole \
  --policy-arn arn:aws:iam::aws:policy/AmazonECS_FullAccess
```

### 3. Trigger Deployment

```bash
# Push a main para desplegar a producción
git add .
git commit -m "Deploy to production"
git push origin main

# O ejecutar manualmente desde GitHub
# Repository → Actions → Backend CI/CD Pipeline → Run workflow
```

### 4. Monitorear Deployment

```bash
# Desde GitHub Actions UI:
# - Ve a la tab "Actions"
# - Selecciona el workflow en ejecución
# - Monitorea cada job en tiempo real

# Desde AWS CLI:
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-production-service \
  --query 'services[0].deployments'

# Ver eventos del servicio
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-production-service \
  --query 'services[0].events[0:10]'
```

---

## 🔐 Variables de Entorno

### Desarrollo Local (.env)

```env
APP_NAME=SHRT
APP_ENV=local
APP_KEY=base64:...
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
DB_DATABASE=/ruta/completa/database.sqlite

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis

LOG_CHANNEL=stack
LOG_LEVEL=debug
```

### Producción AWS (Task Definition)

```env
APP_NAME=SHRT
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=http://tu-alb-dns.elb.amazonaws.com

DB_CONNECTION=mysql
DB_HOST=tu-rds-endpoint.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=shrt
DB_USERNAME=admin
DB_PASSWORD=tu_password_seguro

REDIS_HOST=tu-redis-endpoint.cache.amazonaws.com
REDIS_PORT=6379
CACHE_STORE=redis
SESSION_DRIVER=redis

LOG_CHANNEL=stderr
LOG_LEVEL=error
```

---

## 🔍 Troubleshooting

### Problema: Container no inicia o crashea inmediatamente

**Solución:**
```bash
# Ver logs detallados
aws logs tail /ecs/shrt-backend-production --follow --since 10m

# Ver tasks fallidas
aws ecs describe-tasks \
  --cluster shrt-production-cluster \
  --tasks $(aws ecs list-tasks --cluster shrt-production-cluster --desired-status STOPPED --max-items 1 --query 'taskArns[0]' --output text)
```

### Problema: Error "Class 'Redis' not found"

**Causa:** La extensión de Redis no está instalada correctamente.

**Solución:** Verificar que el Dockerfile instale Redis vía PECL:
```dockerfile
RUN pecl install redis && docker-php-ext-enable redis
```

### Problema: 404 en rutas de documentación (/docs)

**Causa:** Route cache puede estar eliminando las rutas web.

**Solución:** En `entrypoint.sh`, NO cachear rutas o regenerar después de cambios:
```bash
php artisan route:clear
# NO ejecutar: php artisan route:cache
```

### Problema: Base de datos no conecta (fallback a SQLite)

**Solución:**
```bash
# 1. Verificar security groups
aws ec2 describe-security-groups --group-ids sg-XXXXX

# 2. Verificar endpoint de RDS
aws rds describe-db-instances --db-instance-identifier shrt-production-db

# 3. Test de conexión desde task
aws ecs execute-command \
  --cluster shrt-production-cluster \
  --task TASK_ID \
  --container app \
  --interactive \
  --command "/bin/sh"

# Dentro del container:
mysql -h $DB_HOST -u $DB_USERNAME -p$DB_PASSWORD -e "SELECT 1"
```

### Problema: Targets "unhealthy" en ALB

**Solución:**
```bash
# Verificar health check endpoint
curl http://TU_ALB_DNS/health

# Revisar configuración del target group
aws elbv2 describe-target-health --target-group-arn $TG_ARN

# Ajustar health check si es necesario
aws elbv2 modify-target-group \
  --target-group-arn $TG_ARN \
  --health-check-interval-seconds 30 \
  --health-check-timeout-seconds 5 \
  --healthy-threshold-count 2 \
  --unhealthy-threshold-count 3
```

### Problema: GitHub Actions falla en deployment

**Solución:**
```bash
# Verificar que los secrets estén configurados correctamente
# Repository → Settings → Secrets

# Verificar permisos del rol de GitHub Actions
aws iam get-role --role-name GitHubActionsRole

# Re-ejecutar el workflow desde GitHub UI
```

### Problema: Memoria o CPU insuficiente

**Solución:**
```bash
# Actualizar task definition con más recursos
# Editar task-definition.json:
"cpu": "1024",  # era 512
"memory": "2048",  # era 1024

# Re-registrar
aws ecs register-task-definition --cli-input-json file://task-definition.json

# Forzar nuevo deployment
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-production-service \
  --force-new-deployment
```

---

## 📊 Comandos Útiles

### Makefile

```bash
# Ver todos los comandos disponibles
make help

# Desarrollo local
make install        # Instalar dependencias
make dev           # Iniciar servidor de desarrollo
make test          # Ejecutar tests

# Deployment
make build-prod    # Build imagen de producción
make deploy        # Deployment completo
make logs          # Ver logs
```

### AWS CLI

```bash
# ECS
aws ecs list-clusters
aws ecs list-services --cluster shrt-production-cluster
aws ecs describe-services --cluster shrt-production-cluster --services shrt-production-service
aws ecs list-tasks --cluster shrt-production-cluster --service-name shrt-production-service
aws ecs update-service --cluster shrt-production-cluster --service shrt-production-service --force-new-deployment

# ECR
aws ecr describe-repositories
aws ecr describe-images --repository-name shrt-backend

# RDS
aws rds describe-db-instances
aws rds describe-db-instances --db-instance-identifier shrt-production-db

# Logs
aws logs tail /ecs/shrt-backend-production --follow
aws logs filter-log-events --log-group-name /ecs/shrt-backend-production --filter-pattern "ERROR"

# ALB
aws elbv2 describe-load-balancers
aws elbv2 describe-target-groups
aws elbv2 describe-target-health --target-group-arn $TG_ARN
```

### Docker

```bash
# Build local
docker build -t shrt-backend:local .

# Run local
docker run -p 8000:80 \
  -e APP_ENV=local \
  -e DB_CONNECTION=sqlite \
  shrt-backend:local

# Ver logs
docker logs -f CONTAINER_ID

# Inspeccionar container
docker exec -it CONTAINER_ID /bin/sh
```

---

## 📞 Soporte

Para problemas o preguntas:
- Crear issue en GitHub
- Revisar logs en AWS CloudWatch
- Verificar health checks en ALB

---

**Última actualización:** 30 de Septiembre, 2025
