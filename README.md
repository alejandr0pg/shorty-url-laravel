# Shrt - URL Shortener Backend

Un acortador de URLs desarrollado con Laravel (backend), diseñado para ser escalable, seguro y fácil de usar con despliegue en AWS ECS.

## 🌐 URLs de Producción - Acceso Directo

### 🚀 PRODUCCIÓN

-   **🖥️ Frontend:** https://d3dcezd6ji3gto.cloudfront.net
-   **⚡ API Backend:** http://shrt-production-alb-132772302.us-east-1.elb.amazonaws.com
-   **📖 API Docs:** http://shrt-production-alb-132772302.us-east-1.elb.amazonaws.com/docs
-   **📊 Load Balancer:** `shrt-production-alb` (ALB configurado)
-   **🎯 Target Group:** `shrt-production-tg` con health checks en `/health`

### 🧪 STAGING

-   **🖥️ Frontend:** https://d22b8xej3kve4.cloudfront.net
-   **⚡ API Backend:** Se activa al crear la rama `develop`

---

## 🚀 Características

-   **Acortamiento de URLs**: Genera códigos únicos de hasta 8 caracteres fáciles de leer.
-   **API RESTful**: Endpoints para crear, listar y redirigir URLs.
-   **Interfaz React**: Frontend moderno con TailwindCSS.
-   **Seguridad**: Validación de URLs según RFC 1738, rate limiting, protección contra inyección SQL.
-   **Caché**: Uso de Redis para mejorar rendimiento en redirecciones.
-   **Pruebas**: Cobertura completa con Pest (backend) y Jest (frontend).
-   **CI/CD**: GitHub Actions para automatización de pruebas y despliegue a AWS.
-   **Monitoreo**: Logging avanzado, health checks y métricas de performance.

## 📋 Requisitos

### Desarrollo Local

-   PHP 8.2+
-   Node.js 18+
-   Composer
-   Docker & Docker Compose
-   Redis (opcional para desarrollo)

### Producción AWS

-   Cuenta AWS activa
-   AWS CLI configurado
-   Dominio propio
-   GitHub account para CI/CD

## 🛠 Instalación Local

### Usando Docker (Recomendado)

```bash
# 1. Clonar el repositorio
git clone <repo-url>
cd shrt

# 2. Iniciar todos los servicios
make up

# 3. Configurar la aplicación
make setup

# 4. Ejecutar migraciones
make migrate

# La aplicación estará disponible en:
# Backend: http://localhost:8000
# Frontend: http://localhost:3000
# Base de datos: localhost:3306
```

### Instalación Manual

#### Backend (Laravel)

```bash
# 1. Instalar dependencias
composer install

# 2. Configurar entorno
cp .env.example .env
php artisan key:generate

# 3. Configurar base de datos (editar .env)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shrt
DB_USERNAME=root
DB_PASSWORD=password

# 4. Ejecutar migraciones
php artisan migrate --seed

# 5. Iniciar servidor
php artisan serve
```

#### Frontend (React)

```bash
# 1. Ir al directorio frontend
cd frontend

# 2. Instalar dependencias
npm install

# 3. Configurar variables de entorno
cp .env.example .env.local

# 4. Iniciar servidor de desarrollo
npm run dev
```

## 🚀 Deployment Completo del Backend desde Cero

### Comandos para Deployment ECS Inicial

