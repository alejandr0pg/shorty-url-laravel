# ⚡ SHRT - Quick Start Guide

Comandos esenciales para empezar rápido.

---

## 🚀 Setup Local en 5 minutos

### Opción 1: Sin Docker (Recomendado para desarrollo)

```bash
# 1. Clonar e instalar
git clone https://github.com/tu-usuario/shrt.git
cd shrt
composer install

# 2. Configurar environment
cp .env.example .env
php artisan key:generate

# 3. Base de datos SQLite (más simple)
touch database/database.sqlite

# 4. Editar .env y configurar SQLite
# DB_CONNECTION=sqlite
# DB_DATABASE=/ruta/completa/a/tu/proyecto/shrt/database/database.sqlite

# 5. Migrar base de datos
php artisan migrate --seed

# 6. Generar documentación API
php artisan scribe:generate

# 7. Iniciar servidor
php artisan serve
# → http://localhost:8000
```

**Verificar:**
```bash
curl http://localhost:8000/health
open http://localhost:8000/docs
```

### Opción 2: Con Docker Compose

```bash
# 1. Clonar
git clone https://github.com/tu-usuario/shrt.git
cd shrt

# 2. Iniciar servicios
docker-compose up -d

# 3. Ver logs
docker-compose logs -f

# → http://localhost:8000
```

**Servicios disponibles:**
- API Backend: http://localhost:8000
- MySQL: localhost:3306
- Redis: localhost:6379

---

## ☁️ Deploy a AWS (Producción)

### Pre-requisitos

```bash
# 1. Instalar AWS CLI
# macOS
brew install awscli

# Ubuntu/Debian
sudo apt install awscli

# 2. Configurar credenciales
aws configure
# AWS Access Key ID: TU_ACCESS_KEY
# AWS Secret Access Key: TU_SECRET_KEY
# Default region: us-east-1
# Default output format: json

# 3. Obtener tu Account ID
export AWS_ACCOUNT_ID=$(aws sts get-caller-identity --query Account --output text)
echo $AWS_ACCOUNT_ID
```

---

## 🏗️ PARTE 1: Infraestructura Base AWS

### 1.1. Crear VPC y Networking (si no existe)

```bash
# Obtener VPC por defecto
export VPC_ID=$(aws ec2 describe-vpcs --filters "Name=is-default,Values=true" --query 'Vpcs[0].VpcId' --output text)
echo "VPC ID: $VPC_ID"

# Obtener subnets públicas
export SUBNET_1=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" "Name=availability-zone,Values=us-east-1a" --query 'Subnets[0].SubnetId' --output text)
export SUBNET_2=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" "Name=availability-zone,Values=us-east-1b" --query 'Subnets[0].SubnetId' --output text)
export SUBNET_3=$(aws ec2 describe-subnets --filters "Name=vpc-id,Values=$VPC_ID" "Name=availability-zone,Values=us-east-1c" --query 'Subnets[0].SubnetId' --output text)

echo "Subnet 1: $SUBNET_1"
echo "Subnet 2: $SUBNET_2"
echo "Subnet 3: $SUBNET_3"
```

### 1.2. Crear Security Groups

