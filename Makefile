# Shrt URL Shortener - Development & Production Commands

# Colors for output
GREEN=\033[0;32m
YELLOW=\033[0;33m
RED=\033[0;31m
NC=\033[0m # No Color

.PHONY: help install test build deploy clean logs

help: ## Show this help message
	@echo "$(GREEN)Shrt URL Shortener - Available Commands$(NC)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ {printf "$(YELLOW)%-20s$(NC) %s\\n", $$1, $$2}' $(MAKEFILE_LIST)

# Development Commands
install: ## Install all dependencies (backend + frontend)
	@echo "$(GREEN)Installing backend dependencies...$(NC)"
	composer install
	@echo "$(GREEN)Installing frontend dependencies...$(NC)"
	cd frontend && npm install
	@echo "$(GREEN)Setting up environment...$(NC)"
	cp .env.example .env
	php artisan key:generate
	@echo "$(GREEN)Installation complete!$(NC)"

setup: ## Setup development environment
	@echo "$(GREEN)Setting up development environment...$(NC)"
	php artisan migrate:fresh --seed
	@echo "$(GREEN)Development environment ready!$(NC)"

dev: ## Start development servers
	@echo "$(GREEN)Starting development servers...$(NC)"
	docker-compose up -d
	@echo "$(GREEN)Servers running:$(NC)"
	@echo "  - Backend: http://localhost:8000"
	@echo "  - Frontend: http://localhost:3000"
	@echo "  - Database: localhost:3306"

# Testing Commands
test: ## Run all tests
	@echo "$(GREEN)Running backend tests...$(NC)"
	php artisan test
	@echo "$(GREEN)Running frontend tests...$(NC)"
	cd frontend && npm test

test-backend: ## Run only backend tests
	@echo "$(GREEN)Running backend tests...$(NC)"
	php artisan test --coverage

test-frontend: ## Run only frontend tests
	@echo "$(GREEN)Running frontend tests...$(NC)"
	cd frontend && npm test -- --coverage

test-watch: ## Run tests in watch mode
	@echo "$(GREEN)Running tests in watch mode...$(NC)"
	php artisan test --watch &
	cd frontend && npm test -- --watch

# Code Quality Commands
lint: ## Run linting for both backend and frontend
	@echo "$(GREEN)Running backend linting...$(NC)"
	./vendor/bin/phpcs
	@echo "$(GREEN)Running frontend linting...$(NC)"
	cd frontend && npm run lint

lint-fix: ## Fix linting issues
	@echo "$(GREEN)Fixing backend lint issues...$(NC)"
	./vendor/bin/phpcbf
	@echo "$(GREEN)Fixing frontend lint issues...$(NC)"
	cd frontend && npm run lint:fix

typecheck: ## Run TypeScript type checking
	@echo "$(GREEN)Running TypeScript type checking...$(NC)"
	cd frontend && npm run typecheck

# Build Commands
prepare-laravel: ## Prepare Laravel app for production
	@echo "$(GREEN)Preparing Laravel for production...$(NC)"
	make setup-permissions
	composer install --optimize-autoloader --no-dev --no-interaction
	php artisan optimize:clear
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	php artisan event:cache
	@echo "$(GREEN)Laravel preparation complete!$(NC)"

build: ## Build for production (both backend and frontend)
	@echo "$(GREEN)Building backend...$(NC)"
	make prepare-laravel
	@echo "$(GREEN)Building frontend...$(NC)"
	cd frontend && npm ci --omit=dev
	cd frontend && npm run build:production
	@echo "$(GREEN)Build complete!$(NC)"

build-frontend: ## Build only frontend
	@echo "$(GREEN)Building frontend for production...$(NC)"
	cd frontend && npm ci --omit=dev
	cd frontend && npm run build:production
	@echo "$(GREEN)Frontend build complete!$(NC)"

build-frontend-staging: ## Build frontend for staging
	@echo "$(GREEN)Building frontend for staging...$(NC)"
	cd frontend && npm ci --omit=dev
	cd frontend && npm run build:staging
	@echo "$(GREEN)Frontend staging build complete!$(NC)"

build-docker: ## Build Docker images
	@echo "$(GREEN)Building Docker images...$(NC)"
	docker-compose build
	@echo "$(GREEN)Docker images built!$(NC)"

# AWS Deployment Commands
setup-permissions: ## Setup proper permissions for Laravel deployment
	@echo "$(GREEN)Setting up Laravel storage permissions...$(NC)"
	mkdir -p storage/framework/{cache,sessions,testing,views}
	mkdir -p storage/app/{private,public}
	mkdir -p storage/logs
	mkdir -p bootstrap/cache
	chmod -R 775 storage
	chmod -R 775 bootstrap/cache
	@echo "$(GREEN)Permissions set successfully!$(NC)"

build-prod: ## Build production Docker image
	@echo "$(GREEN)Building production Docker image...$(NC)"
	make prepare-laravel
	docker build -f Dockerfile --build-arg APP_ENV=production -t shrt-backend:latest .
	@echo "$(GREEN)Production image built!$(NC)"

login-ecr: ## Login to Amazon ECR
	@echo "$(GREEN)Logging into Amazon ECR...$(NC)"
	aws ecr get-login-password --region us-east-1 | docker login --username AWS --password-stdin 109995068952.dkr.ecr.us-east-1.amazonaws.com

tag-prod: ## Tag image for ECR
	@echo "$(GREEN)Tagging image for ECR...$(NC)"
	docker tag shrt-backend:latest 109995068952.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest
	docker tag shrt-backend:latest 109995068952.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:$(shell git rev-parse --short HEAD)

push-prod: ## Push image to ECR
	@echo "$(GREEN)Pushing image to ECR...$(NC)"
	make login-ecr
	docker push 109995068952.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:latest
	docker push 109995068952.dkr.ecr.us-east-1.amazonaws.com/shrt-backend:$(shell git rev-parse --short HEAD)
	@echo "$(GREEN)Image pushed successfully!$(NC)"

deploy-staging: ## Deploy to AWS ECS staging
	@echo "$(GREEN)Deploying to ECS staging...$(NC)"
	aws ecs update-service --cluster shrt-staging-cluster --service shrt-staging-service --force-new-deployment
	aws ecs wait services-stable --cluster shrt-staging-cluster --services shrt-staging-service
	@echo "$(GREEN)Staging deployment complete!$(NC)"

deploy-prod: ## Deploy to AWS ECS production
	@echo "$(GREEN)Deploying to ECS production...$(NC)"
	aws ecs update-service --cluster shrt-production-cluster --service shrt-production-service --force-new-deployment
	aws ecs wait services-stable --cluster shrt-production-cluster --services shrt-production-service
	@echo "$(GREEN)Production deployment complete!$(NC)"

check-aws-permissions: ## Check and fix permissions for AWS deployment
	@echo "$(GREEN)Checking AWS deployment permissions...$(NC)"
	make setup-permissions
	@echo "$(GREEN)Verifying storage directories exist...$(NC)"
	test -d storage/app/private || mkdir -p storage/app/private
	test -d storage/app/public || mkdir -p storage/app/public
	test -d storage/framework/cache || mkdir -p storage/framework/cache
	test -d storage/framework/sessions || mkdir -p storage/framework/sessions
	test -d storage/framework/testing || mkdir -p storage/framework/testing
	test -d storage/framework/views || mkdir -p storage/framework/views
	test -d storage/logs || mkdir -p storage/logs
	@echo "$(GREEN)Setting proper ownership for Docker build...$(NC)"
	find storage -type f -exec chmod 664 {} \;
	find storage -type d -exec chmod 775 {} \;
	find bootstrap/cache -type f -exec chmod 664 {} \;
	find bootstrap/cache -type d -exec chmod 775 {} \;
	@echo "$(GREEN)AWS deployment permissions verified!$(NC)"

deploy: ## Full deployment pipeline (backend + frontend)
	@echo "$(GREEN)Starting full deployment pipeline...$(NC)"
	make check-aws-permissions
	make build-prod
	make tag-prod
	make push-prod
	make deploy-prod
	make deploy-frontend-prod
	@echo "$(GREEN)Full deployment complete!$(NC)"

# Frontend Deployment Commands
deploy-frontend-staging: ## Deploy frontend to S3 staging
	@echo "$(GREEN)Deploying frontend to staging...$(NC)"
	make build-frontend-staging
	aws s3 sync frontend/dist/ s3://tu-dominio-frontend-staging/ --delete --cache-control "public,max-age=31536000,immutable" --exclude "*.html" --exclude "service-worker.js"
	aws s3 sync frontend/dist/ s3://tu-dominio-frontend-staging/ --cache-control "public,max-age=0,must-revalidate" --include "*.html" --include "service-worker.js" --exclude "*"
	@if [ -n "$(CLOUDFRONT_DISTRIBUTION_STAGING)" ]; then \
		echo "Invalidating CloudFront cache..."; \
		aws cloudfront create-invalidation --distribution-id $(CLOUDFRONT_DISTRIBUTION_STAGING) --paths "/*"; \
	fi
	@echo "$(GREEN)Frontend staging deployment complete!$(NC)"

deploy-frontend-prod: ## Deploy frontend to S3 production
	@echo "$(GREEN)Deploying frontend to production...$(NC)"
	make build-frontend
	aws s3 sync frontend/dist/ s3://tu-dominio-frontend-production/ --delete --cache-control "public,max-age=31536000,immutable" --exclude "*.html" --exclude "service-worker.js" --exclude "manifest.json"
	aws s3 sync frontend/dist/ s3://tu-dominio-frontend-production/ --cache-control "public,max-age=0,must-revalidate" --include "*.html" --include "service-worker.js" --exclude "*"
	aws s3 sync frontend/dist/ s3://tu-dominio-frontend-production/ --cache-control "public,max-age=86400" --include "manifest.json" --exclude "*"
	@if [ -n "$(CLOUDFRONT_DISTRIBUTION_PRODUCTION)" ]; then \
		echo "Invalidating CloudFront cache..."; \
		aws cloudfront create-invalidation --distribution-id $(CLOUDFRONT_DISTRIBUTION_PRODUCTION) --paths "/*"; \
	fi
	@echo "$(GREEN)Frontend production deployment complete!$(NC)"

setup-s3-frontend: ## Setup S3 buckets with proper configuration
	@echo "$(GREEN)Setting up S3 buckets for frontend...$(NC)"
	aws s3 website s3://tu-dominio-frontend-staging --index-document index.html --error-document index.html
	aws s3 website s3://tu-dominio-frontend-production --index-document index.html --error-document index.html
	aws s3api put-bucket-policy --bucket tu-dominio-frontend-staging --policy file://s3-bucket-policy.json || echo "Policy file not found, skipping..."
	aws s3api put-bucket-policy --bucket tu-dominio-frontend-production --policy file://s3-bucket-policy.json || echo "Policy file not found, skipping..."
	@echo "$(GREEN)S3 frontend setup complete!$(NC)"

# Maintenance Commands
migrate: ## Run database migrations
	@echo "$(GREEN)Running migrations...$(NC)"
	php artisan migrate

migrate-fresh: ## Fresh migration with seeding
	@echo "$(YELLOW)WARNING: This will delete all data!$(NC)"
	php artisan migrate:fresh --seed

backup-db: ## Backup database
	@echo "$(GREEN)Creating database backup...$(NC)"
	php artisan backup:run

logs: ## Show application logs
	@echo "$(GREEN)Showing logs...$(NC)"
	docker-compose logs -f

logs-backend: ## Show backend logs
	docker-compose logs -f app

logs-frontend: ## Show frontend logs
	docker-compose logs -f frontend

# Clean Commands
clean: ## Clean caches and temporary files
	@echo "$(GREEN)Cleaning caches...$(NC)"
	php artisan cache:clear
	php artisan config:clear
	php artisan route:clear
	php artisan view:clear
	cd frontend && npm run clean

clean-all: ## Clean everything including dependencies
	@echo "$(RED)Cleaning all dependencies and caches...$(NC)"
	rm -rf vendor node_modules frontend/node_modules
	php artisan cache:clear
	php artisan config:clear

# Docker Commands
docker-up: ## Start Docker services
	docker-compose up -d

docker-down: ## Stop Docker services
	docker-compose down

docker-rebuild: ## Rebuild and restart Docker services
	docker-compose down
	docker-compose build --no-cache
	docker-compose up -d

# Monitoring Commands
monitor: ## Show system monitoring
	@echo "$(GREEN)System Monitoring:$(NC)"
	@echo "Backend Status:"
	@curl -s http://localhost:8000/health || echo "Backend not responding"
	@echo "Frontend Status:"
	@curl -s http://localhost:3000 > /dev/null && echo "Frontend: OK" || echo "Frontend: DOWN"

# Security Commands
security-scan: ## Run security scans
	@echo "$(GREEN)Running security scans...$(NC)"
	composer audit
	cd frontend && npm audit

# Database Commands
db-console: ## Open database console
	php artisan tinker

db-reset: ## Reset database
	@echo "$(YELLOW)Resetting database...$(NC)"
	php artisan migrate:fresh --seed

# AWS Infrastructure Commands
aws-setup: ## Setup complete AWS infrastructure (backend + frontend)
	@echo "$(GREEN)Setting up AWS infrastructure...$(NC)"
	@echo "Creating ECR repository..."
	aws ecr create-repository --repository-name shrt-backend --region us-east-1 || true
	@echo "Creating ECS clusters..."
	aws ecs create-cluster --cluster-name shrt-staging-cluster --capacity-providers FARGATE --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1 || true
	aws ecs create-cluster --cluster-name shrt-production-cluster --capacity-providers FARGATE --default-capacity-provider-strategy capacityProvider=FARGATE,weight=1 || true
	@echo "Creating S3 buckets..."
	aws s3 mb s3://tu-dominio-frontend-staging --region us-east-1 || true
	aws s3 mb s3://tu-dominio-frontend-production --region us-east-1 || true
	aws s3 mb s3://tu-dominio-backups --region us-east-1 || true
	@echo "Configuring S3 for frontend hosting..."
	make setup-s3-frontend
	@echo "$(GREEN)AWS infrastructure setup complete!$(NC)"
	@echo "$(YELLOW)Next: Configure RDS and ElastiCache manually or use CloudFormation$(NC)"
	@echo "$(YELLOW)Don't forget to set CLOUDFRONT_DISTRIBUTION_* environment variables$(NC)"

aws-status: ## Check AWS services status
	@echo "$(GREEN)Checking AWS services status...$(NC)"
	@echo "ECS Clusters:"
	aws ecs describe-clusters --clusters shrt-staging-cluster shrt-production-cluster --query 'clusters[].{Name:clusterName,Status:status,ActiveServices:activeServicesCount}' --output table
	@echo "ECR Repository:"
	aws ecr describe-repositories --repository-names shrt-backend --query 'repositories[].{Name:repositoryName,URI:repositoryUri}' --output table
	@echo "S3 Buckets:"
	aws s3 ls | grep 'tu-dominio\|shrt'

aws-logs: ## View ECS service logs
	@echo "$(GREEN)Viewing ECS logs...$(NC)"
	aws logs describe-log-groups --log-group-name-prefix '/ecs/shrt' --query 'logGroups[].{Name:logGroupName,CreationTime:creationTime}' --output table

aws-cleanup: ## Cleanup AWS resources (DANGER!)
	@echo "$(RED)WARNING: This will delete AWS resources!$(NC)"
	@echo "This is a destructive operation. Press Ctrl+C to cancel, or press Enter to continue..."
	@read dummy
	aws ecs delete-cluster --cluster shrt-staging-cluster || true
	aws ecs delete-cluster --cluster shrt-production-cluster || true
	aws ecr delete-repository --repository-name shrt-backend --force || true
	aws s3 rb s3://tu-dominio-frontend-staging --force || true
	aws s3 rb s3://tu-dominio-frontend-production --force || true
	aws s3 rb s3://tu-dominio-backups --force || true