```bash
# 1. Configurar AWS CLI
aws configure
# AWS Access Key ID: [TU_ACCESS_KEY]
# AWS Secret Access Key: [TU_SECRET_KEY]
# Default region: us-east-1
# Default output format: json

# 2. Crear repositorio ECR para imágenes Docker
aws ecr create-repository --repository-name shrt-backend --region us-east-1

# 3. Obtener comandos de login para ECR
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin 109995068952.dkr.ecr.us-east-1.amazonaws.com

# 4. Construir y subir imagen Docker (desde el directorio del backend)
docker build -t shrt-backend .
docker tag shrt-backend:latest 109995068952.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest-production
docker push 109995068952.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest-production

# 5. Crear roles IAM necesarios
# Rol de ejecución ECS
aws iam create-role --role-name ecsTaskExecutionRole --assume-role-policy-document '{
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
}'

# Rol de tarea ECS
aws iam create-role --role-name ecsTaskRole --assume-role-policy-document '{
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
}'

# 6. Adjuntar políticas a los roles
aws iam attach-role-policy --role-name ecsTaskExecutionRole --policy-arn arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy

# Política adicional para logs
aws iam create-policy --policy-name ECSAdditionalLogsPolicy --policy-document '{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": "*"
    }
  ]
}'

aws iam attach-role-policy --role-name ecsTaskExecutionRole --policy-arn arn:aws:iam::109995068952:policy/ECSAdditionalLogsPolicy

# 7. Crear security group
aws ec2 create-security-group \
  --group-name shrt-backend-sg \
  --description "Security group for Shrt backend service"

# Obtener ID del security group y configurar reglas
SG_ID=$(aws ec2 describe-security-groups --filters "Name=group-name,Values=shrt-backend-sg" --query 'SecurityGroups[0].GroupId' --output text)

aws ec2 authorize-security-group-ingress \
  --group-id $SG_ID \
  --protocol tcp \
  --port 8000 \
  --cidr 0.0.0.0/0

# 8. Crear log group de CloudWatch
aws logs create-log-group --log-group-name /ecs/shrt-backend

# 9. Crear cluster ECS
aws ecs create-cluster --cluster-name shrt-backend-production

# 10. Crear task definition (colocar en task-definition.json)
# Ver contenido completo más abajo

# 11. Registrar task definition
aws ecs register-task-definition --cli-input-json file://task-definition.json

# 12. Obtener subnets públicas
SUBNETS=$(aws ec2 describe-subnets --filters "Name=availability-zone,Values=us-east-1a,us-east-1b" --query "Subnets[?MapPublicIpOnLaunch==\`true\`].[SubnetId]" --output text | tr '\n' ',')

# 13. Crear servicio ECS
aws ecs create-service \
  --cluster shrt-backend-production \
  --service-name shrt-backend-production \
  --task-definition shrt-backend-production \
  --desired-count 1 \
  --launch-type FARGATE \
  --network-configuration "awsvpcConfiguration={subnets=[$SUBNETS],securityGroups=[$SG_ID],assignPublicIp=ENABLED}" \
  --enable-execute-command

# 14. Verificar el deployment
aws ecs describe-services --cluster shrt-backend-production --services shrt-backend-production

# 15. Obtener IP pública del contenedor
TASK_ARN=$(aws ecs list-tasks --cluster shrt-backend-production --service-name shrt-backend-production --query 'taskArns[0]' --output text)
ENI_ID=$(aws ecs describe-tasks --cluster shrt-backend-production --tasks $TASK_ARN --query "tasks[0].attachments[0].details[?name=='networkInterfaceId'].value" --output text)
PUBLIC_IP=$(aws ec2 describe-network-interfaces --network-interface-ids $ENI_ID --query "NetworkInterfaces[0].Association.PublicIp" --output text)

echo "Backend disponible en: http://$PUBLIC_IP:8000"
```

### Task Definition para ECS

Crear archivo `task-definition.json`:

```json
{
    "family": "shrt-backend-production",
    "networkMode": "awsvpc",
    "requiresCompatibilities": ["FARGATE"],
    "cpu": "256",
    "memory": "512",
    "executionRoleArn": "arn:aws:iam::109995068952:role/ecsTaskExecutionRole",
    "taskRoleArn": "arn:aws:iam::109995068952:role/ecsTaskRole",
    "containerDefinitions": [
        {
            "name": "app",
            "image": "109995068952.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest-production",
            "essential": true,
            "portMappings": [
                {
                    "containerPort": 8000,
                    "protocol": "tcp"
                }
            ],
            "logConfiguration": {
                "logDriver": "awslogs",
                "options": {
                    "awslogs-group": "/ecs/shrt-backend",
                    "awslogs-region": "us-east-1",
                    "awslogs-stream-prefix": "ecs",
                    "awslogs-create-group": "true"
                }
            },
            "environment": [
                {
                    "name": "APP_ENV",
                    "value": "production"
                },
                {
                    "name": "APP_DEBUG",
                    "value": "false"
                },
                {
                    "name": "LOG_CHANNEL",
                    "value": "stderr"
                },
                {
                    "name": "APP_URL",
                    "value": "http://localhost:8000"
                }
            ]
        }
    ]
}
```