```bash
# Security Group para ALB
aws ec2 create-security-group \
  --group-name shrt-alb-sg \
  --description "Security group for SHRT Application Load Balancer" \
  --vpc-id $VPC_ID

export ALB_SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=shrt-alb-sg" --query 'SecurityGroups[0].GroupId' --output text)

# Permitir HTTP desde internet
aws ec2 authorize-security-group-ingress \
  --group-id $ALB_SG_ID \
  --protocol tcp \
  --port 80 \
  --cidr 0.0.0.0/0

# Security Group para ECS Tasks
aws ec2 create-security-group \
  --group-name shrt-ecs-sg \
  --description "Security group for SHRT ECS tasks" \
  --vpc-id $VPC_ID

export ECS_SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=shrt-ecs-sg" --query 'SecurityGroups[0].GroupId' --output text)

# Permitir tráfico desde ALB
aws ec2 authorize-security-group-ingress \
  --group-id $ECS_SG_ID \
  --protocol tcp \
  --port 80 \
  --source-group $ALB_SG_ID

# Security Group para RDS
aws ec2 create-security-group \
  --group-name shrt-rds-sg \
  --description "Security group for SHRT RDS" \
  --vpc-id $VPC_ID

export RDS_SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=shrt-rds-sg" --query 'SecurityGroups[0].GroupId' --output text)

# Permitir MySQL desde ECS
aws ec2 authorize-security-group-ingress \
  --group-id $RDS_SG_ID \
  --protocol tcp \
  --port 3306 \
  --source-group $ECS_SG_ID

# Permitir desde VPC completa
aws ec2 authorize-security-group-ingress \
  --group-id $RDS_SG_ID \
  --protocol tcp \
  --port 3306 \
  --cidr 172.31.0.0/16

echo "ALB SG: $ALB_SG_ID"
echo "ECS SG: $ECS_SG_ID"
echo "RDS SG: $RDS_SG_ID"
```

---

## 🗄️ PARTE 2: Base de Datos (RDS MySQL)

```bash
# Crear DB Subnet Group
aws rds create-db-subnet-group \
  --db-subnet-group-name shrt-db-subnet \
  --db-subnet-group-description "Subnet group for Shrt database" \
  --subnet-ids $SUBNET_1 $SUBNET_2 $SUBNET_3

# Generar contraseña segura
export DB_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)
echo "⚠️  GUARDAR ESTA CONTRASEÑA: $DB_PASSWORD"

# Crear RDS Instance
aws rds create-db-instance \
  --db-instance-identifier shrt-production-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0.42 \
  --master-username admin \
  --master-user-password "$DB_PASSWORD" \
  --allocated-storage 20 \
  --storage-type gp2 \
  --vpc-security-group-ids $RDS_SG_ID \
  --db-subnet-group-name shrt-db-subnet \
  --backup-retention-period 7 \
  --publicly-accessible \
  --storage-encrypted \
  --db-name shrt

echo "⏳ Esperando a que RDS esté disponible (esto toma 5-10 minutos)..."
aws rds wait db-instance-available --db-instance-identifier shrt-production-db

# Obtener endpoint
export DB_HOST=$(aws rds describe-db-instances \
  --db-instance-identifier shrt-production-db \
  --query 'DBInstances[0].Endpoint.Address' \
  --output text)

echo "✅ RDS creado exitosamente!"
echo "DB Host: $DB_HOST"
echo "DB Password: $DB_PASSWORD"
```

---

## 🔴 PARTE 3: Redis Cache (ElastiCache)

```bash
# Crear subnet group para Redis
aws elasticache create-cache-subnet-group \
  --cache-subnet-group-name shrt-redis-subnet \
  --cache-subnet-group-description "Subnet group for Shrt Redis" \
  --subnet-ids $SUBNET_1 $SUBNET_2 $SUBNET_3

# Crear Redis cluster
aws elasticache create-cache-cluster \
  --cache-cluster-id shrt-production-redis \
  --engine redis \
  --cache-node-type cache.t3.micro \
  --num-cache-nodes 1 \
  --engine-version 7.0 \
  --cache-subnet-group-name shrt-redis-subnet \
  --security-group-ids $ECS_SG_ID

echo "⏳ Esperando a que Redis esté disponible..."
aws elasticache wait cache-cluster-available --cache-cluster-id shrt-production-redis

# Obtener endpoint
export REDIS_HOST=$(aws elasticache describe-cache-clusters \
  --cache-cluster-id shrt-production-redis \
  --show-cache-node-info \
  --query 'CacheClusters[0].CacheNodes[0].Endpoint.Address' \
  --output text)

echo "✅ Redis creado exitosamente!"
echo "Redis Host: $REDIS_HOST"
```

---

## 📦 PARTE 4: ECR y Docker Image

