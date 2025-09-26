# 🎯 Configuración Visual de GitHub Secrets

## Paso 1: Navegar a Settings

1. Ve a tu repositorio en GitHub
2. Haz clic en la pestaña **"Settings"**

```
[Tu Repositorio] → Settings
```

## Paso 2: Acceder a Secrets

1. En el menú lateral izquierdo, busca la sección **"Security"**
2. Haz clic en **"Secrets and variables"**
3. Selecciona **"Actions"**

```
Settings → Secrets and variables → Actions
```

## Paso 3: Agregar Secrets

### 3.1 Agregar AWS_ACCESS_KEY_ID

1. Haz clic en el botón **"New repository secret"**
2. En el campo **"Name"** escribe: `AWS_ACCESS_KEY_ID`
3. En el campo **"Secret"** pega tu Access Key ID de AWS
4. Haz clic en **"Add secret"**

```
Name: AWS_ACCESS_KEY_ID
Secret: AKIA... (tu access key real)
```

### 3.2 Agregar AWS_SECRET_ACCESS_KEY

1. Haz clic en **"New repository secret"** nuevamente
2. En el campo **"Name"** escribe: `AWS_SECRET_ACCESS_KEY`
3. En el campo **"Secret"** pega tu Secret Access Key de AWS
4. Haz clic en **"Add secret"**

```
Name: AWS_SECRET_ACCESS_KEY
Secret: [tu secret access key real - muy largo]
```

### 3.3 Agregar AWS_ACCOUNT_ID

1. **"New repository secret"**
2. Name: `AWS_ACCOUNT_ID`
3. Secret: `109995068952`
4. **"Add secret"**

### 3.4 Agregar CloudFront Distribution IDs

1. **"New repository secret"**
2. Name: `CLOUDFRONT_DISTRIBUTION_STAGING`
3. Secret: `EMGOF3DHVN1IP`
4. **"Add secret"**

1. **"New repository secret"**
2. Name: `CLOUDFRONT_DISTRIBUTION_PRODUCTION`
3. Secret: `E2IC4GJDJKZPKW`
4. **"Add secret"**

## Paso 4: Verificar Lista Final

Después de agregar todos los secrets, tu lista debería verse así:

```
Repository secrets (5)

AWS_ACCESS_KEY_ID                    ✅ Updated now
AWS_ACCOUNT_ID                       ✅ Updated now
AWS_SECRET_ACCESS_KEY               ✅ Updated now
CLOUDFRONT_DISTRIBUTION_PRODUCTION   ✅ Updated now
CLOUDFRONT_DISTRIBUTION_STAGING      ✅ Updated now
```

## 🚨 Importantes Consideraciones de Seguridad

### ❌ NUNCA hagas esto:
- No compartas tus AWS credentials en chat/email
- No los pongas en código fuente
- No los subas a git

### ✅ SÍ haz esto:
- Usa solo GitHub Secrets para credentials
- Mantén tus keys rotadas regularmente
- Usa permisos mínimos necesarios en AWS IAM

## 📧 ¿Cómo Obtener AWS Credentials?

### Método 1: AWS Console
1. Ve a [AWS Console](https://console.aws.amazon.com)
2. IAM → Users → [tu usuario]
3. Security credentials
4. Create access key
5. Selecciona "Command Line Interface (CLI)"
6. Copia ambas keys

### Método 2: AWS CLI (si ya tienes configurado)
```bash
# Ver tus credentials actuales (SIN mostrar secrets)
aws configure list

# Ver tu Account ID
aws sts get-caller-identity --query Account --output text
```

## 🔍 Validar Configuración

### Test 1: Validar AWS Credentials
```bash
# Ejecutar desde tu máquina local
aws sts get-caller-identity
```

Debería retornar:
```json
{
    "UserId": "AIDA...",
    "Account": "109995068952",
    "Arn": "arn:aws:iam::109995068952:user/tu-usuario"
}
```

### Test 2: Probar Deployment
1. Haz un cambio mínimo en el código
2. Commit y push a branch `develop`
3. Ve a **Actions** en GitHub
4. Observa que el workflow se ejecute sin errores

## 🎉 ¡Listo!

Una vez configurado, cada push disparará automáticamente:

- **Branch `develop`** → Deploy a Staging
- **Branch `main`** → Deploy a Production

Las URLs de tu aplicación serán:

- **Staging:** https://d2570b9eh3h8yc.cloudfront.net
- **Production:** https://daaedpb6kov3c.cloudfront.net