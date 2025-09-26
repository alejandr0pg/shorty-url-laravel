# 📋 Comandos Específicos AWS para Shorty URL

## 🚀 Quick Start (Recomendado)

```bash
# 1. Configurar AWS CLI
aws configure

# 2. Setup automático de infraestructura básica
make aws-setup

# 3. Verificar que todo se creó correctamente
make aws-status
```

---

## ☁️ Comandos Detallados por Servicio

### 1. ECR (Container Registry)

```bash
# Crear repositorio
aws ecr create-repository --repository-name shrt-backend --region us-east-1

# Obtener URI del repositorio (guarda este valor)
ECR_URI=$(aws ecr describe-repositories --repository-names shrt-backend --query 'repositories[0].repositoryUri' --output text)
echo "ECR URI: $ECR_URI"

# Login a ECR (necesario antes de hacer push)
aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin $ECR_URI
```

### 2. ECS (Container Service)

```bash
# Crear clusters
aws ecs create-cluster \
  --cluster-name shrt-staging-cluster \
  --capacity-providers FARGATE \
  --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1

aws ecs create-cluster \
  --cluster-name shrt-production-cluster \
  --capacity-providers FARGATE \
  --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1

# Verificar clusters
aws ecs describe-clusters \
  --clusters shrt-staging-cluster shrt-production-cluster \
  --query 'clusters[].{Name:clusterName,Status:status}' \
  --output table
```

### 3. RDS (Base de Datos)

```bash
# Crear subnet group (necesario para RDS)
aws rds create-db-subnet-group \
  --db-subnet-group-name shrt-db-subnet \
  --db-subnet-group-description "Subnet group for Shrt database" \
  --subnet-ids subnet-12345678 subnet-87654321  # Reemplazar con tus subnet IDs

# Crear instancia MySQL
aws rds create-db-instance \
  --db-instance-identifier shrt-production-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0.35 \
  --allocated-storage 20 \
  --storage-type gp2 \
  --db-name shrt \
  --master-username admin \
  --master-user-password $(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25) \
  --db-subnet-group-name shrt-db-subnet \
  --vpc-security-group-ids sg-12345678 \
  --backup-retention-period 7 \
  --storage-encrypted \
  --no-multi-az

# Obtener endpoint de la base de datos
aws rds describe-db-instances \
  --db-instance-identifier shrt-production-db \
  --query 'DBInstances[0].Endpoint.Address' \
  --output text
```

### 4. ElastiCache (Redis)

```bash
# Crear subnet group para Redis
aws elasticache create-cache-subnet-group \
  --cache-subnet-group-name shrt-redis-subnet \
  --cache-subnet-group-description "Subnet group for Shrt Redis" \
  --subnet-ids subnet-12345678 subnet-87654321

# Crear cluster Redis
aws elasticache create-replication-group \
  --replication-group-id shrt-production-redis \
  --description "Redis cluster for Shrt production" \
  --num-cache-clusters 1 \
  --cache-node-type cache.t3.micro \
  --engine redis \
  --engine-version 7.0 \
  --cache-subnet-group-name shrt-redis-subnet \
  --security-group-ids sg-12345678 \
  --at-rest-encryption-enabled \
  --transit-encryption-enabled

# Obtener endpoint de Redis
aws elasticache describe-replication-groups \
  --replication-group-id shrt-production-redis \
  --query 'ReplicationGroups[0].RedisEndpoint.Address' \
  --output text
```

### 5. S3 (Frontend Hosting)

```bash
# Crear buckets
aws s3 mb s3://tu-dominio-frontend-staging --region us-east-1
aws s3 mb s3://tu-dominio-frontend-production --region us-east-1
aws s3 mb s3://tu-dominio-backups --region us-east-1

# Configurar hosting estático
aws s3 website s3://tu-dominio-frontend-production \
  --index-document index.html \
  --error-document index.html

# Configurar política de bucket (público)
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

# Configurar CORS
aws s3api put-bucket-cors \
  --bucket tu-dominio-frontend-production \
  --cors-configuration '{
    "CORSRules": [
      {
        "AllowedOrigins": ["*"],
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "HEAD"],
        "MaxAgeSeconds": 3000
      }
    ]
  }'
```

### 6. CloudFront (CDN)

```bash
# Crear distribución para producción
aws cloudfront create-distribution \
  --distribution-config '{
    "CallerReference": "shrt-prod-'$(date +%s)'",
    "Comment": "Shrt Production CDN",
    "Origins": {
      "Quantity": 1,
      "Items": [
        {
          "Id": "S3-tu-dominio-frontend-production",
          "DomainName": "tu-dominio-frontend-production.s3-website-us-east-1.amazonaws.com",
          "CustomOriginConfig": {
            "HTTPPort": 80,
            "HTTPSPort": 443,
            "OriginProtocolPolicy": "http-only"
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
      "MinTTL": 0,
      "DefaultTTL": 86400,
      "MaxTTL": 31536000,
      "Compress": true
    },
    "CustomErrorPages": {
      "Quantity": 1,
      "Items": [
        {
          "ErrorCode": 404,
          "ResponsePagePath": "/index.html",
          "ResponseCode": "200",
          "ErrorCachingMinTTL": 300
        }
      ]
    },
    "Enabled": true,
    "PriceClass": "PriceClass_100"
  }'

# Obtener ID y dominio de la distribución
aws cloudfront list-distributions \
  --query 'DistributionList.Items[?Comment==`Shrt Production CDN`].{Id:Id,DomainName:DomainName}' \
  --output table
```