```bash
# Crear repositorio ECR
aws ecr create-repository \
  --repository-name shrt-backend \
  --region us-east-1

# Obtener URI
export ECR_URI=$(aws ecr describe-repositories \
  --repository-names shrt-backend \
  --query 'repositories[0].repositoryUri' \
  --output text)

echo "ECR Repository: $ECR_URI"

# Login a ECR
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com

# Build de la imagen (desde el directorio del proyecto)
docker build -t shrt-backend:latest .

# Tag y Push
docker tag shrt-backend:latest $ECR_URI:latest
docker tag shrt-backend:latest $ECR_URI:$(git rev-parse --short HEAD)

docker push $ECR_URI:latest
docker push $ECR_URI:$(git rev-parse --short HEAD)

echo "✅ Imagen subida a ECR!"
```

---

## ⚙️ PARTE 5: IAM Roles para ECS

```bash
# Crear rol de ejecución
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

# Crear rol de tarea
aws iam create-role \
  --role-name ecsTaskRole \
  --assume-role-policy-document file://ecs-task-execution-role.json

echo "✅ Roles IAM creados!"
```

---

## 🔀 PARTE 6: Application Load Balancer

```bash
# Crear ALB
aws elbv2 create-load-balancer \
  --name shrt-production-alb \
  --subnets $SUBNET_1 $SUBNET_2 $SUBNET_3 \
  --security-groups $ALB_SG_ID \
  --scheme internet-facing \
  --type application \
  --ip-address-type ipv4

# Obtener ARN del ALB
export ALB_ARN=$(aws elbv2 describe-load-balancers \
  --names shrt-production-alb \
  --query 'LoadBalancers[0].LoadBalancerArn' \
  --output text)

# Obtener DNS del ALB
export ALB_DNS=$(aws elbv2 describe-load-balancers \
  --names shrt-production-alb \
  --query 'LoadBalancers[0].DNSName' \
  --output text)

# Crear Target Group
aws elbv2 create-target-group \
  --name shrt-production-tg \
  --protocol HTTP \
  --port 80 \
  --vpc-id $VPC_ID \
  --target-type ip \
  --health-check-path /health \
  --health-check-interval-seconds 30 \
  --health-check-timeout-seconds 5 \
  --healthy-threshold-count 2 \
  --unhealthy-threshold-count 3

# Obtener ARN del Target Group
export TG_ARN=$(aws elbv2 describe-target-groups \
  --names shrt-production-tg \
  --query 'TargetGroups[0].TargetGroupArn' \
  --output text)

# Crear Listener
aws elbv2 create-listener \
  --load-balancer-arn $ALB_ARN \
  --protocol HTTP \
  --port 80 \
  --default-actions Type=forward,TargetGroupArn=$TG_ARN

echo "✅ ALB creado exitosamente!"
echo "ALB DNS: $ALB_DNS"
echo "Target Group ARN: $TG_ARN"
```

---

## 🐳 PARTE 7: ECS Cluster y Task Definition

```bash
# Crear cluster
aws ecs create-cluster \
  --cluster-name shrt-production-cluster \
  --capacity-providers FARGATE \
  --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1

# Crear log group
aws logs create-log-group --log-group-name /ecs/shrt-backend-production

# Crear task definition
cat > task-definition.json <<EOF
{
  "family": "shrt-backend-production",
  "networkMode": "awsvpc",
  "requiresCompatibilities": ["FARGATE"],
  "cpu": "512",
  "memory": "1024",
  "executionRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ecsTaskExecutionRole",
  "taskRoleArn": "arn:aws:iam::${AWS_ACCOUNT_ID}:role/ecsTaskRole",
  "containerDefinitions": [
    {
      "name": "app",
      "image": "${ECR_URI}:latest",
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
        {"name": "APP_KEY", "value": "base64:$(openssl rand -base64 32)"},
        {"name": "APP_DEBUG", "value": "false"},
        {"name": "APP_URL", "value": "http://${ALB_DNS}"},
        {"name": "LOG_CHANNEL", "value": "stderr"},
        {"name": "LOG_LEVEL", "value": "error"},
        {"name": "DB_CONNECTION", "value": "mysql"},
        {"name": "DB_HOST", "value": "${DB_HOST}"},
        {"name": "DB_PORT", "value": "3306"},
        {"name": "DB_DATABASE", "value": "shrt"},
        {"name": "DB_USERNAME", "value": "admin"},
        {"name": "DB_PASSWORD", "value": "${DB_PASSWORD}"},
        {"name": "REDIS_HOST", "value": "${REDIS_HOST}"},
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

# Registrar task definition
aws ecs register-task-definition --cli-input-json file://task-definition.json

echo "✅ Task definition registrada!"
```

