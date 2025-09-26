# Shrt - URL Shortener

Un acortador de URLs desarrollado con Laravel (backend) y React (frontend), diseñado para ser escalable, seguro y fácil de usar con despliegue en AWS.

## 🚀 Características

- **Acortamiento de URLs**: Genera códigos únicos de hasta 8 caracteres fáciles de leer.
- **API RESTful**: Endpoints para crear, listar y redirigir URLs.
- **Interfaz React**: Frontend moderno con TailwindCSS.
- **Seguridad**: Validación de URLs según RFC 1738, rate limiting, protección contra inyección SQL.
- **Caché**: Uso de Redis para mejorar rendimiento en redirecciones.
- **Pruebas**: Cobertura completa con Pest (backend) y Jest (frontend).
- **CI/CD**: GitHub Actions para automatización de pruebas y despliegue a AWS.
- **Monitoreo**: Logging avanzado, health checks y métricas de performance.

## 📋 Requisitos

### Desarrollo Local
- PHP 8.2+
- Node.js 18+
- Composer
- Docker & Docker Compose
- Redis (opcional para desarrollo)

### Producción AWS
- Cuenta AWS activa
- AWS CLI configurado
- Dominio propio
- GitHub account para CI/CD

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
  --repository-name shrt-backend \
  --region us-east-1

# Obtener URI del repositorio (guarda este valor)
aws ecr describe-repositories \
  --repository-names shrt-backend \
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
  --db-instance-identifier shrt-production-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0 \
  --allocated-storage 20 \
  --storage-type gp2 \
  --db-name shrt \
  --master-username admin \
  --master-user-password $(openssl rand -base64 32) \
  --db-subnet-group-name shrt-db-subnet \
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
  --replication-group-id shrt-production-redis \
  --description "Redis cluster for Shrt production" \
  --num-cache-clusters 2 \
  --cache-node-type cache.t3.micro \
  --engine redis \
  --engine-version 7.0 \
  --cache-subnet-group-name shrt-redis-subnet \
  --security-group-ids sg-xxxxx \
  --at-rest-encryption-enabled \
  --transit-encryption-enabled
```

### Paso 5: Crear ECS Clusters

```bash
# Crear cluster de staging
aws ecs create-cluster \
  --cluster-name shrt-staging-cluster \
  --capacity-providers FARGATE \
  --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1

# Crear cluster de producción
aws ecs create-cluster \
  --cluster-name shrt-production-cluster \
  --capacity-providers FARGATE \
  --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1

# Crear Application Load Balancer
aws elbv2 create-load-balancer \
  --name shrt-production-alb \
  --subnets subnet-xxxxx subnet-yyyyy \
  --security-groups sg-xxxxx \
  --scheme internet-facing \
  --type application \
  --ip-address-type ipv4
```

### Paso 6: Configurar S3 para Frontend

```bash
# Crear buckets para frontend
aws s3 mb s3://tu-dominio-frontend-staging --region us-east-1
aws s3 mb s3://tu-dominio-frontend-production --region us-east-1
aws s3 mb s3://tu-dominio-backups --region us-east-1

# Configurar hosting estático
aws s3 website s3://tu-dominio-frontend-production \
  --index-document index.html \
  --error-document index.html

# Configurar política de bucket
aws s3api put-bucket-policy \
  --bucket tu-dominio-frontend-production \
  --policy '{
    "Version": "2012-10-17",
    "Statement": [
      {
        "Sid": "PublicReadGetObject",
        "Effect": "Allow",
        "Principal": "*",
        "Action": "s3:GetObject",
        "Resource": "arn:aws:s3:::tu-dominio-frontend-production/*"
      }
    ]
  }'
