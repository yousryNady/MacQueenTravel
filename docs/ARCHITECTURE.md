# MacQueen Travel Platform - Architecture Documentation

## System Overview
```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT LAYER                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│  Web App    │    Mobile App    │    Third Party    │    Admin Panel         │
└──────┬──────┴────────┬─────────┴─────────┬─────────┴──────────┬─────────────┘
       │               │                   │                    │
       └───────────────┴─────────┬─────────┴────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              API GATEWAY                                     │
├─────────────────────────────────────────────────────────────────────────────┤
│  Rate Limiting  │  Authentication  │  Tenant Resolution  │  Request Logging │
└─────────────────┴────────┬─────────┴────────────────────┴───────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           APPLICATION LAYER                                  │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   Tenant    │  │  Employee   │  │   Travel    │  │   Booking   │        │
│  │ Controller  │  │ Controller  │  │ Controller  │  │ Controller  │        │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘        │
│         │                │                │                │                │
│         ▼                ▼                ▼                ▼                │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   Tenant    │  │  Employee   │  │   Travel    │  │   Booking   │        │
│  │  Service    │  │  Service    │  │  Service    │  │  Service    │        │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘        │
│         │                │                │                │                │
│         └────────────────┴────────┬───────┴────────────────┘                │
│                                   │                                          │
│                                   ▼                                          │
│                          ┌───────────────┐                                   │
│                          │    Wallet     │                                   │
│                          │   Service     │                                   │
│                          └───────────────┘                                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            DATA LAYER                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │   MySQL     │  │   Redis     │  │   Redis     │  │  External   │        │
│  │  Database   │  │   Cache     │  │   Queue     │  │  Providers  │        │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘        │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Domain Structure
```
app/
├── Domain/
│   ├── Tenant/
│   │   ├── Models/
│   │   │   └── Tenant.php
│   │   ├── Services/
│   │   │   └── TenantService.php
│   │   ├── Contracts/
│   │   │   └── TenantServiceInterface.php
│   │   ├── Traits/
│   │   │   └── BelongsToTenant.php
│   │   └── TenantManager.php
│   │
│   ├── Employee/
│   │   ├── Models/
│   │   │   └── Employee.php
│   │   ├── Services/
│   │   │   └── EmployeeService.php
│   │   └── Contracts/
│   │       └── EmployeeServiceInterface.php
│   │
│   ├── Travel/
│   │   ├── Models/
│   │   │   └── TravelRequest.php
│   │   ├── Services/
│   │   │   └── TravelRequestService.php
│   │   └── Contracts/
│   │       └── TravelRequestServiceInterface.php
│   │
│   ├── Wallet/
│   │   ├── Models/
│   │   │   ├── Wallet.php
│   │   │   └── WalletTransaction.php
│   │   ├── Services/
│   │   │   └── WalletService.php
│   │   └── Contracts/
│   │       └── WalletServiceInterface.php
│   │
│   ├── Booking/
│   │   ├── Models/
│   │   │   └── Booking.php
│   │   ├── Services/
│   │   │   └── BookingService.php
│   │   └── Contracts/
│   │       └── BookingServiceInterface.php
│   │
│   └── Shared/
│       ├── Models/
│       │   └── Lock.php
│       ├── Services/
│       │   └── LockService.php
│       └── Traits/
│           └── QueryOptimization.php
│
├── Http/
│   ├── Controllers/Api/
│   ├── Middleware/
│   ├── Requests/
│   └── Traits/
│
├── Jobs/
│   ├── Booking/
│   └── Notification/
│
├── Policies/
├── Providers/
└── Services/
```

## Multi-Tenancy Architecture
```
┌─────────────────────────────────────────────────────────────────┐
│                      REQUEST FLOW                                │
└─────────────────────────────────────────────────────────────────┘

  Request                                                Response
     │                                                       ▲
     ▼                                                       │
┌─────────┐    ┌─────────┐    ┌─────────┐    ┌─────────┐    │
│  Rate   │───▶│  Auth   │───▶│ Tenant  │───▶│ Owner-  │────┘
│ Limiter │    │Sanctum  │    │Resolver │    │  ship   │
└─────────┘    └─────────┘    └─────────┘    └─────────┘


