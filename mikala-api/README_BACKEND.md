# Mikala Backend - Laravel 11 API

## ✅ Project Status: FOUNDATION COMPLETE

**Built:** May 9, 2026  
**Laravel Version:** 11.x  
**PHP Version:** 8.2+  
**Database:** MySQL 8.0

---

## 🎯 What's Built

### ✅ Phase 1: Database Setup
- [x] MySQL database `mikala_db` created
- [x] Connection configured and tested

### ✅ Phase 2: Migrations (13 Tables)
- [x] `users` - Multi-role authentication
- [x] `mitra` - Perawat/nurses profiles
- [x] `klien` - Clients (individu & institusi)
- [x] `pasien` - Patients linked to klien
- [x] `agen` - Institution agents
- [x] `orders` - Service orders
- [x] `tagihan` - Billing/invoicing
- [x] `payroll` - Mitra payments
- [x] `trainings` - Training records
- [x] `notifikasi` - In-app notifications
- [x] `feedback` - Ratings & reviews
- [x] `leads` - Marketing leads
- [x] `jurnal_keuangan` - Financial journal

### ✅ Phase 3: Models (13 Models)
All models created with:
- Proper relationships (hasMany, belongsTo, etc)
- Fillable fields
- Casts (dates, decimals, JSON)
- Scopes (active, pending, etc)
- Helper methods

### ✅ Phase 4: Authentication (Sanctum)
- [x] Laravel Sanctum installed
- [x] Multi-role authentication system
- [x] AuthController (login, logout, me, refresh)
- [x] Role-based middleware (`CheckRole`, `CheckDivision`)
- [x] Token-based API authentication

### ✅ Phase 5: Controllers (15+ Controllers)
#### Internal (Staff)
- `DashboardController`
- `RekrutmenController`
- `TrainingController`
- `CustomerCareController`
- `FinanceController`
- `MarketingController`

#### Mitra
- `MitraProfileController`
- `MitraJobController`
- `MitraPayrollController`

#### Klien
- `KlienProfileController`
- `KlienLayananController`
- `KlienBillingController`

#### Public
- `MGMController` (website)
- `MGAController` (akademi)

#### Shared
- `NotifikasiController`

### ✅ Phase 6: API Routes (104 Endpoints)
Complete routing structure for:
- Auth endpoints (public + protected)
- Internal staff routes (role: internal)
- Mitra routes (role: mitra)
- Klien routes (role: klien)
- Public routes (no auth)
- Shared routes (authenticated)

### ✅ Phase 7: Services (4 Service Classes)
- `NotifikasiService` - Push notifications logic
- `BillingService` - Invoice generation & reminders
- `PayrollService` - Payroll calculation & approval
- `ReportService` - Generate various reports

### ✅ Phase 8: Jobs & Scheduler
- `SendBillingReminderJob` - Daily reminders (H-7, H-3, H-1, overdue)
- `SendPushNotifikasiJob` - Queue push notifications
- `GenerateReportJob` - Background report generation
- Scheduler configured (runs daily at 08:00)

### ✅ Phase 9: Seeders
Complete test data:
- 1 Admin (manajemen)
- 1 Finance staff
- 1 Customer Care staff
- 2 Mitra (nurses)
- 2 Klien (1 individu, 1 rumah sakit)
- 1 Pasien
- 1 Agen (institution)
- 1 Order (in progress)
- 1 Tagihan (unpaid)

### ✅ Phase 10: Testing & Validation
- [x] Migrations run successfully
- [x] Seeders populate test data
- [x] Authentication tested (login works)
- [x] 104 API routes registered
- [x] Laravel server runs

---

## 🔑 Test Credentials

```
Admin (Full Access):
Email: admin@mikala.com
Password: password

Finance Staff:
Email: finance@mikala.com
Password: password

Customer Care:
Email: cc@mikala.com
Password: password

Mitra 1 (Nurse - Available):
Email: siti@example.com
Password: password

Mitra 2 (Nurse - On Job):
Email: budi@example.com
Password: password

Klien 1 (Individual):
Email: aminah@example.com
Password: password

Klien 2 (Hospital):
Email: admin@rsharapansehat.com
Password: password

Agen (Institution):
Email: agen@pantisejahtera.com
Password: password
```

---

## 🚀 Quick Start

### 1. Database Configuration
Already configured in `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mikala_db
DB_USERNAME=root
DB_PASSWORD=mikala2026
```

### 2. Run Migrations
```bash
php artisan migrate:fresh
```

### 3. Seed Test Data
```bash
php artisan db:seed
```

### 4. Start Server
```bash
php artisan serve
```

### 5. Test Authentication
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@mikala.com","password":"password"}'
```

Response:
```json
{
  "message": "Login successful",
  "token": "1|xxxxx...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Admin Mikala",
    "email": "admin@mikala.com",
    "role": "manajemen",
    "status": "active"
  }
}
```

### 6. Test Protected Endpoint
```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## 📋 API Endpoints Overview

