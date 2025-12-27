# MacQueen Travel Platform

A B2B multi-tenant travel platform built with Laravel 12 and PHP 8.3.

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [API Documentation](#api-documentation)
- [Security](#security)
- [Performance](#performance)

---

## Overview

MacQueen Travel Platform enables corporate clients to manage:

- Employees
- Travel requests
- Wallets & balances
- Booking approvals
- External providers (Flights / Hotels)

### Key Features

- Multi-tenant architecture with data isolation
- Role-based access control (Admin, Manager, Employee)
- Secure wallet transactions with concurrency handling
- Queue-based booking processing
- Rate limiting and caching

---

## Architecture

### Domain-Driven Design
```
app/
├── Domain/
│   ├── Tenant/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Contracts/
│   │   └── Traits/
│   ├── Employee/
│   ├── Travel/
│   ├── Wallet/
│   └── Booking/
├── Http/
│   ├── Controllers/Api/
│   ├── Middleware/
│   ├── Requests/
│   └── Traits/
├── Jobs/
├── Policies/
├── Providers/
└── Services/
```

### Multi-Tenancy Strategy

| Aspect | Implementation |
|--------|----------------|
| Resolution | User's tenant_id / X-Tenant-ID header / Subdomain |
| Data Isolation | Global scope via `BelongsToTenant` trait |
| Shared Tables | `tenants`, `users` |
| Tenant Tables | `employees`, `wallets`, `travel_requests`, `bookings` |

### Service Layer Pattern
```
Controller → FormRequest → Service → Repository/Model
     ↓            ↓           ↓
  Thin        Validation   Business
  Layer         Layer       Logic
```

### Dependency Injection

All services are bound via interfaces in `DomainServiceProvider`:
```php
WalletServiceInterface::class => WalletService::class
TravelRequestServiceInterface::class => TravelRequestService::class
BookingServiceInterface::class => BookingService::class
TenantServiceInterface::class => TenantService::class
EmployeeServiceInterface::class => EmployeeService::class
```

---

## Requirements

- Docker & Docker Compose
- Make (optional)

---

## Installation

### Quick Start
```bash
git clone <repository-url>
cd macqueen-travel-platform
make install
```

### Manual Setup
```bash
# Build and start containers
docker-compose up -d --build

# Install dependencies
docker-compose exec app composer install

# Setup environment
docker-compose exec app cp .env.example .env
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate
```

### Access

| Service | URL |
|---------|-----|
| Application | http://localhost:8847 |
| Horizon Dashboard | http://localhost:8847/horizon |

### Environment Variables
```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=macqueen
DB_USERNAME=macqueen_user
DB_PASSWORD=secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=redis
REDIS_PORT=6379
```

---

## API Documentation

### Authentication

All endpoints except `/register` and `/login` require Bearer token.
```
Authorization: Bearer <token>
```

### Endpoints

#### Auth

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/register | Register new user |
| POST | /api/login | Login user |
| POST | /api/logout | Logout user |
| GET | /api/me | Get current user |

#### Tenants

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/tenants | List all tenants |
| POST | /api/tenants | Create tenant |
| GET | /api/tenants/{id} | Get tenant |
| PUT | /api/tenants/{id} | Update tenant |
| POST | /api/tenants/{id}/activate | Activate tenant |
| POST | /api/tenants/{id}/deactivate | Deactivate tenant |

#### Employees

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/employees | List employees |
| POST | /api/employees | Create employee |
| GET | /api/employees/{id} | Get employee |
| PUT | /api/employees/{id} | Update employee |
| POST | /api/employees/{id}/activate | Activate employee |
| POST | /api/employees/{id}/deactivate | Deactivate employee |

#### Wallet

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/wallet | Get wallet balance |
| POST | /api/wallet/credit | Add funds |
| POST | /api/wallet/debit | Deduct funds |

#### Travel Requests

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/travel-requests | List requests |
| POST | /api/travel-requests | Create request |
| GET | /api/travel-requests/{id} | Get request |
| POST | /api/travel-requests/{id}/approve | Approve request |
| POST | /api/travel-requests/{id}/reject | Reject request |
| POST | /api/travel-requests/{id}/cancel | Cancel request |

#### Bookings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/bookings | List bookings |
| POST | /api/bookings | Create booking |
| GET | /api/bookings/{id} | Get booking |
| POST | /api/bookings/{id}/confirm | Confirm booking |
| POST | /api/bookings/{id}/cancel | Cancel booking |

### Request Examples

#### Register
```bash
curl -X POST http://localhost:8847/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "tenant_id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "role": "admin"
  }'
```

#### Login
```bash
curl -X POST http://localhost:8847/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

#### Create Travel Request
```bash
curl -X POST http://localhost:8847/api/travel-requests \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 1,
    "type": "flight",
    "destination": "New York",
    "departure_date": "2025-02-01",
    "return_date": "2025-02-05",
    "estimated_cost": 1500.00,
    "purpose": "Business meeting"
  }'
```

#### Wallet Credit
```bash
curl -X POST http://localhost:8847/api/wallet/credit \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 5000.00,
    "description": "Initial deposit",
    "idempotency_key": "unique-key-123"
  }'
```

---

## Security

### Implemented Protections

| Threat | Protection |
|--------|------------|
| IDOR | `EnsureTenantOwnership` middleware |
| Cross-tenant leakage | `BelongsToTenant` trait with global scope |
| Mass Assignment | `$fillable` whitelist in all models |
| Privilege Escalation | `PreventPrivilegeEscalation` middleware |
| Brute Force | Rate limiting per endpoint |
| Unauthorized Access | Sanctum authentication + Policies |

### Authorization Roles

| Role | Permissions |
|------|-------------|
| Admin | Full access, manage wallet, activate/deactivate |
| Manager | Approve requests, create bookings, manage employees |
| Employee | View own data, create travel requests |

---

## Performance

### Caching Strategy

| Data | TTL | Purpose |
|------|-----|---------|
| Wallet | 1 hour | Reduce DB queries |
| Tenant | 1 hour | Quick resolution |

### Rate Limiting

| Endpoint Group | Limit |
|----------------|-------|
| Auth | 5/minute |
| API | 60/minute |
| Wallet | 10/minute |
| Booking | 20/minute |

### Queue Strategy

- Redis-backed queues via Laravel Horizon
- Booking processing in background
- Notification dispatch async
- 3 retries with 60s backoff

### Database Optimization

- Indexes on `tenant_id`, `status`, `email`
- Eager loading for relationships
- Cursor pagination for large datasets

---

## Useful Commands
```bash
make help         # Show all commands
make shell        # Access container
make logs         # View logs
make fresh        # Reset database
make horizon      # Start queue worker
make clear        # Clear caches
```