┌─────────────────────────────────────────────────────────────────┐
│                   TENANT RESOLUTION                              │
└─────────────────────────────────────────────────────────────────┘

  Priority 1: Authenticated User
  ┌─────────────────────────────────────────┐
  │  if ($request->user()->tenant_id)       │
  │      return $user->tenant;              │
  └─────────────────────────────────────────┘
            │
            ▼ (fallback)
  Priority 2: Request Header
  ┌─────────────────────────────────────────┐
  │  if ($request->hasHeader('X-Tenant-ID'))│
  │      return Tenant::find($header);      │
  └─────────────────────────────────────────┘
            │
            ▼ (fallback)
  Priority 3: Subdomain
  ┌─────────────────────────────────────────┐
  │  $subdomain = explode('.', $host)[0];   │
  │  return Tenant::where('slug',$subdomain)│
  └─────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                   DATA ISOLATION                                 │
└─────────────────────────────────────────────────────────────────┘

  BelongsToTenant Trait:
  
  ┌─────────────────────────────────────────────────────────────┐
  │  Global Scope (Auto Filter)                                  │
  │  ─────────────────────────────────────────────────────────  │
  │  SELECT * FROM employees WHERE tenant_id = ?                 │
  │                                                              │
  │  Creating Hook (Auto Assign)                                 │
  │  ─────────────────────────────────────────────────────────  │
  │  $model->tenant_id = TenantManager::id();                   │
  └─────────────────────────────────────────────────────────────┘
```

## Database Schema
```
┌─────────────────────────────────────────────────────────────────┐
│                      SHARED TABLES                               │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────┐
│    tenants      │
├─────────────────┤
│ id              │
│ name            │
│ slug (unique)   │
│ domain          │
│ is_active       │
│ timestamps      │
└─────────────────┘

┌─────────────────┐
│     users       │
├─────────────────┤
│ id              │
│ tenant_id (FK)  │───────┐
│ name            │       │
│ email           │       │
│ password        │       │
│ role            │       │
│ timestamps      │       │
└─────────────────┘       │
                          │
┌─────────────────────────┴───────────────────────────────────────┐
│                     TENANT TABLES                                │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────┐       ┌─────────────────┐
│   employees     │       │    wallets      │
├─────────────────┤       ├─────────────────┤
│ id              │       │ id              │
│ tenant_id (FK)  │       │ tenant_id (FK)  │
│ first_name      │       │ balance         │
│ last_name       │       │ currency        │
│ email           │       │ timestamps      │
│ phone           │       └────────┬────────┘
│ department      │                │
│ position        │                │
│ is_active       │                ▼
│ timestamps      │       ┌─────────────────┐
└────────┬────────┘       │wallet_transactions
         │                ├─────────────────┤
         │                │ id              │
         ▼                │ wallet_id (FK)  │
┌─────────────────┐       │ tenant_id (FK)  │
│ travel_requests │       │ idempotency_key │
├─────────────────┤       │ type            │
│ id              │       │ amount          │
│ tenant_id (FK)  │       │ balance_before  │
│ employee_id(FK) │       │ balance_after   │
│ approved_by(FK) │       │ description     │
│ type            │       │ timestamps      │
│ status          │       └─────────────────┘
│ destination     │
│ departure_date  │
│ return_date     │
│ estimated_cost  │
│ purpose         │
│ timestamps      │
└────────┬────────┘
         │
         ▼
┌─────────────────┐       ┌─────────────────┐
│    bookings     │       │     locks       │
├─────────────────┤       ├─────────────────┤
│ id              │       │ id              │
│ tenant_id (FK)  │       │ lockable_type   │
│ travel_req(FK)  │       │ lockable_id     │
│ employee_id(FK) │       │ lock_key        │
│ type            │       │ owner           │
│ status          │       │ expires_at      │
│ provider        │       │ timestamps      │
│ provider_ref    │       └─────────────────┘
│ amount          │
│ currency        │
│ provider_data   │
│ timestamps      │
└─────────────────┘
```

## Service Layer Pattern
```
┌─────────────────────────────────────────────────────────────────┐
│                    REQUEST LIFECYCLE                             │
└─────────────────────────────────────────────────────────────────┘

     ┌──────────────┐
     │   Request    │
     └──────┬───────┘
            │
            ▼
     ┌──────────────┐
     │  Middleware  │  Rate Limit, Auth, Tenant, Ownership
     └──────┬───────┘
            │
            ▼
     ┌──────────────┐
     │ FormRequest  │  Validation + Authorization
     └──────┬───────┘
            │
            ▼
     ┌──────────────┐
     │  Controller  │  Thin Layer - No Business Logic
     └──────┬───────┘
            │
            ▼
     ┌──────────────┐
     │   Service    │  Business Logic + Transactions
     └──────┬───────┘
            │
            ▼
     ┌──────────────┐
     │    Model     │  Eloquent + Relationships
     └──────┬───────┘
            │
            ▼
     ┌──────────────┐
     │   Database   │
     └──────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                DEPENDENCY INJECTION                              │