## ☁️ Configuración AWS

### Paso 1: Configurar AWS CLI

```bash
# Instalar AWS CLI
curl "https://awscli.amazonaws.com/AWSCLIV2.pkg" -o "AWSCLIV2.pkg"
sudo installer -pkg AWSCLIV2.pkg -target /

# Configurar credenciales
aws configure
# AWS Access Key ID: TU_ACCESS_KEY
# AWS Secret Access Key: TU_SECRET_KEY
# Default region: us-east-1
# Default output format: json
```

### Paso 2: Crear Infraestructura Base

```bash
# Crear VPC y componentes de red
aws cloudformation create-stack \
  --stack-name shrt-networking \
  --template-body file://infrastructure/networking.yaml \
  --capabilities CAPABILITY_IAM

# Crear repositorio ECR para el backend
aws ecr create-repository \
  --repository-name {YOUR_APP}-backend \
  --region us-east-1

# Obtener URI del repositorio (guarda este valor)
aws ecr describe-repositories \
  --repository-names {YOUR_APP}-backend \
  --query 'repositories[0].repositoryUri' \
  --output text
```

### Paso 3: Configurar Base de Datos

```bash
# Crear subnet group para RDS
aws rds create-db-subnet-group \
  --db-subnet-group-name shrt-db-subnet \
  --db-subnet-group-description "Subnet group for Shrt database" \
  --subnet-ids subnet-xxxxx subnet-yyyyy  # Reemplazar con tus subnet IDs

# Crear instancia RDS MySQL
aws rds create-db-instance \
  --db-instance-identifier {YOUR_APP}-production-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0 \
  --allocated-storage 20 \
  --storage-type gp2 \
  --db-name {YOUR_APP} \
  --master-username admin \
  --master-user-password $(openssl rand -base64 32) \
  --db-subnet-group-name {YOUR_APP}-db-subnet \
  --vpc-security-group-ids sg-xxxxx \
  --backup-retention-period 7 \
  --multi-az \
  --storage-encrypted
```

### Paso 4: Configurar Redis

```bash
# Crear subnet group para ElastiCache
aws elasticache create-cache-subnet-group \
  --cache-subnet-group-name shrt-redis-subnet \
  --cache-subnet-group-description "Subnet group for Shrt Redis" \
  --subnet-ids subnet-xxxxx subnet-yyyyy

# Crear cluster Redis
aws elasticache create-replication-group \
  --replication-group-id {YOUR_APP}-production-redis \
  --description "Redis cluster for {YOUR_APP} production" \
  --num-cache-clusters 2 \
  --cache-node-type cache.t3.micro \
  --engine redis \
  --engine-version 7.0 \
  --cache-subnet-group-name {YOUR_APP}-redis-subnet \
  --security-group-ids sg-xxxxx \
  --at-rest-encryption-enabled \
  --transit-encryption-enabled
```

### Paso 5: Crear ECS Clusters

```bash
# Crear cluster de staging
aws ecs create-cluster \
  --cluster-name {YOUR_APP}-staging-cluster \
  --capacity-providers FARGATE \
  --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1

# Crear cluster de producción
aws ecs create-cluster \
  --cluster-name {YOUR_APP}-production-cluster \
  --capacity-providers FARGATE \
  --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1

# Crear Application Load Balancer
aws elbv2 create-load-balancer \
  --name {YOUR_APP}-production-alb \
  --subnets subnet-xxxxx subnet-yyyyy \
  --security-groups sg-xxxxx \
  --scheme internet-facing \
  --type application \
  --ip-address-type ipv4
```

### Paso 6: Configurar S3 para Frontend

