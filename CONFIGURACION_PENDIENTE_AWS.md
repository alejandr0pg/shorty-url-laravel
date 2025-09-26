# 🔄 Configuración Pendiente de AWS

## ⚠️ Servicios que Faltan por Configurar

Para completar la infraestructura de producción, necesitas configurar estos servicios de AWS:

### 1. 🗄️ Base de Datos RDS (MySQL)
### 2. ⚡ Cache Redis (ElastiCache)
### 3. 🔐 Application Load Balancer
### 4. 🌐 Route 53 (cuando tengas dominio)

---

## 📋 Paso 1: Configurar RDS MySQL

### 1.1 Crear Subnet Group
```bash
# Obtener subnet IDs de tu VPC
aws ec2 describe-subnets --query 'Subnets[?MapPublicIpOnLaunch==`false`].{SubnetId:SubnetId,AZ:AvailabilityZone}' --output table

# Crear DB subnet group (reemplaza subnet-xxxxx con tus IDs reales)
aws rds create-db-subnet-group \
  --db-subnet-group-name shrt-db-subnet \
  --db-subnet-group-description "Subnet group for Shrt database" \
  --subnet-ids subnet-xxxxx subnet-yyyyy
```

### 1.2 Crear Security Group para RDS
```bash
# Obtener VPC ID
VPC_ID=$(aws ec2 describe-vpcs --query 'Vpcs[0].VpcId' --output text)

# Crear security group para RDS
aws ec2 create-security-group \
  --group-name shrt-rds-sg \
  --description "Security group for Shrt RDS" \
  --vpc-id $VPC_ID

# Obtener el Security Group ID
SG_ID=$(aws ec2 describe-security-groups --group-names shrt-rds-sg --query 'SecurityGroups[0].GroupId' --output text)

# Permitir conexiones MySQL desde ECS
aws ec2 authorize-security-group-ingress \
  --group-id $SG_ID \
  --protocol tcp \
  --port 3306 \
  --source-group $ECS_SG_ID  # Necesitas el SG ID de ECS
```

### 1.3 Crear Instancia RDS
```bash
# Generar password seguro
DB_PASSWORD=$(openssl rand -base64 32)
echo "Guarda este password: $DB_PASSWORD"

# Crear instancia RDS
aws rds create-db-instance \
  --db-instance-identifier shrt-production-db \
  --db-instance-class db.t3.micro \
  --engine mysql \
  --engine-version 8.0 \
  --allocated-storage 20 \
  --storage-type gp2 \
  --db-name shrt \
  --master-username admin \
  --master-user-password $DB_PASSWORD \
  --db-subnet-group-name shrt-db-subnet \
  --vpc-security-group-ids $SG_ID \
  --backup-retention-period 7 \
  --multi-az \
  --storage-encrypted
```

### 1.4 Obtener Endpoint RDS
```bash
# Obtener endpoint (espera 10-15 minutos)
aws rds describe-db-instances \
  --db-instance-identifier shrt-production-db \
  --query 'DBInstances[0].Endpoint.Address' \
  --output text
```

---

## 📋 Paso 2: Configurar ElastiCache Redis

### 2.1 Crear Subnet Group para Redis
```bash
aws elasticache create-cache-subnet-group \
  --cache-subnet-group-name shrt-redis-subnet \
  --cache-subnet-group-description "Subnet group for Shrt Redis" \
  --subnet-ids subnet-xxxxx subnet-yyyyy
```

### 2.2 Crear Security Group para Redis
```bash
# Crear security group para Redis
aws ec2 create-security-group \
  --group-name shrt-redis-sg \
  --description "Security group for Shrt Redis" \
  --vpc-id $VPC_ID

REDIS_SG_ID=$(aws ec2 describe-security-groups --group-names shrt-redis-sg --query 'SecurityGroups[0].GroupId' --output text)

# Permitir conexiones Redis desde ECS
aws ec2 authorize-security-group-ingress \
  --group-id $REDIS_SG_ID \
  --protocol tcp \
  --port 6379 \
  --source-group $ECS_SG_ID
```