└─────────────────────────────────────────────────────────────────┘

  DomainServiceProvider
  ┌─────────────────────────────────────────────────────────────┐
  │                                                              │
  │  WalletServiceInterface      ──▶  WalletService             │
  │  TravelRequestServiceInterface ──▶ TravelRequestService     │
  │  BookingServiceInterface     ──▶  BookingService            │
  │  TenantServiceInterface      ──▶  TenantService             │
  │  EmployeeServiceInterface    ──▶  EmployeeService           │
  │                                                              │
  └─────────────────────────────────────────────────────────────┘

  Controller Usage:
  ┌─────────────────────────────────────────────────────────────┐
  │                                                              │
  │  public function __construct(                               │
  │      private WalletServiceInterface $walletService          │
  │  ) {}                                                       │
  │                                                              │
  └─────────────────────────────────────────────────────────────┘
```

## Security Architecture
```
┌─────────────────────────────────────────────────────────────────┐
│                   SECURITY LAYERS                                │
└─────────────────────────────────────────────────────────────────┘

  Layer 1: Rate Limiting
  ┌─────────────────────────────────────────────────────────────┐
  │  auth     : 5 requests/minute                               │
  │  api      : 60 requests/minute                              │
  │  wallet   : 10 requests/minute                              │
  │  booking  : 20 requests/minute                              │
  └─────────────────────────────────────────────────────────────┘
            │
            ▼
  Layer 2: Authentication (Sanctum)
  ┌─────────────────────────────────────────────────────────────┐
  │  Bearer Token Authentication                                │
  │  Token stored in personal_access_tokens table               │
  └─────────────────────────────────────────────────────────────┘
            │
            ▼
  Layer 3: Tenant Resolution
  ┌─────────────────────────────────────────────────────────────┐
  │  ResolveTenant Middleware                                   │
  │  Sets TenantManager::$current                               │
  └─────────────────────────────────────────────────────────────┘
            │
            ▼
  Layer 4: Tenant Ownership (IDOR Prevention)
  ┌─────────────────────────────────────────────────────────────┐
  │  EnsureTenantOwnership Middleware                           │
  │  Checks $model->tenant_id === $user->tenant_id              │
  └─────────────────────────────────────────────────────────────┘
            │
            ▼
  Layer 5: Privilege Escalation Prevention
  ┌─────────────────────────────────────────────────────────────┐
  │  PreventPrivilegeEscalation Middleware                      │
  │  Cannot assign role higher than your own                    │
  └─────────────────────────────────────────────────────────────┘
            │
            ▼
  Layer 6: Authorization (Policies)
  ┌─────────────────────────────────────────────────────────────┐
  │  TravelRequestPolicy                                        │
  │  BookingPolicy                                              │
  │  EmployeePolicy                                             │
  │  WalletPolicy                                               │
  └─────────────────────────────────────────────────────────────┘
            │
            ▼
  Layer 7: Mass Assignment Protection
  ┌─────────────────────────────────────────────────────────────┐
  │  $fillable whitelist in all models                          │
  └─────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                   ROLE HIERARCHY                                 │
└─────────────────────────────────────────────────────────────────┘

                    ┌─────────┐
                    │  Admin  │  Full Access
                    └────┬────┘
                         │
                         ▼
                    ┌─────────┐
                    │ Manager │  Approve, Create, Manage
                    └────┬────┘
                         │
                         ▼
                    ┌─────────┐
                    │Employee │  View Own, Create Requests
                    └─────────┘
```

## Caching & Performance
```
┌─────────────────────────────────────────────────────────────────┐
│                   CACHING STRATEGY                               │
└─────────────────────────────────────────────────────────────────┘

  Cache Keys Pattern:
  ┌─────────────────────────────────────────────────────────────┐
  │  tenant:{tenant_id}:wallet     TTL: 1 hour                  │
  │  tenant:{tenant_id}:employees  TTL: 15 minutes              │
  └─────────────────────────────────────────────────────────────┘

  Cache Invalidation:
  ┌─────────────────────────────────────────────────────────────┐
  │  On wallet transaction  ──▶  Clear wallet cache             │
  │  On employee update     ──▶  Clear employee cache           │
  └─────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                   QUEUE ARCHITECTURE                             │
