#!/bin/bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DIST_DIR="/app/dist"
AWS_REGION="${AWS_DEFAULT_REGION:-us-east-1}"

echo -e "${BLUE}🚀 Starting S3 deployment for shrt frontend...${NC}"

# Validate required environment variables
if [ -z "$S3_BUCKET" ]; then
    echo -e "${RED}❌ Error: S3_BUCKET environment variable is required${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Bucket: $S3_BUCKET${NC}"
echo -e "${YELLOW}🌍 Region: $AWS_REGION${NC}"

# Check if dist directory exists
if [ ! -d "$DIST_DIR" ]; then
    echo -e "${RED}❌ Error: Build directory $DIST_DIR not found${NC}"
    exit 1
fi

# Sync static assets with long cache
echo -e "${BLUE}📤 Uploading static assets with long cache...${NC}"
aws s3 sync "$DIST_DIR" "s3://$S3_BUCKET/" \
    --region "$AWS_REGION" \
    --delete \
    --cache-control "public, max-age=31536000, immutable" \
    --exclude "*.html" \
    --exclude "service-worker.js" \
    --exclude "manifest.json" \
    --exclude "robots.txt" \
    --exclude "sitemap.xml"

# Upload HTML files and service worker with no cache
echo -e "${BLUE}📄 Uploading HTML and service worker with no cache...${NC}"
aws s3 sync "$DIST_DIR" "s3://$S3_BUCKET/" \
    --region "$AWS_REGION" \
    --cache-control "public, max-age=0, must-revalidate" \
    --include "*.html" \
    --include "service-worker.js" \
    --exclude "*"

# Upload manifest and SEO files with short cache
echo -e "${BLUE}📋 Uploading manifest and SEO files...${NC}"
aws s3 sync "$DIST_DIR" "s3://$S3_BUCKET/" \
    --region "$AWS_REGION" \
    --cache-control "public, max-age=86400" \
    --include "manifest.json" \
    --include "robots.txt" \
    --include "sitemap.xml" \
    --exclude "*"

# Set content-encoding for gzipped files
echo -e "${BLUE}🗜️ Setting content-encoding for compressed files...${NC}"
find "$DIST_DIR" -name "*.gz" -type f | while read -r file; do
    key="${file#$DIST_DIR/}"
    key="${key%.gz}"

    if [[ "$key" == *.js ]]; then
        content_type="application/javascript"
    elif [[ "$key" == *.css ]]; then
        content_type="text/css"
    elif [[ "$key" == *.html ]]; then
        content_type="text/html"
    elif [[ "$key" == *.json ]]; then
        content_type="application/json"
    else
        content_type="binary/octet-stream"
    fi

    aws s3 cp "$file" "s3://$S3_BUCKET/$key" \
        --region "$AWS_REGION" \
        --content-encoding gzip \
        --content-type "$content_type" \
        --cache-control "public, max-age=31536000, immutable"
done

# Invalidate CloudFront cache if distribution ID is provided
if [ -n "$CLOUDFRONT_DISTRIBUTION_ID" ]; then
    echo -e "${BLUE}🔄 Invalidating CloudFront cache...${NC}"

    INVALIDATION_ID=$(aws cloudfront create-invalidation \
        --distribution-id "$CLOUDFRONT_DISTRIBUTION_ID" \
        --paths "/*" \
        --query 'Invalidation.Id' \
        --output text)

    echo -e "${YELLOW}⏳ CloudFront invalidation created: $INVALIDATION_ID${NC}"

    # Wait for invalidation to complete (optional - can be commented out for faster deployments)
    echo -e "${BLUE}⏳ Waiting for invalidation to complete...${NC}"
    aws cloudfront wait invalidation-completed \
        --distribution-id "$CLOUDFRONT_DISTRIBUTION_ID" \
        --id "$INVALIDATION_ID"

    echo -e "${GREEN}✅ CloudFront invalidation completed${NC}"
else
    echo -e "${YELLOW}⚠️ No CloudFront distribution ID provided - skipping cache invalidation${NC}"
fi

# Verify deployment
echo -e "${BLUE}🔍 Verifying deployment...${NC}"
BUCKET_URL="https://$S3_BUCKET.s3.$AWS_REGION.amazonaws.com"

if curl -s --fail "$BUCKET_URL/index.html" > /dev/null; then
    echo -e "${GREEN}✅ Deployment verified successfully${NC}"
    echo -e "${GREEN}🌐 Frontend is now live at: $BUCKET_URL${NC}"
else
    echo -e "${RED}❌ Deployment verification failed${NC}"
    exit 1
fi

echo -e "${GREEN}🎉 S3 deployment completed successfully!${NC}"