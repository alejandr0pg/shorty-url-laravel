# Configuración de GitHub Secrets

## Secrets Requeridos

Para que el deployment funcione correctamente, necesitas configurar estos secrets en GitHub:

### 1. Secrets de AWS
```bash
AWS_ACCESS_KEY_ID=your-aws-access-key
AWS_SECRET_ACCESS_KEY=your-aws-secret-key
AWS_ACCOUNT_ID=109995068952
```

### 2. Secrets de Base de Datos (Production)
```bash
DB_HOST=your-rds-endpoint.rds.amazonaws.com
DB_DATABASE=shrt_production
DB_USERNAME=admin
DB_PASSWORD=your-rds-password
```

### 3. Secrets de Redis (Production)
```bash
REDIS_HOST=your-redis-endpoint.cache.amazonaws.com
REDIS_PASSWORD=null
```

### 4. Secrets de Aplicación
```bash
APP_KEY=base64:your-generated-app-key
APP_URL=https://api.your-domain.com
```

### 5. Secrets de AWS S3
```bash
AWS_BUCKET=shrt-storage
```

### 6. Secrets de ALB/Target Groups
```bash
TARGET_GROUP_ARN=arn:aws:elasticloadbalancing:us-east-1:109995068952:targetgroup/your-target-group/xxxxx
ALB_DNS_NAME=your-alb-dns-name.us-east-1.elb.amazonaws.com
```

## Secrets Opcionales para Staging

Si quieres usar diferentes recursos para staging:

```bash
STAGING_APP_URL=https://staging-api.your-domain.com
STAGING_DB_HOST=your-staging-rds-endpoint.rds.amazonaws.com
STAGING_DB_DATABASE=shrt_staging
STAGING_DB_USERNAME=admin
STAGING_DB_PASSWORD=your-staging-rds-password
STAGING_REDIS_HOST=your-staging-redis-endpoint.cache.amazonaws.com
STAGING_AWS_BUCKET=shrt-storage-staging
```

## Cómo configurar los secrets

1. Ve a tu repositorio en GitHub
2. Settings > Secrets and variables > Actions
3. Click "New repository secret"
4. Agrega cada secret con su valor correspondiente

## Generar APP_KEY

Para generar un APP_KEY válido:

```bash
php artisan key:generate --show
```

El resultado debe tener el formato: `base64:...`

## Verificar configuración

Una vez configurados todos los secrets, puedes hacer un push a `main` o `develop` para probar el deployment.