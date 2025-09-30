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

### Opción 1: Sin Docker (Más Simple)

```bash
# 1. Clonar e instalar
git clone <repo-url>
cd shrt
composer install

# 2. Configurar environment
cp .env.example .env
php artisan key:generate

# 3. Base de datos SQLite (local)
touch database/database.sqlite

# 4. Configurar .env para SQLite
DB_CONNECTION=sqlite
DB_DATABASE=/ruta/completa/a/database/database.sqlite

# 5. Migrar y seed
php artisan migrate --seed

# 6. Generar documentación API
php artisan scribe:generate

# 7. Iniciar servidor
php artisan serve
# ✅ Listo: http://localhost:8000
```

**Verificar que funciona:**
```bash
curl http://localhost:8000/health
open http://localhost:8000/docs
```

### Opción 2: Con Docker Compose

```bash
# 1. Clonar repositorio
git clone <repo-url>
cd shrt

# 2. Copiar .env (se creará en el build)
cp .env.example .env

# 3. Iniciar servicios
docker-compose up -d

# 4. Ver logs
docker-compose logs -f

# ✅ Listo: http://localhost:8000
```

**Servicios incluidos:**
- Backend API: http://localhost:8000
- MySQL: localhost:3306
- Redis: localhost:6379

**Comandos útiles:**
```bash
docker-compose down              # Detener
docker-compose restart           # Reiniciar
docker-compose exec app bash     # Entrar al contenedor
```

## ☁️ Deployment AWS

### Primera Vez - Setup Completo

Ver **[QUICK_START.md](QUICK_START.md)** para guía paso a paso que incluye:
- VPC, Subnets y Security Groups
- RDS MySQL (base de datos)
- ElastiCache Redis (cache)
- ECR (registro de imágenes Docker)
- Application Load Balancer
- ECS Fargate (contenedores)
- Task Definition y Service

**O ver [DEPLOYMENT.md](DEPLOYMENT.md)** para explicación detallada de cada componente.

### Updates - GitHub Actions (Automático)

```bash
# Push a main despliega a producción automáticamente
git add .
git commit -m "Update feature"
git push origin main
```

### Updates - Manual

```bash
# 1. Build imagen Docker
docker build -t shrt-backend:latest .

# 2. Login a ECR
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin $(aws sts get-caller-identity --query Account --output text).dkr.ecr.us-east-1.amazonaws.com

# 3. Tag y push
docker tag shrt-backend:latest $(aws sts get-caller-identity --query Account --output text).dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest
docker push $(aws sts get-caller-identity --query Account --output text).dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest

# 4. Forzar nuevo deployment
aws ecs update-service \
  --cluster shrt-production-cluster \
  --service shrt-production-service \
  --force-new-deployment
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
# Development Local
php artisan serve                    # Iniciar servidor
php artisan test                     # Ejecutar tests
docker-compose logs -f               # Ver logs (con Docker)

# AWS Monitoring
aws logs tail /ecs/shrt-backend-production --follow
aws ecs describe-services --cluster shrt-production-cluster --services shrt-production-service
aws ecs list-tasks --cluster shrt-production-cluster --service-name shrt-production-service

# Database
php artisan migrate                  # Ejecutar migraciones
php artisan migrate:fresh --seed     # Reset completo
php artisan tinker                   # Console interactiva

# Cache y optimización
php artisan optimize:clear           # Limpiar todo
php artisan cache:clear              # Solo cache
php artisan config:clear             # Solo config
php artisan route:clear              # Solo routes
php artisan scribe:generate          # Regenerar docs
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
