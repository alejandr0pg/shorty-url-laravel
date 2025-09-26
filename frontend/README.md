# Shrt Frontend - React Application

Una aplicación React moderna para el servicio de acortamiento de URLs Shrt, construida con TypeScript, Vite y configurada para despliegue en AWS S3.

## 📋 Tabla de Contenidos

- [Requisitos Previos](#requisitos-previos)
- [Configuración Inicial](#configuración-inicial)
- [Desarrollo Local](#desarrollo-local)
- [Configuración de Entornos](#configuración-de-entornos)
- [Construcción y Despliegue](#construcción-y-despliegue)
- [Configuración de AWS](#configuración-de-aws)
- [GitHub Actions](#github-actions)
- [Docker](#docker)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)

## 🚀 Requisitos Previos

Antes de comenzar, asegúrate de tener instalado:

```bash
# Node.js (versión 18 o superior)
node --version

# npm (viene con Node.js)
npm --version

# Git
git --version

# AWS CLI (para despliegues)
aws --version
```

### Instalación de dependencias del sistema

**macOS:**
```bash
# Instalar Node.js con Homebrew
brew install node

# Instalar AWS CLI
brew install awscli
```

**Ubuntu/Debian:**
```bash
# Instalar Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# Instalar AWS CLI
sudo apt-get install awscli
```

## 🛠 Configuración Inicial

### 1. Clonar el repositorio

```bash
git clone <repository-url>
cd shrt/frontend
```

### 2. Instalar dependencias

```bash
# Instalar todas las dependencias
npm install

# Verificar que todo esté correctamente instalado
npm run typecheck
```

### 3. Configurar variables de entorno

Crea un archivo `.env.local` para desarrollo:

```bash
cp .env.example .env.local
```

Edita `.env.local` con tus configuraciones locales:

```env
VITE_API_URL=http://localhost:8000
VITE_APP_ENV=development
VITE_ENABLE_DEBUG=true
```

## 💻 Desarrollo Local

### Iniciar el servidor de desarrollo

```bash
# Ejecutar en modo desarrollo
npm run dev

# El servidor estará disponible en http://localhost:3000
```

### Comandos útiles durante el desarrollo

```bash
# Verificar tipos de TypeScript
npm run typecheck

# Ejecutar linter
npm run lint

# Corregir problemas de linting automáticamente
npm run lint:fix

# Ejecutar tests
npm test

# Ejecutar tests en modo watch
npm run test:watch

# Generar reporte de cobertura
npm run test:coverage
```

## 🌍 Configuración de Entornos

El proyecto está configurado para trabajar con tres entornos:

### Development (local)
```bash
npm run dev
```

### Staging
```bash
npm run build:staging
npm run preview
```

### Production
```bash
npm run build:production
npm run preview
```

### Variables de entorno por ambiente

Cada entorno tiene su archivo de configuración:

- `.env.local` - Desarrollo local
- `.env.staging` - Entorno de staging
- `.env.production` - Entorno de producción

## 🏗 Construcción y Despliegue

### Construcción local

```bash
# Para staging
npm run build:staging

# Para producción
npm run build:production

# Analizar el bundle
npm run analyze
```

### Despliegue manual a S3

Primero, configura tus credenciales de AWS:

```bash
aws configure
```

Luego ejecuta el despliegue:

```bash
# Staging
npm run deploy:staging

# Producción
npm run deploy:production
```

## ☁️ Configuración de AWS

### 1. Crear buckets de S3

```bash
# Crear bucket para staging
aws s3 mb s3://shrt-frontend-staging --region us-east-1

# Crear bucket para producción
aws s3 mb s3://shrt-frontend-production --region us-east-1
```

### 2. Configurar buckets para hosting estático

```bash
# Habilitar hosting estático para staging
aws s3 website s3://shrt-frontend-staging \
  --index-document index.html \
  --error-document index.html

# Habilitar hosting estático para producción
aws s3 website s3://shrt-frontend-production \
  --index-document index.html \
  --error-document index.html
```

### 3. Configurar políticas de bucket

Crea un archivo `bucket-policy.json`:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::shrt-frontend-production/*"
    }
  ]
}
```

Aplicar la política:

```bash
aws s3api put-bucket-policy \
  --bucket shrt-frontend-production \
  --policy file://bucket-policy.json
```

### 4. Configurar CloudFront (Opcional pero recomendado)

```bash
# Crear distribución de CloudFront para producción
aws cloudfront create-distribution \
  --distribution-config file://cloudfront-config.json
```

## 🔄 GitHub Actions

### Configuración de secretos

En la configuración de tu repositorio de GitHub, añade los siguientes secretos:

```
AWS_ACCESS_KEY_ID=<tu-access-key>
AWS_SECRET_ACCESS_KEY=<tu-secret-key>
CLOUDFRONT_DISTRIBUTION_STAGING=<distribution-id-staging>
CLOUDFRONT_DISTRIBUTION_PRODUCTION=<distribution-id-production>
```

### Estructura del workflow

El archivo `.github/workflows/frontend.yml` maneja automáticamente:

1. **Testing**: Ejecuta todos los tests en cada push
2. **Linting**: Verifica el código con ESLint
3. **Type checking**: Valida tipos de TypeScript
4. **Build**: Construye la aplicación para cada entorno
5. **Deploy**: Despliega automáticamente según la rama
   - `develop` → Staging
   - `main` → Production
6. **Cache invalidation**: Limpia la cache de CloudFront
7. **Performance audit**: Ejecuta Lighthouse CI

### Flujo de trabajo recomendado

```bash
# 1. Crear una nueva rama para desarrollo
git checkout -b feature/nueva-funcionalidad

# 2. Hacer cambios y commits
git add .
git commit -m "feat: añadir nueva funcionalidad"

# 3. Push a la rama de feature
git push origin feature/nueva-funcionalidad

# 4. Crear Pull Request hacia develop
# 5. Después del merge a develop, se despliega automáticamente a staging
# 6. Crear PR de develop a main para despliegue a producción
```

## 🐳 Docker

### Desarrollo con Docker

```bash
# Construir imagen de desarrollo
docker build -t shrt-frontend:dev .

# Ejecutar contenedor de desarrollo
docker run -p 3000:3000 shrt-frontend:dev
```

### Producción con Docker

```bash
# Construir imagen de producción
docker build -f Dockerfile.prod \
  --build-arg BUILD_ENV=production \
  --build-arg VITE_API_URL=https://api.shrt.com \
  --build-arg VITE_APP_ENV=production \
  -t shrt-frontend:prod .

# Ejecutar contenedor de producción
docker run -p 80:80 shrt-frontend:prod
```

### Docker Compose (para desarrollo completo)

```bash
# Iniciar todos los servicios
docker-compose up -d

# Ver logs
docker-compose logs -f frontend

# Parar servicios
docker-compose down
```

## 🧪 Testing

### Configuración de Jest

El proyecto incluye una configuración completa de Jest con:

- Testing de componentes React
- Mocks de módulos
- Cobertura de código
- Testing de hooks personalizados

### Ejecutar tests

```bash
# Todos los tests
npm test

# Tests en modo watch
npm run test:watch

# Con reporte de cobertura
npm run test:coverage

# Tests específicos
npm test -- --testPathPattern=components
```

### Escribir tests

```javascript
// src/components/__tests__/Button.test.tsx
import { render, screen } from '@testing-library/react'
import { Button } from '../Button'

describe('Button', () => {
  it('renders correctly', () => {
    render(<Button>Click me</Button>)
    expect(screen.getByText('Click me')).toBeInTheDocument()
  })
})
```

## 📊 Monitoreo y Performance

### Métricas incluidas

- **Bundle size analysis**: Usando rollup-plugin-visualizer
- **Lighthouse CI**: Auditorías automáticas de performance
- **Error tracking**: Configurado para entornos de staging/production
- **Performance monitoring**: Métricas de Core Web Vitals

### Ver análisis del bundle

```bash
npm run analyze
# Abre automáticamente el reporte en el navegador
```

## 🔧 Troubleshooting

### Problemas comunes y soluciones

#### Error: "Module not found"
```bash
# Limpiar cache de npm
npm cache clean --force

# Reinstalar dependencias
rm -rf node_modules package-lock.json
npm install
```

#### Error de tipos de TypeScript
```bash
# Verificar configuración
npm run typecheck

# Regenerar tipos si es necesario
npm run build
```

#### Problemas con el despliegue a S3
```bash
# Verificar credenciales de AWS
aws sts get-caller-identity

# Verificar permisos del bucket
aws s3api get-bucket-policy --bucket shrt-frontend-production
```

#### Error de CORS en desarrollo
Añade a tu `.env.local`:
```env
VITE_API_URL=http://localhost:8000
```

### Logs y debugging

```bash
# Ver logs del servidor de desarrollo
npm run dev -- --debug

# Ver logs de construcción
npm run build:staging -- --debug

# Verificar configuración de Vite
npx vite --help
```

## 📚 Estructura del Proyecto

```
frontend/
├── src/
│   ├── components/     # Componentes reutilizables
│   ├── pages/         # Páginas de la aplicación
│   ├── hooks/         # Custom hooks
│   ├── services/      # Servicios de API
│   ├── types/         # Definiciones de TypeScript
│   ├── utils/         # Funciones utilitarias
│   └── __tests__/     # Tests globales
├── public/            # Archivos estáticos
├── docker/           # Configuraciones de Docker
├── .github/          # Workflows de GitHub Actions
├── dist/             # Build de producción
└── coverage/         # Reportes de cobertura
```

## 🤝 Contribución

1. Fork el proyecto
2. Crea tu rama de feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📄 Licencia

Este proyecto está bajo la licencia ISC. Ver el archivo `LICENSE` para más detalles.

---

**Nota**: Este README se actualiza regularmente. Si encuentras algún problema o tienes sugerencias, no dudes en abrir un issue.