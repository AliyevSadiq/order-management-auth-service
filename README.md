# Auth Service

A production-ready JWT authentication microservice built with **Symfony 7.0** and **PHP 8.3+**. Provides user registration, login, token refresh, and profile management with stateless JWT-based authentication.

## Architecture Overview

![Auth Service Architecture](docs/auth-service-animated.svg)

## Table of Contents

- [Auth Service](#auth-service)
  - [Architecture Overview](#architecture-overview)
  - [Table of Contents](#table-of-contents)
  - [Features](#features)
  - [Tech Stack](#tech-stack)
  - [API Endpoints](#api-endpoints)
    - [Additional Endpoints](#additional-endpoints)
    - [Request / Response Examples](#request--response-examples)
  - [Authentication Flow](#authentication-flow)
  - [Project Structure](#project-structure)
  - [Getting Started](#getting-started)
    - [Prerequisites](#prerequisites)
    - [Run with Docker](#run-with-docker)
    - [Run Locally](#run-locally)
  - [Environment Variables](#environment-variables)
  - [Database Schema](#database-schema)
    - [users](#users)
    - [refresh\_tokens](#refresh_tokens)
  - [Event-Driven Integration](#event-driven-integration)
  - [Monitoring \& Observability](#monitoring--observability)
    - [Prometheus Metrics (`GET /metrics`)](#prometheus-metrics-get-metrics)
    - [Health Check (`GET /health/auth`)](#health-check-get-healthauth)
    - [Distributed Tracing](#distributed-tracing)
    - [Error Tracking](#error-tracking)
  - [Testing](#testing)
  - [Docker Services](#docker-services)
  - [License](#license)

## Features

- JWT access tokens (RS256, 1-hour TTL) with refresh token rotation (7-day TTL)
- Argon2 password hashing
- Async event publishing via RabbitMQ (UserRegistered, UserLoggedIn)
- Prometheus metrics for auth operations and HTTP requests
- Health check endpoint for service discovery
- Sentry error tracking
- OpenAPI documentation (NelmioApiDoc)
- CORS support
- Distributed tracing via Jaeger (Zipkin format)
- Interface-driven service layer (contract-based design)

## Tech Stack

| Component       | Technology                        |
|-----------------|-----------------------------------|
| Framework       | Symfony 7.0                       |
| Language        | PHP 8.3+                          |
| Database        | PostgreSQL 16                     |
| Message Broker  | RabbitMQ (AMQP)                   |
| Auth            | Lexik JWT Authentication (RS256)  |
| Monitoring      | Prometheus + Sentry               |
| Container       | Docker (PHP 8.4-FPM)             |


## API Endpoints

Base path: `/api/auth`

| Method | Endpoint     | Description                      | Auth Required |
|--------|-------------|----------------------------------|---------------|
| POST   | `/register` | Register a new user              | No            |
| POST   | `/login`    | Authenticate and get JWT tokens  | No            |
| POST   | `/logout`   | Invalidate refresh token         | Yes (Bearer)  |
| POST   | `/refresh`  | Refresh access token             | No            |
| GET    | `/profile`  | Get authenticated user profile   | Yes (Bearer)  |

### Additional Endpoints

| Method | Endpoint       | Description                       |
|--------|---------------|-----------------------------------|
| GET    | `/health/auth` | Health check (DB, RabbitMQ)       |
| GET    | `/metrics`     | Prometheus metrics                |

### Request / Response Examples

**Register**
```http
POST /api/auth/register
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "securePassword123",
  "firstName": "John",
  "lastName": "Doe"
}
```
```json
// 201 Created
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "email": "user@example.com",
  "firstName": "John",
  "lastName": "Doe",
  "roles": ["ROLE_USER"],
  "createdAt": "2026-03-06T12:00:00+00:00"
}
```

**Login**
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "securePassword123"
}
```
```json
// 200 OK
{
  "access_token": "eyJhbGciOiJSUzI1NiIs...",
  "refresh_token": "a1b2c3d4e5f6...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "firstName": "John",
    "lastName": "Doe",
    "roles": ["ROLE_USER"]
  }
}
```

**Refresh Token**
```http
POST /api/auth/refresh
Content-Type: application/json

{
  "refresh_token": "a1b2c3d4e5f6..."
}
```

**Profile** (Authenticated)
```http
GET /api/auth/profile
Authorization: Bearer eyJhbGciOiJSUzI1NiIs...
```

## Authentication Flow

```
                                    Auth Service
                                   ┌─────────────────────────────┐
                                   │                             │
  ┌──────────┐   POST /login       │  ┌───────────┐             │
  │          │ ─────────────────►  │  │  Login    │             │
  │          │                     │  │  Service  │             │
  │          │   access_token +    │  └─────┬─────┘             │
  │  Client  │   refresh_token     │        │                   │
  │          │ ◄───────────────── │        ▼                   │
  │          │                     │  ┌───────────┐  ┌───────┐ │
  │          │   GET /profile      │  │  JWT      │  │ User  │ │
  │          │   Authorization:    │  │  Token    │  │ DB    │ │
  │          │   Bearer <token>    │  │  Service  │  │(PgSQL)│ │
  │          │ ─────────────────►  │  └───────────┘  └───────┘ │
  │          │                     │        │                   │
  │          │   user profile      │        ▼                   │
  │          │ ◄───────────────── │  ┌───────────┐             │
  │          │                     │  │ Refresh   │             │
  │          │   POST /refresh     │  │ Token DB  │             │
  │          │ ─────────────────►  │  └───────────┘             │
  │          │                     │                             │
  │          │   new tokens        │        ┌─────────┐         │
  │          │ ◄───────────────── │        │RabbitMQ │──► Other │
  └──────────┘                     │        └─────────┘  Services
                                   └─────────────────────────────┘
```

1. **Login**: Client sends credentials → service validates → returns access + refresh tokens
2. **Authenticated Request**: Client includes `Authorization: Bearer <token>` header
3. **Token Refresh**: When access token expires, use refresh token to get a new pair (old refresh token is revoked)
4. **Logout**: Refresh token is invalidated server-side

## Project Structure

```
src/
├── Contract/           # Service interfaces (6 contracts)
├── Controller/         # API controllers (7 controllers)
├── DTO/                # Data transfer objects
│   ├── LoginRequest
│   ├── RegisterRequest
│   └── UserResponse
├── Entity/             # Doctrine entities
│   ├── User            # users table (UUID, email, password, name, roles)
│   └── RefreshToken    # refresh_tokens table (UUID, token, expiry)
├── EventListener/      # Event subscribers
│   ├── ExceptionListener        # JSON error responses
│   ├── LoginSuccessListener     # Publishes UserLoggedIn event
│   ├── AuthMetricsListener      # Auth operation metrics
│   └── HttpMetricsListener      # HTTP request metrics
├── Logger/             # Custom Monolog processor
├── Message/            # Async message classes
│   ├── UserRegistered
│   └── UserLoggedIn
├── Metrics/            # Prometheus metrics registry
├── Middleware/         # JWT validation middleware
├── Repository/         # Doctrine repositories
└── Service/            # Business logic (7 services)
    ├── RegistrationService
    ├── LoginService
    ├── JwtTokenService
    ├── TokenService
    ├── PasswordHasherService
    └── ...
```

## Getting Started

### Prerequisites

- Docker & Docker Compose
- (Optional) PHP 8.3+ and Composer for local development

### Run with Docker

```bash
# Start all services
docker compose up -d

# Run database migrations
docker compose exec auth-service php bin/console doctrine:migrations:migrate --no-interaction

# Generate JWT keys (if not present)
docker compose exec auth-service php bin/console lexik:jwt:generate-keypair --skip-if-exists
```

The service will be available at `http://localhost:8080`.

### Run Locally

```bash
composer install

# Configure .env.local with your database and service credentials

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console lexik:jwt:generate-keypair --skip-if-exists

symfony server:start
# or
php -S 0.0.0.0:8080 -t public
```

## Environment Variables

| Variable               | Description                    | Default                  |
|------------------------|--------------------------------|--------------------------|
| `APP_ENV`              | Environment (dev/prod/test)    | `dev`                    |
| `APP_SECRET`           | Application secret key         | —                        |
| `DATABASE_URL`         | PostgreSQL connection string   | —                        |
| `JWT_SECRET_KEY`       | Path to RSA private key        | `config/jwt/private.pem` |
| `JWT_PUBLIC_KEY`       | Path to RSA public key         | `config/jwt/public.pem`  |
| `JWT_PASSPHRASE`       | Private key passphrase         | —                        |
| `MESSENGER_TRANSPORT_DSN` | RabbitMQ AMQP connection    | —                        |
| `SENTRY_DSN`           | Sentry error tracking DSN      | —                        |
| `CORS_ALLOW_ORIGIN`    | Allowed CORS origins           | `*`                      |

See `.env.example` for a complete list.

## Database Schema

### users

| Column      | Type         | Constraints          |
|-------------|--------------|----------------------|
| id          | UUID         | Primary Key          |
| email       | VARCHAR(180) | Unique, Not Null     |
| password    | VARCHAR(255) | Not Null (Argon2)    |
| first_name  | VARCHAR(100) | Not Null             |
| last_name   | VARCHAR(100) | Not Null             |
| roles       | JSON         | Default: ["ROLE_USER"]|
| created_at  | TIMESTAMP    | Immutable            |
| updated_at  | TIMESTAMP    | Auto-updated         |

### refresh_tokens

| Column      | Type         | Constraints          |
|-------------|--------------|----------------------|
| id          | UUID         | Primary Key          |
| user_id     | UUID         | Indexed              |
| token       | VARCHAR(512) | Unique               |
| expires_at  | TIMESTAMP    | Not Null             |
| created_at  | TIMESTAMP    | Immutable            |

## Event-Driven Integration

The service publishes domain events to RabbitMQ for consumption by other microservices:

| Event             | Trigger              | Payload                                      |
|-------------------|----------------------|----------------------------------------------|
| `UserRegistered`  | Successful registration | userId, email, firstName, lastName          |
| `UserLoggedIn`    | Successful login       | userId, email, ipAddress, userAgent          |

Messages are published to the `messages` topic exchange using routing keys `auth.user_registered` and `auth.user_logged_in` (set via `AmqpStamp` on dispatch).

**Consumer**: The notification-service subscribes to both events via the `messages` topic exchange (queue: `notification_messages`). Order-service and product-service do **not** consume auth events.

## Monitoring & Observability

### Prometheus Metrics (`GET /metrics`)

- `auth_registration_attempts_total` — Registration attempts by status
- `auth_registrations_total` — Successful registrations
- `auth_registration_duplicates_total` — Duplicate email attempts
- `auth_login_attempts_total` — Login attempts by status
- `auth_login_success_total` / `auth_login_failures_total`
- `auth_logouts_total`
- `auth_token_refreshes_total` — Token refresh attempts by status
- HTTP request duration and status code metrics

### Health Check (`GET /health/auth`)

Returns connectivity status for:
- PostgreSQL database
- RabbitMQ message broker
- JWT key files

### Distributed Tracing

The service integrates with Jaeger for distributed tracing via Zipkin format. Key components: `TracingService`, `TracingListener`, `TracingFinishListener`, `TracingResponseListener`, and `TraceContextProcessor`.

### Error Tracking

Sentry integration for real-time exception monitoring.

## Testing

```bash
# Run tests
php bin/phpunit

# Static analysis
vendor/bin/phpstan analyse src --level=max

# With Docker
docker compose -f docker-compose.test.yml up --abort-on-container-exit
```

CI runs PHPUnit on PHP 8.3 & 8.4 matrix, plus PHPStan static analysis and Docker build verification.

## Docker Services

| Service            | Image               | Port  | Purpose                    |
|--------------------|---------------------|-------|----------------------------|
| auth-service       | Custom (Dockerfile) | 8080  | Main application           |
| postgresql         | postgres:16-alpine  | 5433  | Primary database           |
| postgres-exporter  | prom/postgres-exporter | 9187 | DB metrics for Prometheus |

## License

This project is part of the Order Management System microservices architecture.
