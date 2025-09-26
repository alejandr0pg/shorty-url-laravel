# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Shrt is a URL shortener application built with Laravel (backend) and React (frontend). The system generates unique short codes (6-8 characters) using safe characters (avoiding confusing ones like 0/O, 1/I/l) and tracks clicks per URL.

## Common Development Commands

### Backend (Laravel)

-   **Run development server**: `php artisan serve` or `composer run dev` (runs server, queue, logs, and vite concurrently)
-   **Run tests**: `php artisan test` or `composer test`
-   **Code formatting**: `./vendor/bin/pint` (Laravel Pint for PHP code style)
-   **Run migrations**: `php artisan migrate`
-   **Generate API documentation**: `php artisan scribe:generate` (uses Scribe for API docs)

### Frontend (React)

-   **Install dependencies**: `cd frontend && npm install`
-   **Run development server**: `cd frontend && npm run dev`
-   **Build for production**: `cd frontend && npm run build`
-   **Run tests**: `cd frontend && npm test`

## Architecture

### Backend Structure

-   **API Controller**: `app/Http/Controllers/Api/UrlController.php` handles all URL operations
-   **Model**: `app/Models/Url.php` contains business logic for short code generation
-   **Routes**:
    -   `routes/api.php`: API endpoints with rate limiting (60 req/min)
    -   `routes/web.php`: Redirect route for short codes
-   **Middleware**: Throttle middleware applied globally to API routes
-   **Cache**: Redis used in production for caching redirects (1-hour TTL)

### Frontend Structure

-   **Single Page Application**: `frontend/src/App.jsx` contains all views in one component
-   **State Management**: React hooks (useState, useEffect) for local state
-   **API Communication**: Axios with device ID header for tracking
-   **Styling**: TailwindCSS with Vite build process

### Key Design Patterns

1. **Device Tracking**: Uses localStorage-based device ID sent via `X-Device-ID` header
2. **Short Code Generation**: Random generation with collision check, using safe character set (ABCDEFGHJKMNPQRSTUVWXYZ23456789)
3. **URL Validation**: RFC 1738 compliant validation using Laravel's built-in validators
4. **Response Caching**: Short code lookups cached to reduce database hits

## API Endpoints

-   `POST /api/urls`: Create shortened URL

    -   Header: `X-Device-ID: device_abc123`
    -   Body: `{ "url": "https://example.com" }`
    -   Response: `{ "short_url": "http://localhost/abc123", "original_url": "https://example.com", "code": "abc123" }`

-   `GET /api/urls`: List URLs for device

    -   Header: `X-Device-ID: device_abc123`
    -   Response: Array of URL objects with clicks count

-   `GET /{code}`: Redirect to original URL (302 redirect)

## Testing Approach

-   **Backend**: Pest framework for feature and unit tests
-   **Frontend**: Jest with React Testing Library
-   **CI/CD**: GitHub Actions runs tests on push/PR to main branch

## Database

-   **Development**: SQLite (database/database.sqlite)
-   **Production**: PostgreSQL recommended
-   **Key indexes**: `short_code` field indexed for fast lookups