```

### Paso 7: Configurar CloudFront

```bash
# Crear distribución CloudFront para producción
aws cloudfront create-distribution \
  --distribution-config '{
    "CallerReference": "shrt-prod-'$(date +%s)'",
    "Comment": "Shrt Production CDN",
    "Origins": {
      "Quantity": 1,
      "Items": [
        {
          "Id": "S3-tu-dominio-frontend-production",
          "DomainName": "tu-dominio-frontend-production.s3.us-east-1.amazonaws.com",
          "S3OriginConfig": {
            "OriginAccessIdentity": ""
          }
        }
      ]
    },
    "DefaultCacheBehavior": {
      "TargetOriginId": "S3-tu-dominio-frontend-production",
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

Ve a tu repositorio GitHub → Settings → Secrets and variables → Actions:

### ✅ Infraestructura AWS Creada:
- **ECR Repository:** `109995068952.dkr.ecr.us-east-1.amazonaws.com/shrt-backend`
- **ECS Clusters:** `shrt-staging-cluster`, `shrt-production-cluster`
- **S3 Buckets:** `tu-dominio-frontend-staging`, `tu-dominio-frontend-production`, `tu-dominio-backups`
- **CloudFront URLs:**
  - Staging: https://d2570b9eh3h8yc.cloudfront.net
  - Production: https://daaedpb6kov3c.cloudfront.net

```bash
# Secretos obligatorios
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=wJal...
AWS_ACCOUNT_ID=109995068952

# URLs de dominio
STAGING_DOMAIN=staging-api.tu-dominio.com
PRODUCTION_DOMAIN=api.tu-dominio.com

# CloudFront Distribution IDs (URLs creadas)
CLOUDFRONT_DISTRIBUTION_STAGING=EMGOF3DHVN1IP  # https://d2570b9eh3h8yc.cloudfront.net
CLOUDFRONT_DISTRIBUTION_PRODUCTION=E2IC4GJDJKZPKW  # https://daaedpb6kov3c.cloudfront.net

# Base de datos (usar AWS Secrets Manager en producción)
DB_PASSWORD=tu-password-seguro
REDIS_AUTH_TOKEN=tu-redis-token
```

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
# ECS Services
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-backend-production

# RDS Status
aws rds describe-db-instances \
  --db-instance-identifier shrt-production-db

# Redis Status
aws elasticache describe-replication-groups \
  --replication-group-id shrt-production-redis
```

### Ver Logs

```bash
# ECS Logs
aws logs describe-log-groups --log-group-name-prefix /ecs/shrt

# Aplicación logs
aws logs filter-log-events \
  --log-group-name /ecs/shrt-production \
  --start-time $(date -d '1 hour ago' +%s)000
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
  --cluster shrt-production-cluster \
  --service shrt-backend-production \
  --force-new-deployment

# 3. Deploy frontend
cd frontend
npm run build:production
aws s3 sync dist/ s3://tu-dominio-frontend-production/
```

## 🛠 Troubleshooting

### Problemas Comunes

**Error de conexión a base de datos**
```bash
# Verificar security groups
aws ec2 describe-security-groups --group-ids sg-xxxxx

# Test de conectividad
mysql -h shrt-production-db.xxxxx.us-east-1.rds.amazonaws.com -u admin -p
```

**ECS tasks no inician**
```bash
# Revisar logs de la tarea
aws ecs describe-tasks --cluster shrt-production-cluster --tasks arn:aws:ecs:...

# Verificar task definition
aws ecs describe-task-definition --task-definition shrt-backend-production
```

**Frontend no carga**
```bash
# Verificar bucket policy
aws s3api get-bucket-policy --bucket tu-dominio-frontend-production

# Verificar distribución CloudFront
aws cloudfront get-distribution --id E789GHI012JKL
```

## 📚 Documentación Adicional

### 🔧 Configuración y Despliegue
- **[Configuración GitHub Secrets](GITHUB_SECRETS_SETUP.md)** - Guía completa para configurar CI/CD
- **[Guía Visual GitHub](CONFIGURACION_GITHUB_VISUAL.md)** - Screenshots paso a paso
- **[Servicios AWS Pendientes](CONFIGURACION_PENDIENTE_AWS.md)** - RDS, Redis y ALB

### 📖 Documentación Técnica
- [Deployment Guide](DEPLOYMENT_GUIDE.md) - Guía detallada de despliegue
- [API Documentation](docs/api.md) - Documentación de endpoints
- [Architecture Overview](docs/architecture.md) - Arquitectura del sistema

## 🤝 Contribución

1. Fork el repositorio
2. Crea una rama: `git checkout -b feature/nueva-funcionalidad`
3. Commit cambios: `git commit -m 'feat: agrega nueva funcionalidad'`
4. Push: `git push origin feature/nueva-funcionalidad`
5. Abre un Pull Request

## 📄 Licencia

MIT License - ver [LICENSE](LICENSE) para detalles.