└─────────────────────────────────────────────────────────────────┘

     ┌──────────────┐
     │   Request    │
     └──────┬───────┘
            │
            ▼
     ┌──────────────┐     ┌──────────────┐
     │  Dispatch    │────▶│    Redis     │
     │    Job       │     │    Queue     │
     └──────────────┘     └──────┬───────┘
                                 │
                                 ▼
                          ┌──────────────┐
                          │   Horizon    │
                          │   Worker     │
                          └──────┬───────┘
                                 │
            ┌────────────────────┼────────────────────┐
            ▼                    ▼                    ▼
     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
     │   Process    │     │    Send      │     │   External   │
     │   Booking    │     │ Notification │     │   Provider   │
     └──────────────┘     └──────────────┘     └──────────────┘
```

## Concurrency Handling
```
┌─────────────────────────────────────────────────────────────────┐
│              WALLET TRANSACTION SAFETY                           │
└─────────────────────────────────────────────────────────────────┘

  Step 1: Check Idempotency
  ┌─────────────────────────────────────────────────────────────┐
  │  if (idempotency_key exists)                                │
  │      return existing transaction                            │
  └─────────────────────────────────────────────────────────────┘
            │
            ▼
  Step 2: Acquire Lock
  ┌─────────────────────────────────────────────────────────────┐
  │  Cache::lock("wallet:{id}:lock", 10)                        │
  │      ->block(5, function() { ... })                         │
  └─────────────────────────────────────────────────────────────┘
            │
            ▼
  Step 3: Database Transaction
  ┌─────────────────────────────────────────────────────────────┐
  │  DB::transaction(function() {                               │
  │      $wallet = Wallet::lockForUpdate()->find($id);          │
  │      // Update balance                                      │
  │      // Create transaction record                           │
  │  });                                                        │
  └─────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│               BOOKING LOCK MECHANISM                             │
└─────────────────────────────────────────────────────────────────┘

  LockService::executeWithLock()
  ┌─────────────────────────────────────────────────────────────┐
  │                                                              │
  │  1. Generate lock_key: "Model:id:action"                    │
  │  2. Check if lock exists and not expired                    │
  │  3. Create lock record with owner UUID                      │
  │  4. Execute callback                                        │
  │  5. Release lock                                            │
  │                                                              │
  └─────────────────────────────────────────────────────────────┘
```

## API Endpoints Summary
```
┌─────────────────────────────────────────────────────────────────┐
│                    PUBLIC ENDPOINTS                              │
└─────────────────────────────────────────────────────────────────┘

  POST   /api/register          Register new user
  POST   /api/login             Login user

  GET    /api/tenants           List tenants
  POST   /api/tenants           Create tenant
  GET    /api/tenants/{id}      Get tenant
  PUT    /api/tenants/{id}      Update tenant


┌─────────────────────────────────────────────────────────────────┐
│                 AUTHENTICATED ENDPOINTS                          │
└─────────────────────────────────────────────────────────────────┘

  POST   /api/logout            Logout user
  GET    /api/me                Get current user

  ──────────────── EMPLOYEES ────────────────
  GET    /api/employees                 List employees
  POST   /api/employees                 Create employee
  GET    /api/employees/{id}            Get employee
  PUT    /api/employees/{id}            Update employee
  POST   /api/employees/{id}/activate   Activate employee
  POST   /api/employees/{id}/deactivate Deactivate employee

  ──────────────── WALLET ────────────────
  GET    /api/wallet            Get wallet balance
  POST   /api/wallet/credit     Add funds
  POST   /api/wallet/debit      Deduct funds

  ──────────────── TRAVEL REQUESTS ────────────────
  GET    /api/travel-requests              List requests
  POST   /api/travel-requests              Create request
  GET    /api/travel-requests/{id}         Get request
  POST   /api/travel-requests/{id}/approve Approve request
  POST   /api/travel-requests/{id}/reject  Reject request
  POST   /api/travel-requests/{id}/cancel  Cancel request

  ──────────────── BOOKINGS ────────────────
  GET    /api/bookings                  List bookings
  POST   /api/bookings                  Create booking
  GET    /api/bookings/{id}             Get booking
  POST   /api/bookings/{id}/confirm     Confirm booking
  POST   /api/bookings/{id}/cancel      Cancel booking
```
