# 🔐 Guía Completa de Configuración de GitHub Secrets

Esta guía te enseñará paso a paso cómo configurar todos los secrets necesarios en GitHub para que el CI/CD funcione correctamente.

## 📍 Cómo Acceder a GitHub Secrets

1. Ve a tu repositorio en GitHub
2. Click en **Settings** (Configuración)
3. En el menú lateral izquierdo, busca **Secrets and variables**
4. Click en **Actions**
5. Click en **New repository secret**

## 🔑 Secrets Obligatorios

### 1. AWS Credentials

```bash
# Nombre: AWS_ACCESS_KEY_ID
# Valor: Tu Access Key ID de AWS (ej: AKIA...)
AWS_ACCESS_KEY_ID

# Nombre: AWS_SECRET_ACCESS_KEY
# Valor: Tu Secret Access Key de AWS
AWS_SECRET_ACCESS_KEY

# Nombre: AWS_ACCOUNT_ID
# Valor: 109995068952
AWS_ACCOUNT_ID
```

**¿Cómo obtener AWS Credentials?**
1. Ve a AWS Console → IAM
2. Click en "Users" → tu usuario
3. Click en "Security credentials"
4. Click en "Create access key"
5. Selecciona "Command Line Interface (CLI)"
6. Copia el Access Key ID y Secret Access Key

### 2. CloudFront Distribution IDs

```bash
# Nombre: CLOUDFRONT_DISTRIBUTION_STAGING
# Valor: EMGOF3DHVN1IP
CLOUDFRONT_DISTRIBUTION_STAGING

# Nombre: CLOUDFRONT_DISTRIBUTION_PRODUCTION
# Valor: E2IC4GJDJKZPKW
CLOUDFRONT_DISTRIBUTION_PRODUCTION
```

### 3. URLs de Dominio (Opcional por ahora)

```bash
# Nombre: STAGING_DOMAIN
# Valor: staging-api.tu-dominio.com (cuando tengas dominio)
STAGING_DOMAIN

# Nombre: PRODUCTION_DOMAIN
# Valor: api.tu-dominio.com (cuando tengas dominio)
PRODUCTION_DOMAIN
```

### 4. Base de Datos (Se configurará después)

```bash
# Nombre: DB_PASSWORD
# Valor: [Password seguro para RDS - se configurará con RDS]
DB_PASSWORD

# Nombre: REDIS_AUTH_TOKEN
# Valor: [Token para ElastiCache - se configurará con Redis]
REDIS_AUTH_TOKEN
```

## ✅ URLs Actuales del Frontend

Mientras configuras un dominio personalizado, puedes usar estas URLs:

- **Staging Frontend:** https://d2570b9eh3h8yc.cloudfront.net
- **Production Frontend:** https://daaedpb6kov3c.cloudfront.net

## 🎯 Configuración Paso a Paso

### Paso 1: Configurar AWS Credentials

1. **NEW REPOSITORY SECRET**
   - Name: `AWS_ACCESS_KEY_ID`
   - Secret: `[Tu Access Key]`

2. **NEW REPOSITORY SECRET**
   - Name: `AWS_SECRET_ACCESS_KEY`
   - Secret: `[Tu Secret Key]`

3. **NEW REPOSITORY SECRET**
   - Name: `AWS_ACCOUNT_ID`
   - Secret: `109995068952`

### Paso 2: Configurar CloudFront

1. **NEW REPOSITORY SECRET**
   - Name: `CLOUDFRONT_DISTRIBUTION_STAGING`
   - Secret: `EMGOF3DHVN1IP`

2. **NEW REPOSITORY SECRET**
   - Name: `CLOUDFRONT_DISTRIBUTION_PRODUCTION`
   - Secret: `E2IC4GJDJKZPKW`

### Paso 3: Verificar Configuración

Después de agregar los secrets, ve a:
- **Settings** → **Secrets and variables** → **Actions**

Deberías ver algo así:

```
Repository secrets:
✅ AWS_ACCESS_KEY_ID                    Updated X minutes ago
✅ AWS_SECRET_ACCESS_KEY               Updated X minutes ago
✅ AWS_ACCOUNT_ID                      Updated X minutes ago
✅ CLOUDFRONT_DISTRIBUTION_STAGING     Updated X minutes ago
✅ CLOUDFRONT_DISTRIBUTION_PRODUCTION  Updated X minutes ago
```

## 🚀 Probar el Deployment

Una vez configurados los secrets, puedes probar el deployment:

### Para el Backend:
```bash
# Push a develop para staging
git checkout develop
git add .
git commit -m "test: probar deployment staging"
git push origin develop

# Push a main para production
git checkout main
git merge develop
git push origin main
```

### Para el Frontend:
```bash
# El CI se ejecutará automáticamente en push a develop/main
# Revisa Actions → All workflows para ver el progreso
```

## 🔍 Monitoreo

### Ver Logs de GitHub Actions:
1. Ve a tu repositorio
2. Click en **Actions**
3. Selecciona el workflow que se está ejecutando
4. Click en el job para ver logs detallados

### Verificar Deployments:
```bash
# Backend ECS status
aws ecs describe-services \
  --cluster shrt-production-cluster \
  --services shrt-backend-production

# Frontend S3 status
aws s3 ls s3://tu-dominio-frontend-production/
```

## 🔧 Troubleshooting

### Error: "The security token included in the request is invalid"
- Verifica que AWS_ACCESS_KEY_ID y AWS_SECRET_ACCESS_KEY sean correctos
- Asegúrate de que las credenciales tengan permisos suficientes

### Error: "Distribution not found"
- Verifica que CLOUDFRONT_DISTRIBUTION_* IDs sean correctos
- Las distribuciones pueden tardar 15-20 minutos en estar listas

### Error: "Access Denied" en S3
- Verifica que el usuario AWS tenga permisos de S3
- Revisa las políticas IAM del usuario

## 📋 Checklist Final

- [ ] AWS_ACCESS_KEY_ID configurado
- [ ] AWS_SECRET_ACCESS_KEY configurado
- [ ] AWS_ACCOUNT_ID configurado (109995068952)
- [ ] CLOUDFRONT_DISTRIBUTION_STAGING configurado (EMGOF3DHVN1IP)
- [ ] CLOUDFRONT_DISTRIBUTION_PRODUCTION configurado (E2IC4GJDJKZPKW)
- [ ] Primer deployment de prueba exitoso
- [ ] Frontend accesible en URLs de CloudFront

¡Con esto ya tienes todo configurado para deployment automático! 🎉