### 2.3 Crear Cluster Redis
```bash
aws elasticache create-replication-group \
  --replication-group-id shrt-production-redis \
  --description "Redis cluster for Shrt production" \
  --num-cache-clusters 2 \
  --cache-node-type cache.t3.micro \
  --engine redis \
  --engine-version 7.0 \
  --cache-subnet-group-name shrt-redis-subnet \
  --security-group-ids $REDIS_SG_ID \
  --at-rest-encryption-enabled \
  --transit-encryption-enabled
```

### 2.4 Obtener Endpoint Redis
```bash
aws elasticache describe-replication-groups \
  --replication-group-id shrt-production-redis \
  --query 'ReplicationGroups[0].RedisEndpoint.Address' \
  --output text
```

---

## 📋 Paso 3: Application Load Balancer

### 3.1 Crear ALB
```bash
# Obtener subnet IDs públicas
PUBLIC_SUBNETS=$(aws ec2 describe-subnets --query 'Subnets[?MapPublicIpOnLaunch==`true`].SubnetId' --output text)

# Crear ALB
aws elbv2 create-load-balancer \
  --name shrt-production-alb \
  --subnets $PUBLIC_SUBNETS \
  --security-groups $ALB_SG_ID \  # Necesitas crear SG para ALB
  --scheme internet-facing \
  --type application \
  --ip-address-type ipv4
```

---

## 🔐 Paso 4: Actualizar GitHub Secrets

Una vez creados RDS y Redis, necesitas agregar estos secrets:

```bash
# En GitHub Settings → Secrets and variables → Actions:

# Database
DB_HOST=[RDS_ENDPOINT]              # ej: shrt-production-db.xxxxx.us-east-1.rds.amazonaws.com
DB_DATABASE=shrt
DB_USERNAME=admin
DB_PASSWORD=[PASSWORD_GENERADO]

# Redis
REDIS_HOST=[REDIS_ENDPOINT]         # ej: shrt-production-redis.xxxxx.cache.amazonaws.com
REDIS_PORT=6379
REDIS_AUTH_TOKEN=[TOKEN_SI_TIENES]

# Load Balancer
ALB_TARGET_GROUP_ARN=[TARGET_GROUP_ARN]
```

---

## 🎯 Comandos de Verificación

### Verificar RDS
```bash
# Test conexión
mysql -h [RDS_ENDPOINT] -u admin -p[PASSWORD] shrt
```

### Verificar Redis
```bash
# Necesitarás redis-cli en ECS task o EC2 instance
redis-cli -h [REDIS_ENDPOINT] -p 6379 ping
```

### Verificar Estado General
```bash
# RDS Status
aws rds describe-db-instances --db-instance-identifier shrt-production-db --query 'DBInstances[0].DBInstanceStatus'

# Redis Status
aws elasticache describe-replication-groups --replication-group-id shrt-production-redis --query 'ReplicationGroups[0].Status'
```

---

## 📅 Orden Recomendado

1. ✅ **COMPLETADO**: ECR, ECS, S3, CloudFront
2. 🔄 **SIGUIENTE**: RDS MySQL (necesario para que la app funcione)
3. 🔄 **DESPUÉS**: ElastiCache Redis (performance)
4. 🔄 **FINAL**: ALB y dominio personalizado

---

## 💡 Alternativas Rápidas para Testing

Si quieres probar la app rápidamente sin configurar RDS/Redis:

### Usar RDS Serverless (más simple)
```bash
# Crear cluster Aurora Serverless
aws rds create-db-cluster \
  --db-cluster-identifier shrt-serverless \
  --engine aurora-mysql \
  --engine-mode serverless \
  --master-username admin \
  --master-user-password $DB_PASSWORD \
  --database-name shrt
```

### Usar SQLite temporal (solo para testing)
```bash
# Modificar .env para usar SQLite
DB_CONNECTION=sqlite
DB_DATABASE=/tmp/shrt.sqlite
```

## ❓ ¿Necesitas Ayuda?

Si encuentras errores o necesitas ayuda configurando estos servicios, revisa:

1. **Logs de CloudFormation** si usas templates
2. **Logs de ECS** para errores de aplicación
3. **Security Groups** - la mayoría de errores son de conectividad
4. **IAM Permissions** para el usuario que ejecuta los comandos