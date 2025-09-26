# Development Dockerfile for React Frontend
FROM node:18-alpine

# Install dumb-init for proper signal handling
RUN apk add --no-cache dumb-init

# Create non-root user for security
RUN addgroup -g 1001 -S nodejs && \
    adduser -S nextjs -u 1001

# Set working directory
WORKDIR /app

# Change ownership to nodejs user
RUN chown -R nextjs:nodejs /app
USER nextjs

# Install dependencies first (better layer caching)
COPY --chown=nextjs:nodejs package*.json ./
RUN npm ci --only=production=false

# Copy source code
COPY --chown=nextjs:nodejs . .

# Expose port for development
EXPOSE 3000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:3000/ || exit 1

# Start development server with proper signal handling
ENTRYPOINT ["dumb-init", "--"]
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "3000"]