```bash
# Crear buckets para frontend
aws s3 mb s3://{YOUR_DOMAIN}-frontend-staging --region us-east-1
aws s3 mb s3://{YOUR_DOMAIN}-frontend-production --region us-east-1
aws s3 mb s3://{YOUR_DOMAIN}-backups --region us-east-1

# Configurar hosting estático
aws s3 website s3://{YOUR_DOMAIN}-frontend-production \
  --index-document index.html \
  --error-document index.html

# Configurar política de bucket
aws s3api put-bucket-policy \
  --bucket {YOUR_DOMAIN}-frontend-production \
  --policy '{
    "Version": "2012-10-17",
    "Statement": [
      {
        "Sid": "PublicReadGetObject",
        "Effect": "Allow",
        "Principal": "*",
        "Action": "s3:GetObject",
        "Resource": "arn:aws:s3:::{YOUR_DOMAIN}-frontend-production/*"
      }
    ]
  }'
```

### Paso 7: Configurar CloudFront

```bash
# Crear distribución CloudFront para producción
aws cloudfront create-distribution \
  --distribution-config '{
    "CallerReference": "{YOUR_APP}-prod-'$(date +%s)'",
    "Comment": "{YOUR_APP} Production CDN",
    "Origins": {
      "Quantity": 1,
      "Items": [
        {
          "Id": "S3-{YOUR_DOMAIN}-frontend-production",
          "DomainName": "{YOUR_DOMAIN}-frontend-production.s3.us-east-1.amazonaws.com",
          "S3OriginConfig": {
            "OriginAccessIdentity": ""
          }
        }
      ]
    },
    "DefaultCacheBehavior": {
      "TargetOriginId": "S3-{YOUR_DOMAIN}-frontend-production",
      "ViewerProtocolPolicy": "redirect-to-https",
      "TrustedSigners": {
        "Enabled": false,
        "Quantity": 0
      },
      "ForwardedValues": {
        "QueryString": false,
        "Cookies": {"Forward": "none"}
      },
      "MinTTL": 0
    },
    "Enabled": true,
    "PriceClass": "PriceClass_100"
  }'
```

## 🔐 GitHub Secrets Configuration

📖 **[Ver Guía Completa de GitHub Secrets](GITHUB_SECRETS_SETUP.md)**

Ve a tu repositorio GitHub → Settings → Secrets and variables → Actions y configura los siguientes secrets:

### 🔐 GitHub Secrets Necesarios:

#### ☁️ Credenciales AWS (OBLIGATORIOS)

```bash
AWS_ACCESS_KEY_ID=AKIA***************XYZ
AWS_SECRET_ACCESS_KEY=abc123***************************xyz789
AWS_ACCOUNT_ID=109995068952
AWS_REGION=us-east-1
```

#### 🐳 Configuración Docker/ECR (OBLIGATORIOS)

```bash
ECR_REGISTRY=109995068952.dkr.ecr.us-east-1.amazonaws.com
ECR_REPOSITORY=shrt-backend
```

#### 🚀 Configuración ECS (OBLIGATORIOS)

```bash
ECS_CLUSTER_STAGING=shrt-staging-cluster
ECS_CLUSTER_PRODUCTION=shrt-backend-production
ECS_SERVICE_STAGING=shrt-backend-staging
ECS_SERVICE_PRODUCTION=shrt-backend-production
ECS_TASK_DEFINITION_STAGING=shrt-backend-staging
ECS_TASK_DEFINITION_PRODUCTION=shrt-backend-production
```

#### 🗄️ Base de Datos (OBLIGATORIOS)

```bash
DB_HOST_STAGING=shrt-staging-db.*****.us-east-1.rds.amazonaws.com
DB_HOST_PRODUCTION=shrt-production-db.*****.us-east-1.rds.amazonaws.com
DB_DATABASE=shrt
DB_USERNAME=admin
DB_PASSWORD=Super***SecretPass***2024
DB_PORT=3306
```

#### 🔥 Redis Cache (OBLIGATORIOS)

```bash
REDIS_HOST_STAGING=shrt-staging-redis.*****.cache.amazonaws.com
REDIS_HOST_PRODUCTION=shrt-production-redis.*****.cache.amazonaws.com
REDIS_PORT=6379
REDIS_PASSWORD=redis***secret***pass***2024
```