### 7. Application Load Balancer

```bash
# Crear ALB para el backend
aws elbv2 create-load-balancer \
  --name shrt-production-alb \
  --subnets subnet-12345678 subnet-87654321 \
  --security-groups sg-12345678 \
  --scheme internet-facing \
  --type application \
  --ip-address-type ipv4

# Crear target group
aws elbv2 create-target-group \
  --name shrt-backend-targets \
  --protocol HTTP \
  --port 80 \
  --vpc-id vpc-12345678 \
  --target-type ip \
  --health-check-path /health \
  --health-check-protocol HTTP

# Obtener ARN del ALB
aws elbv2 describe-load-balancers \
  --names shrt-production-alb \
  --query 'LoadBalancers[0].LoadBalancerArn' \
  --output text
```

### 8. Route 53 (DNS)

```bash
# Crear zona hospedada
aws route53 create-hosted-zone \
  --name tu-dominio.com \
  --caller-reference $(date +%s) \
  --hosted-zone-config Comment="Shrt URL Shortener DNS zone"

# Crear registro A para el frontend (apuntando a CloudFront)
aws route53 change-resource-record-sets \
  --hosted-zone-id Z123456789ABCDEF \
  --change-batch '{
    "Changes": [
      {
        "Action": "CREATE",
        "ResourceRecordSet": {
          "Name": "tu-dominio.com",
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

# Crear registro A para el backend API (apuntando a ALB)
aws route53 change-resource-record-sets \
  --hosted-zone-id Z123456789ABCDEF \
  --change-batch '{
    "Changes": [
      {
        "Action": "CREATE",
        "ResourceRecordSet": {
          "Name": "api.tu-dominio.com",
          "Type": "A",
          "AliasTarget": {
            "DNSName": "shrt-production-alb-123456789.us-east-1.elb.amazonaws.com",
            "EvaluateTargetHealth": true,
            "HostedZoneId": "Z35SXDOTRQ7X7K"
          }
        }
      }
    ]
  }'
```

---

## 🔐 Variables de Entorno Necesarias

### Para GitHub Secrets

```bash
# Obligatorios
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=wJal...
AWS_ACCOUNT_ID=123456789012

# URLs de dominio
STAGING_DOMAIN=staging-api.tu-dominio.com
PRODUCTION_DOMAIN=api.tu-dominio.com

# IDs de CloudFront (obtén estos después de crear las distribuciones)
CLOUDFRONT_DISTRIBUTION_STAGING=E123ABC456DEF
CLOUDFRONT_DISTRIBUTION_PRODUCTION=E789GHI012JKL
```

### Para el archivo .env de Laravel

```bash
# Base de datos
DB_CONNECTION=mysql
DB_HOST=shrt-production-db.xyz123.us-east-1.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=shrt
DB_USERNAME=admin
DB_PASSWORD=tu-password-de-rds

# Redis
REDIS_HOST=shrt-production-redis.xyz123.cache.amazonaws.com
REDIS_PORT=6379
REDIS_PASSWORD=tu-redis-auth-token

# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tu-dominio.com
```

---

## 🧪 Comandos de Verificación

### Verificar que todo funciona

```bash
# 1. Verificar servicios AWS
make aws-status

# 2. Verificar conectividad de base de datos
mysql -h shrt-production-db.xyz123.us-east-1.rds.amazonaws.com -u admin -p

# 3. Verificar Redis
redis-cli -h shrt-production-redis.xyz123.cache.amazonaws.com ping

# 4. Verificar S3
aws s3 ls s3://tu-dominio-frontend-production

# 5. Verificar CloudFront
curl -I https://d123456789.cloudfront.net

# 6. Verificar ALB
curl -I http://shrt-production-alb-123456789.us-east-1.elb.amazonaws.com/health
```

---

## 📦 Comandos de Despliegue

### Build y Deploy del Backend

```bash
# 1. Build imagen Docker
make build-prod

# 2. Tag para ECR
make tag-prod

# 3. Push a ECR
make push-prod

# 4. Deploy a ECS
make deploy-prod

# O todo junto:
make deploy
```

### Deploy del Frontend

```bash
cd frontend

# 1. Build para producción
npm run build:production

# 2. Deploy a S3
aws s3 sync dist/ s3://tu-dominio-frontend-production/ --delete

# 3. Invalidar cache de CloudFront
aws cloudfront create-invalidation \
  --distribution-id E789GHI012JKL \
  --paths "/*"
```

---

## 🚨 Comandos de Emergencia

### Ver logs de problemas

```bash
# Logs de ECS
aws logs filter-log-events \
  --log-group-name /ecs/shrt-production \
  --start-time $(date -d '1 hour ago' +%s)000

# Status de servicios
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-backend-production

# Rollback a versión anterior
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-backend-production \
  --task-definition shrt-backend-production:PREVIOUS-REVISION
```

### Cleanup completo (PELIGROSO)

```bash
# CUIDADO: Esto eliminará TODA la infraestructura
make aws-cleanup
```

---

## 📝 Notas Importantes

1. **Reemplaza `tu-dominio` con tu dominio real** en todos los comandos
2. **Guarda los IDs y ARNs** que devuelven los comandos - los necesitarás
3. **Los subnet IDs y security group IDs** necesitan ser de tu VPC
4. **Configurar VPC primero** si no tienes una (o usar la default)
5. **Los precios** de AWS se cobran por uso - ten cuidado con lo que creas

**💡 Tip**: Ejecuta primero `make aws-setup` y luego configura manualmente RDS y ElastiCache para un setup más rápido.
