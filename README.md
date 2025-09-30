# Shrt - URL Shortener

Acortador de URLs desarrollado con Laravel y React, desplegado en AWS ECS con CI/CD completo.

## 📚 Documentación Completa

- **[⚡ Quick Start](QUICK_START.md)** - Setup local en 5 minutos
- **[🚀 Deployment Guide](DEPLOYMENT.md)** - Guía completa de deployment (local + AWS)
- **[🔧 Troubleshooting](TROUBLESHOOTING.md)** - Solución de problemas comunes
- **[🔐 Environment Variables](.env.example)** - Configuración de variables de entorno

## 🌐 URLs de Producción

### PRODUCCIÓN
- **Frontend:** <https://d3dcezd6ji3gto.cloudfront.net>
- **API Backend:** <http://shrt-production-alb-132772302.us-east-1.elb.amazonaws.com>
- **API Docs:** <http://shrt-production-alb-132772302.us-east-1.elb.amazonaws.com/docs>

### STAGING
- **Frontend:** <https://d22b8xej3kve4.cloudfront.net>
- **Backend:** Se activa al crear la rama `develop`

## 🚀 Setup Rápido

### Local Development (Docker)

```bash
git clone <repo-url>
cd shrt

# Copiar y configurar environment
cp .env.example .env
php artisan key:generate

# Iniciar con Docker Compose
docker-compose up -d

# Setup base de datos
php artisan migrate --seed

# Generar documentación
php artisan scribe:generate
```

**Acceder a:**
- Backend: <http://localhost:8000>
- API Docs: <http://localhost:8000/docs>
- Health Check: <http://localhost:8000/health>

### Sin Docker

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite para desarrollo
touch database/database.sqlite
php artisan migrate --seed

php artisan serve
# → http://localhost:8000
```

## ☁️ Deployment AWS

### Primera Vez (Setup Completo)

Ver guía detallada en **[DEPLOYMENT.md](DEPLOYMENT.md)** que incluye:
- Creación de infraestructura AWS (RDS, Redis, ECR, ECS, ALB)
- Configuración de security groups y networking
- Setup de GitHub Actions
- Variables de entorno para producción

### Updates (GitHub Actions Automático)

```bash
# Push a main deploya a producción automáticamente
git add .
git commit -m "Update feature"
git push origin main
```

### Manual Deployment

```bash
# Build y push
make build-prod
make push-prod

# Desplegar
make deploy-prod

# O todo en uno
make deploy
```

## 🧪 Testing

```bash
# Backend
php artisan test
./vendor/bin/pest

# Con cobertura
php artisan test --coverage
```

## 📊 Comandos Útiles

```bash
# Development
make dev          # Iniciar servidor de desarrollo
make test         # Ejecutar tests
make logs         # Ver logs

# AWS Monitoring
aws logs tail /ecs/shrt-backend-production --follow
aws ecs describe-services --cluster shrt-production-cluster --services shrt-production-service

# Database
php artisan migrate
php artisan db:seed
php artisan tinker

# Cache
php artisan optimize:clear
php artisan cache:clear
```

## 🔍 Health Checks

```bash
# Local
curl http://localhost:8000/health

# Production
curl http://shrt-production-alb-132772302.us-east-1.elb.amazonaws.com/health | jq '.'
```

## 🛠 Troubleshooting

Para problemas comunes, ver **[TROUBLESHOOTING.md](TROUBLESHOOTING.md)**

Problemas típicos:
- Container no inicia → Revisar logs en CloudWatch
- Base de datos no conecta → Verificar security groups
- 404 en rutas → Limpiar route cache
- Targets unhealthy en ALB → Ajustar health check thresholds

## 🏗️ Arquitectura

### Backend Stack
- Laravel 12
- PHP 8.3
- MySQL 8.0 (RDS)
- Redis 7 (ElastiCache)
- Nginx

### Infraestructura AWS
- **Compute:** ECS Fargate
- **Load Balancer:** Application Load Balancer
- **Database:** RDS MySQL
- **Cache:** ElastiCache Redis
- **Container Registry:** ECR
- **CDN:** CloudFront
- **Storage:** S3
- **CI/CD:** GitHub Actions

## 🤝 Contribución

1. Fork el repositorio
2. Crea una rama: `git checkout -b feature/nueva-funcionalidad`
3. Commit cambios: `git commit -m 'feat: agrega nueva funcionalidad'`
4. Push: `git push origin feature/nueva-funcionalidad`
5. Abre un Pull Request

## 📄 Licencia

MIT License - ver [LICENSE](LICENSE) para detalles.