#### 🌐 Frontend S3/CloudFront (OBLIGATORIOS)

```bash
S3_BUCKET_STAGING=shrt-frontend-staging
S3_BUCKET_PRODUCTION=shrt-frontend-production
CLOUDFRONT_DISTRIBUTION_ID_STAGING=E2Q0FJ804E8MGI
CLOUDFRONT_DISTRIBUTION_ID_PRODUCTION=E1JT122OSSCK8R
```

#### 🔐 Aplicación Laravel (OBLIGATORIOS)

```bash
APP_KEY_STAGING=base64:abc123***************************xyz789==
APP_KEY_PRODUCTION=base64:def456***************************uvw012==
APP_ENV_STAGING=staging
APP_ENV_PRODUCTION=production
APP_DEBUG_STAGING=true
APP_DEBUG_PRODUCTION=false
APP_URL_STAGING=http://shrt-staging-alb-***.us-east-1.elb.amazonaws.com
APP_URL_PRODUCTION=http://shrt-production-alb-***.us-east-1.elb.amazonaws.com
```

#### 📧 Configuración de Correo (OPCIONALES)

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your***app***specific***password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourapp.com
MAIL_FROM_NAME="Shrt URL Shortener"
```

#### 🔍 Monitoreo y Logs (OPCIONALES)

```bash
LOG_CHANNEL=cloudwatch
LOG_LEVEL=info
SENTRY_LARAVEL_DSN=https://***@***.ingest.sentry.io/***
NEW_RELIC_LICENSE_KEY=***license_key***
```

#### 🛡️ Seguridad Adicional (RECOMENDADOS)

```bash
JWT_SECRET=your***jwt***secret***key***here
BCRYPT_ROUNDS=12
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=yourapp.com,www.yourapp.com
```

### 📋 Pasos para Configurar:

1. **Ve a tu repositorio GitHub**
2. **Navega a:** Settings → Secrets and variables → Actions
3. **Haz clic en:** "New repository secret"
4. **Agrega cada secret** con su nombre exacto y valor real
5. **Verifica** que todos los secrets estén configurados correctamente

### ⚠️ Notas Importantes:

-   **Reemplaza los valores ofuscados** (marcados con `***`) con tus valores reales
-   **Nunca commitees secrets** en el código fuente
-   **Usa valores únicos** para staging y production
-   **Genera APP_KEY** con: `php artisan key:generate --show`
-   **Las credenciales AWS** deben tener permisos para ECS, ECR, S3, RDS y CloudFront
-   **Verifica que todos los recursos AWS** existan antes del deployment

## 📦 Comandos Docker Disponibles

```bash
# Desarrollo
make up          # Iniciar todos los servicios
make down        # Parar todos los servicios
make setup       # Configuración inicial
make migrate     # Ejecutar migraciones
make seed        # Poblar base de datos
make test        # Ejecutar tests
make logs        # Ver logs

# Producción
make build-prod  # Construir imagen de producción
make push-prod   # Subir imagen a ECR
make deploy      # Desplegar a AWS
```

## 🧪 Testing

### Backend Tests

```bash
# Ejecutar todos los tests
php artisan test

# Con cobertura
php artisan test --coverage

# Tests específicos
php artisan test --filter=UrlShortenerTest
```

### Frontend Tests

```bash
cd frontend

# Ejecutar tests
npm test

# Con cobertura
npm run test:coverage

# Tests en modo watch
npm run test:watch
```

## 🔍 Monitoreo y Logs

### Verificar Estado de Servicios

```bash
# ECS Service Status
aws ecs describe-services \
  --cluster shrt-backend-production \
  --services shrt-backend-production \
  --query "services[0].[serviceName,status,runningCount,desiredCount]" \
  --output table

# Listar tareas corriendo
aws ecs list-tasks \
  --cluster shrt-backend-production \
  --service-name shrt-backend-production

