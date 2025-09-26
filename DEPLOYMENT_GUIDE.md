# 🚀 Guía de Configuración de Dominio y Despliegue AWS

Esta guía te ayudará a configurar los dominios y servicios de AWS necesarios para desplegar el proyecto Shrt URL Shortener.

## 📋 Tabla de Contenidos

- [Arquitectura del Sistema](#arquitectura-del-sistema)
- [Configuración de Dominios](#configuración-de-dominios)
- [Configuración de AWS](#configuración-de-aws)
- [Configuración de GitHub Secrets](#configuración-de-github-secrets)
- [Variables de Entorno](#variables-de-entorno)
- [Despliegue Inicial](#despliegue-inicial)

## 🏗 Arquitectura del Sistema

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   CloudFront    │    │       S3        │    │      ECS        │
│   (Frontend)    │───▶│   (Assets)      │    │   (Backend)     │
│                 │    │                 │    │                 │
│ tu-dominio.com  │    │ Static Files    │    │ Laravel API     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                              ┌─────────────────┐
                                              │      RDS        │
                                              │   (Database)    │
                                              └─────────────────┘
```

## 🌐 Configuración de Dominios

### 1. Adquiere tu Dominio

Puedes usar servicios como:
- **AWS Route 53** (recomendado para integración completa)
- **Namecheap**
- **GoDaddy**
- **Cloudflare**

### 2. Planifica la Estructura de Subdominios

```
Producción:
├── tu-dominio.com (Frontend)
└── api.tu-dominio.com (Backend API)

Staging:
├── staging.tu-dominio.com (Frontend)
└── staging-api.tu-dominio.com (Backend API)
```

### 3. Actualiza los Archivos de Configuración

Una vez tengas tu dominio, actualiza estos archivos:

**Frontend Staging** (`/Users/alejandroperez/spot2/frontend/.env.staging`):
```env
VITE_API_URL=https://staging-api.TU-DOMINIO.com
VITE_CLOUDFRONT_DOMAIN=staging.TU-DOMINIO.com
```

**Frontend Production** (`/Users/alejandroperez/spot2/frontend/.env.production`):
```env
VITE_API_URL=https://api.TU-DOMINIO.com
VITE_CLOUDFRONT_DOMAIN=TU-DOMINIO.com
```

**GitHub Actions Frontend** (`/Users/alejandroperez/spot2/frontend/.github/workflows/ci.yml`):
```yaml
# Línea 102-103 y 105-106
api_url: https://staging-api.TU-DOMINIO.com
api_url: https://api.TU-DOMINIO.com

# Environment URLs
url: https://staging.TU-DOMINIO.com
url: https://TU-DOMINIO.com
```

## ☁️ Configuración de AWS

### 1. Servicios AWS Necesarios

- **ECR** (Elastic Container Registry) - Para imágenes Docker
- **ECS** (Elastic Container Service) - Para ejecutar el backend
- **RDS** (Relational Database Service) - Base de datos MySQL
- **ElastiCache** - Redis para cache
- **S3** - Almacenamiento de archivos estáticos
- **CloudFront** - CDN para el frontend
- **Route 53** - DNS (si compraste el dominio en AWS)
- **Application Load Balancer** - Para el backend
- **VPC** - Red privada virtual

### 2. Configuración Paso a Paso (Opción Fácil)

#### Opción A: Setup Automático con Makefile

```bash
# 1. Configurar AWS CLI primero
aws configure

# 2. Ejecutar setup automático (creará ECR, ECS, S3)
make aws-setup

# 3. Verificar que todo se creó correctamente
make aws-status

# 4. Ver logs de servicios cuando estén desplegados
make aws-logs
```

#### Opción B: Setup Manual Paso a Paso

#### A. Crear Repositorio ECR

```bash
# Crear repositorio para el backend
aws ecr create-repository \
  --repository-name shrt-backend \
  --region us-east-1

# Obtener el URI del repositorio (guarda este valor)
aws ecr describe-repositories \
  --repository-names shrt-backend \
  --region us-east-1 \
  --query 'repositories[0].repositoryUri' \
  --output text
```

#### B. Crear Cluster ECS

```bash
# Crear cluster
aws ecs create-cluster \
  --cluster-name shrt-staging-cluster \
  --region us-east-1

aws ecs create-cluster \
  --cluster-name shrt-production-cluster \
  --region us-east-1
```

#### C. Configurar RDS

```bash
# Crear subnet group
aws rds create-db-subnet-group \
  --db-subnet-group-name shrt-subnet-group \
  --db-subnet-group-description "Subnet group for Shrt database" \
  --subnet-ids subnet-12345678 subnet-87654321

# Crear instancia RDS
aws rds create-db-instance \
  --db-instance-identifier shrt-production \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0 \
  --allocated-storage 20 \
  --db-name shrt \
  --master-username admin \
  --master-user-password TU-PASSWORD-SEGURO \
  --db-subnet-group-name shrt-subnet-group \
  --vpc-security-group-ids sg-12345678
```

#### D. Crear Buckets S3

```bash
# Crear buckets para el frontend
aws s3 mb s3://TU-DOMINIO-frontend-staging --region us-east-1
aws s3 mb s3://TU-DOMINIO-frontend-production --region us-east-1

# Crear bucket para backups
aws s3 mb s3://TU-DOMINIO-backups --region us-east-1

# Configurar hosting estático
aws s3 website s3://TU-DOMINIO-frontend-production \
  --index-document index.html \
  --error-document index.html
```

#### E. Configurar CloudFront

1. Crear distribución para producción:
```bash
aws cloudfront create-distribution \
  --distribution-config '{
    "CallerReference": "shrt-production-'$(date +%s)'",
    "Comment": "Shrt Production Frontend",
    "Origins": {
      "Quantity": 1,
      "Items": [
        {
          "Id": "S3-TU-DOMINIO-frontend-production",
          "DomainName": "TU-DOMINIO-frontend-production.s3.us-east-1.amazonaws.com",
          "S3OriginConfig": {
            "OriginAccessIdentity": ""
          }
        }
      ]
    },
    "DefaultCacheBehavior": {
      "TargetOriginId": "S3-TU-DOMINIO-frontend-production",
      "ViewerProtocolPolicy": "redirect-to-https",
      "TrustedSigners": {
        "Enabled": false,
        "Quantity": 0
      },
      "ForwardedValues": {
        "QueryString": false,
        "Cookies": {"Forward": "none"}
      }
    },
    "Enabled": true
  }'
```

### 3. Configurar DNS en Route 53

```bash
# Crear zona hospedada
aws route53 create-hosted-zone \
  --name TU-DOMINIO.com \
  --caller-reference $(date +%s)

# Añadir registros A para apuntar a CloudFront
aws route53 change-resource-record-sets \
  --hosted-zone-id Z123456789 \
  --change-batch '{
    "Changes": [
      {
        "Action": "CREATE",
        "ResourceRecordSet": {
          "Name": "TU-DOMINIO.com",
          "Type": "A",
          "AliasTarget": {
            "DNSName": "d123456789.cloudfront.net",
            "EvaluateTargetHealth": false,
            "HostedZoneId": "Z2FDTNDATAQYW2"
          }
        }
      }
    ]
  }'
```

## 🔐 Configuración de GitHub Secrets

Ve a tu repositorio en GitHub → Settings → Secrets and variables → Actions, y añade:

### Secretos Obligatorios

```
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_ACCOUNT_ID=123456789012
```

### Secretos para Dominios

```
STAGING_DOMAIN=staging-api.TU-DOMINIO.com
PRODUCTION_DOMAIN=api.TU-DOMINIO.com
CLOUDFRONT_DISTRIBUTION_STAGING=E123456789
CLOUDFRONT_DISTRIBUTION_PRODUCTION=E987654321
```

### Secretos para Seguridad (Opcionales)

```
SONAR_TOKEN=...
SEMGREP_APP_TOKEN=...
SNYK_TOKEN=...
GITLEAKS_LICENSE=...
CHECKOV_API_KEY=...
```

### Secretos para Notificaciones (Opcionales)

```
SLACK_WEBHOOK_URL=https://hooks.slack.com/...
SECURITY_WEBHOOK_URL=https://...
```

## 🔧 Variables de Entorno para ECS

Crea estas variables en tus Task Definitions de ECS:

### Variables del Backend

```json
{
  "environment": [
    {
      "name": "APP_NAME",
      "value": "Shrt"
    },
    {
      "name": "APP_ENV",
      "value": "production"
    },
    {
      "name": "APP_URL",
      "value": "https://api.TU-DOMINIO.com"
    },
    {
      "name": "DB_CONNECTION",
      "value": "mysql"
    },
    {
      "name": "DB_HOST",
      "value": "shrt-production.xxxxx.us-east-1.rds.amazonaws.com"
    },
    {
      "name": "REDIS_HOST",
      "value": "shrt-redis.xxxxx.cache.amazonaws.com"
    }
  ],
  "secrets": [
    {
      "name": "APP_KEY",
      "valueFrom": "arn:aws:secretsmanager:us-east-1:123456789012:secret:shrt/app-key"
    },
    {
      "name": "DB_PASSWORD",
      "valueFrom": "arn:aws:secretsmanager:us-east-1:123456789012:secret:shrt/db-password"
    }
  ]
}
```

## 🚀 Despliegue Inicial

### 1. Primer Despliegue

```bash
# 1. Hacer push a la rama develop para staging
git checkout develop
git add .
git commit -m "feat: configuración inicial para staging"
git push origin develop

# 2. Hacer push a main para production (después de validar staging)
git checkout main
git merge develop
git push origin main
```

### 2. Verificar Despliegue

Los workflows automáticamente:

1. **Ejecutarán tests** de calidad y seguridad
2. **Construirán** las imágenes Docker
3. **Desplegarán** a staging/production según la rama
4. **Ejecutarán** health checks
5. **Generarán** reportes de performance

### 3. Monitoreo Post-Despliegue

```bash
# Verificar servicios ECS
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-backend-production

# Verificar distribución CloudFront
aws cloudfront get-distribution \
  --id E987654321

# Verificar logs
aws logs describe-log-groups \
  --log-group-name-prefix /ecs/shrt
```

## 🔧 Troubleshooting

### Problemas Comunes

**1. Error de SSL/TLS en CloudFront**
```bash
# Verificar certificado SSL
aws acm list-certificates --region us-east-1
```

**2. Error 403 en S3**
```bash
# Verificar política del bucket
aws s3api get-bucket-policy --bucket TU-DOMINIO-frontend-production
```

**3. Tareas ECS no inician**
```bash
# Revisar logs del cluster
aws ecs describe-tasks \
  --cluster shrt-production-cluster \
  --tasks TASK-ARN
```

**4. Error de conexión a base de datos**
```bash
# Verificar security groups y subnets
aws rds describe-db-instances \
  --db-instance-identifier shrt-production
```

## 📞 Soporte

Si encuentras problemas:

1. **Revisa los logs** de GitHub Actions
2. **Consulta los logs** de AWS CloudWatch
3. **Verifica la configuración** de variables de entorno
4. **Valida** que todos los servicios estén en la misma VPC

---

**Nota**: Recuerda reemplazar `TU-DOMINIO` con tu dominio real en todos los archivos mencionados.