### Public (No Auth)
- `POST /api/auth/login`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/public/layanan`
- `GET /api/public/about`
- `POST /api/public/leads`
- `GET /api/mga/program-pelatihan`
- `POST /api/mga/daftar-pelatihan`

### Protected (Requires Auth)
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `POST /api/auth/refresh`

### Internal Staff Routes (`/api/internal/*`)
**Dashboard:**
- `GET /internal/dashboard/summary`
- `GET /internal/dashboard/notifikasi`

**Rekrutmen:**
- Resource: `/internal/rekrutmen/mitra` (index, store, show, update, destroy)
- `GET /internal/rekrutmen/report`
- `GET /internal/rekrutmen/report/mitra-baru`
- `GET /internal/rekrutmen/report/mitra-keluar`
- `GET /internal/rekrutmen/report/agen-institusi`

**Training:**
- `GET /internal/training/mitra`
- `GET /internal/training/mitra/{id}`
- `POST /internal/training/mitra/{id}/checklist`
- `POST /internal/training/mitra/{id}/feedback`
- `PATCH /internal/training/mitra/{id}/status`
- `GET /internal/training/report`
- `GET /internal/training/report/available`
- `GET /internal/training/report/on-job`
- `GET /internal/training/report/re-training`

**Customer Care:**
- `POST /internal/cc/registrasi/klien`
- `POST /internal/cc/registrasi/pasien`
- `GET /internal/cc/klien`
- `GET /internal/cc/klien/{id}`
- `PATCH /internal/cc/klien/{id}`
- `GET /internal/cc/mitra`
- `GET /internal/cc/layanan`
- `POST /internal/cc/layanan`
- `PATCH /internal/cc/layanan/{id}/status`
- Reports: handling, deal, loss, cc-rating

**Finance:**
- Resource: `/internal/finance/tagihan`
- Resource: `/internal/finance/jurnal`
- Payroll endpoints
- Reports: income, outcome, saldo, piutang, utang

**Marketing:**
- Resource: `/internal/marketing/leads`
- Resource: `/internal/marketing/kerjasama`
- Reports: order-in, deal, gap-analysis

### Mitra Routes (`/api/mitra/*`)
- `GET /mitra/profile`
- `PATCH /mitra/profile`
- `GET /mitra/jobs`
- `GET /mitra/jobs/{id}`
- `PATCH /mitra/jobs/{id}/status`
- `GET /mitra/payroll`
- `GET /mitra/payroll/{id}`
- `GET /mitra/notifikasi`

### Klien Routes (`/api/klien/*`)
- `GET /klien/profile`
- `PATCH /klien/profile`
- `GET /klien/pasien`
- `GET /klien/layanan`
- `GET /klien/layanan/{id}`
- `GET /klien/tagihan`
- `POST /klien/tagihan/{id}/bayar`
- `GET /klien/mitra`
- `POST /klien/feedback`
- `GET /klien/notifikasi`

### Shared Routes
- `GET /api/notifikasi`
- `PATCH /api/notifikasi/{id}/read`

---

## 📁 Project Structure

```
mikala-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/
│   │   │   │   └── AuthController.php
│   │   │   ├── Internal/
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── RekrutmenController.php
│   │   │   │   ├── TrainingController.php
│   │   │   │   ├── CustomerCareController.php
│   │   │   │   ├── FinanceController.php
│   │   │   │   └── MarketingController.php
│   │   │   ├── Mitra/
│   │   │   │   ├── MitraProfileController.php
│   │   │   │   ├── MitraJobController.php
│   │   │   │   └── MitraPayrollController.php
│   │   │   ├── Klien/
│   │   │   │   ├── KlienProfileController.php
│   │   │   │   ├── KlienLayananController.php
│   │   │   │   └── KlienBillingController.php
│   │   │   ├── Public/
│   │   │   │   ├── MGMController.php
│   │   │   │   └── MGAController.php
│   │   │   └── NotifikasiController.php
│   │   └── Middleware/
│   │       ├── CheckRole.php
│   │       └── CheckDivision.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Mitra.php
│   │   ├── Klien.php
│   │   ├── Pasien.php
│   │   ├── Agen.php
│   │   ├── Order.php
│   │   ├── Tagihan.php
│   │   ├── Payroll.php
│   │   ├── Training.php
│   │   ├── Notifikasi.php
│   │   ├── Feedback.php
│   │   ├── Leads.php
│   │   └── JurnalKeuangan.php
│   ├── Services/
│   │   ├── NotifikasiService.php
│   │   ├── BillingService.php
│   │   ├── PayrollService.php
│   │   └── ReportService.php
│   └── Jobs/
│       ├── SendBillingReminderJob.php
│       ├── SendPushNotifikasiJob.php
│       └── GenerateReportJob.php
├── database/
│   ├── migrations/
│   │   ├── 2024_01_01_000001_create_users_table.php
│   │   ├── 2024_01_01_000002_create_mitra_table.php
│   │   ├── 2024_01_01_000003_create_klien_table.php
│   │   ├── ... (13 migrations total)
│   │   └── 2024_01_01_000013_create_jurnal_keuangan_table.php
│   └── seeders/
│       └── DatabaseSeeder.php
└── routes/
    ├── api.php (104 endpoints)
    └── console.php (scheduler)
```

---

## 🔧 Next Steps (Implementation Required)

### 1. Controller Implementation
All controllers are created as **skeletons**. Each needs:
- Business logic
- Validation (Form Requests)
- Resource responses
- Error handling

Example:
```php
// Current (skeleton)
public function store(Request $request) {
    //
}

// Needs implementation:
public function store(MitraRequest $request) {
    $validated = $request->validated();
    $mitra = Mitra::create($validated);
    return new MitraResource($mitra);
}
```

### 2. Form Requests
Create validation classes:
```bash
php artisan make:request MitraRequest
php artisan make:request KlienRequest
php artisan make:request BillingRequest
# ... etc
```

### 3. API Resources
Transform model responses:
```bash
php artisan make:resource MitraResource
php artisan make:resource KlienResource
php artisan make:resource OrderResource
# ... etc
```

### 4. Push Notifications
Integrate Firebase Cloud Messaging (FCM):
- Install `kreait/firebase-php`
- Configure FCM credentials
- Implement in `NotifikasiService::sendPushNotification()`

### 5. Email Service
Configure mail driver:
- Update `.env` mail settings
- Create Mailable classes
- Implement in `NotifikasiService::sendEmailNotification()`

### 6. File Upload
Implement file storage:
- Configure AWS S3 or Cloudflare R2
- Handle uploads in controllers
- Store file paths in models

### 7. Queue Worker
Run queue worker for jobs:
```bash
php artisan queue:work
```

Or setup with Supervisor in production.

### 8. Scheduler
Setup cron job:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### 9. Testing
Write unit & feature tests:
```bash
php artisan make:test AuthTest
php artisan make:test MitraTest
php artisan test
```

### 10. API Documentation
Generate API docs using:
- Laravel Scribe (`scribe-org/laravel-scribe`)
- Or Postman collection export

---

## 🔒 Security Checklist

- [x] Sanctum token authentication
- [x] Role-based access control
- [x] Password hashing (bcrypt)
- [x] SQL injection protection (Eloquent)
- [ ] Rate limiting (add to routes)
- [ ] CORS configuration
- [ ] Environment variables secured
- [ ] Production `.env` secured
- [ ] HTTPS enforced (production)

---

## 📊 Database Schema

All tables created with:
- **Primary keys:** Auto-increment `id`
- **Foreign keys:** With cascade/set null
- **Timestamps:** `created_at`, `updated_at`
- **Soft deletes:** On most tables
- **Indexes:** For performance (status, dates, foreign keys)

### Key Relationships:
```
User 1:1 Mitra/Klien/Agen
Klien 1:N Pasien
Klien 1:N Orders
Order N:1 Mitra, Klien, Pasien
Order 1:N Tagihan
Order 1:1 Feedback
Mitra 1:N Orders, Payroll, Trainings, Feedback
```

---

## 🐛 Known Limitations

1. **Controllers are skeletons** - Need business logic implementation
2. **No validation** - Form Requests need to be created
3. **No push notifications** - FCM integration pending
4. **No email sending** - Mail service not configured
5. **No file uploads** - Storage not configured
6. **No API documentation** - Needs generation
7. **No tests** - Unit/feature tests not written

---

## 📝 Development Notes

### Password Reset Flow
Currently placeholder. To implement:
1. Generate password reset token
2. Send email with reset link
3. Validate token on reset
4. Update password

### Billing Reminders
Scheduler configured to run daily at 08:00.
Sends notifications at H-7, H-3, H-1, and overdue.

### Multi-Role System
9 roles supported:
- Internal: manajemen, customer_care, training_center, rekrutmen, finance, marketing
- External: mitra, klien, agen

### API Response Format
Standardize all responses:
```json
{
  "success": true,
  "message": "Operation successful",
  "data": {...},
  "meta": {...}
}
```

---

## 🎉 Summary

**Foundation Status: 100% COMPLETE**

✅ Database: 13 tables  
✅ Models: 13 models with relationships  
✅ Authentication: Multi-role Sanctum  
✅ Controllers: 15+ controllers  
✅ Routes: 104 API endpoints  
✅ Services: 4 service classes  
✅ Jobs: 3 background jobs  
✅ Seeders: Complete test data  
✅ Tested: Login & basic API working  

**Ready for:** Frontend integration & controller implementation

---

## 📞 Support

For questions or issues, contact the development team.

**Built with ❤️ by JON 🔥**