# Obtener IP pública del backend (MÉTODO ACTUALIZADO - 2025)
TASK_ARN=$(aws ecs list-tasks --cluster shrt-backend-production --region us-east-1 --query 'taskArns[0]' --output text)
TASK_ID=$(echo $TASK_ARN | cut -d'/' -f3)
ENI_ID=$(aws ecs describe-tasks --cluster shrt-backend-production --tasks $TASK_ID --region us-east-1 --query "tasks[0].attachments[0].details[?name=='networkInterfaceId'].value" --output text)
PUBLIC_IP=$(aws ec2 describe-network-interfaces --network-interface-ids $ENI_ID --region us-east-1 --query "NetworkInterfaces[0].Association.PublicIp" --output text)
echo "Backend accesible en: http://$PUBLIC_IP:8000"

# Verificación rápida del estado actual
echo "Estado actual del backend:"
aws ecs describe-services \
  --cluster shrt-backend-production \
  --services shrt-backend-production \
  --region us-east-1 \
  --query "services[0].[serviceName,status,runningCount,desiredCount]" \
  --output table
```

### Ver Logs

```bash
# Ver log groups disponibles
aws logs describe-log-groups --log-group-name-prefix /ecs/shrt

# Ver logs recientes del backend
aws logs filter-log-events \
  --log-group-name /ecs/shrt-backend \
  --start-time $(date -d '1 hour ago' +%s)000

# Ver logs de una tarea específica
TASK_ID=[TASK_ID_AQUI]
aws logs get-log-events \
  --log-group-name /ecs/shrt-backend \
  --log-stream-name ecs/app/$TASK_ID \
  --query "events[*].[timestamp,message]" \
  --output table
```

### Comandos de Mantenimiento

```bash
# Reiniciar servicio ECS
aws ecs update-service \
  --cluster shrt-backend-production \
  --service shrt-backend-production \
  --force-new-deployment

# Escalar servicio
aws ecs update-service \
  --cluster shrt-backend-production \
  --service shrt-backend-production \
  --desired-count 2

# Verificar estado del cluster
aws ecs describe-clusters \
  --clusters shrt-backend-production \
  --include STATISTICS

# Ver eventos recientes del servicio
aws ecs describe-services \
  --cluster shrt-backend-production \
  --services shrt-backend-production \
  --query "services[0].events[0:5]" \
  --output table
```

## 🚀 Despliegue

### Despliegue Automático (GitHub Actions)

```bash
# Staging
git checkout develop
git add .
git commit -m "feat: nueva funcionalidad"
git push origin develop

# Production
git checkout main
git merge develop
git push origin main
```

### Despliegue Manual

```bash
# 1. Build y push de imagen
make build-prod
make push-prod

# 2. Actualizar ECS service
aws ecs update-service \
  --cluster {YOUR_APP}-production-cluster \
  --service {YOUR_APP}-backend-production \
  --force-new-deployment

# 3. Deploy frontend
cd frontend
npm run build:production
aws s3 sync dist/ s3://{YOUR_DOMAIN}-frontend-production/
```

## 🛠 Troubleshooting

### Problemas Comunes

**Error de conexión a base de datos**

```bash
# Verificar security groups
aws ec2 describe-security-groups --group-ids sg-xxxxx

# Test de conectividad
mysql -h {YOUR_APP}-production-db.xxxxx.us-east-1.rds.amazonaws.com -u admin -p
```

**ECS tasks no inician**

```bash
# Revisar logs de la tarea
aws ecs describe-tasks --cluster {YOUR_APP}-production-cluster --tasks arn:aws:ecs:...

# Verificar task definition
aws ecs describe-task-definition --task-definition {YOUR_APP}-backend-production
```

**Frontend no carga**

```bash
# Verificar bucket policy
aws s3api get-bucket-policy --bucket {YOUR_DOMAIN}-frontend-production

# Verificar distribución CloudFront
aws cloudfront get-distribution --id {PRODUCTION_DISTRIBUTION_ID}
```

## 🤝 Contribución

1. Fork el repositorio
2. Crea una rama: `git checkout -b feature/nueva-funcionalidad`
3. Commit cambios: `git commit -m 'feat: agrega nueva funcionalidad'`
4. Push: `git push origin feature/nueva-funcionalidad`
5. Abre un Pull Request

## 📄 Licencia

MIT License - ver [LICENSE](LICENSE) para detalles.