---

## 🚀 PARTE 8: Crear ECS Service y Desplegar

```bash
# Crear servicio
aws ecs create-service \
  --cluster shrt-production-cluster \
  --service-name shrt-production-service \
  --task-definition shrt-backend-production \
  --desired-count 1 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$SUBNET_1,$SUBNET_2,$SUBNET_3],securityGroups=[$ECS_SG_ID],assignPublicIp=ENABLED}" \
  --load-balancers "targetGroupArn=$TG_ARN,containerName=app,containerPort=80"

echo "⏳ Esperando a que el servicio esté estable (esto toma 2-3 minutos)..."
aws ecs wait services-stable \
  --cluster shrt-production-cluster \
  --services shrt-production-service

echo "✅ Servicio desplegado exitosamente!"
echo ""
echo "🎉 DEPLOYMENT COMPLETO!"
echo "================================"
echo "API URL: http://$ALB_DNS"
echo "Docs: http://$ALB_DNS/docs"
echo "Health: http://$ALB_DNS/health"
echo ""
echo "⚠️  GUARDAR ESTAS CREDENCIALES:"
echo "DB Host: $DB_HOST"
echo "DB Password: $DB_PASSWORD"
echo "Redis Host: $REDIS_HOST"
echo "ALB DNS: $ALB_DNS"
```

---

## ✅ Verificación Post-Deploy

```bash
# 1. Health check
curl http://$ALB_DNS/health | jq '.'

# 2. Ver logs
aws logs tail /ecs/shrt-backend-production --follow

# 3. Ver tasks corriendo
aws ecs list-tasks \
  --cluster shrt-production-cluster \
  --service-name shrt-production-service

# 4. Estado del servicio
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-production-service \
  --query 'services[0].{Status: status, Running: runningCount, Desired: desiredCount}'
```

---

## 🔄 Updates Posteriores

```bash
# Build nueva imagen
docker build -t shrt-backend:latest .

# Login y push
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin $AWS_ACCOUNT_ID.dkr.ecr.us-east-1.amazonaws.com

docker tag shrt-backend:latest $ECR_URI:latest
docker push $ECR_URI:latest

# Forzar nuevo deployment
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-production-service \
  --force-new-deployment

# Esperar a que esté estable
aws ecs wait services-stable \
  --cluster shrt-production-cluster \
  --services shrt-production-service
```

---

## 🆘 Troubleshooting Rápido

### Container no inicia

```bash
# Ver logs
aws logs tail /ecs/shrt-backend-production --follow --since 10m

# Ver último task que falló
aws ecs list-tasks --cluster shrt-production-cluster --desired-status STOPPED --max-items 1
```

### Base de datos no conecta

```bash
# Verificar security groups
aws ec2 describe-security-groups --group-ids $RDS_SG_ID

# Test de conexión
mysql -h $DB_HOST -u admin -p
```

### Targets unhealthy

```bash
# Ver health de targets
aws elbv2 describe-target-health --target-group-arn $TG_ARN
```

---

## 📚 Siguiente Paso: GitHub Actions

Para automatizar deployments, configura GitHub Actions:

**Repository → Settings → Secrets and variables → Actions**

Agregar:
- `AWS_ACCOUNT_ID`
- `AWS_REGION`
- `ECR_REPOSITORY`
- `ECS_CLUSTER_PRODUCTION`
- `ECS_SERVICE_PRODUCTION`
- `DB_PASSWORD`
- `TARGET_GROUP_ARN`
- `ALB_DNS_NAME`

---

**Para más detalles:** Ver [DEPLOYMENT.md](DEPLOYMENT.md) y [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

**Última actualización:** 30 de Septiembre, 2025
