# EgovService — Complete Rebuild Documentation

> Read this file entirely before writing any code. It contains everything needed to rebuild this project from scratch.

---

## 1. Project Overview

**What it is:** A Laravel REST API that acts as an integration proxy between a client application (InvestAZ mobile app) and Azerbaijan's **AsanFinance** government API. It fetches identity, residence, and employment data for Azerbaijani citizens by FIN (personal identification number) and document number.

**Key behaviors:**
- Caches API responses in a local MySQL database for 7 days (configurable)
- Formats and normalizes raw government data into a consistent application format
- Monitors API balance hourly and emails admins when it drops below 500
- Tracks request counts per type for reporting
- Uses Laravel AI to detect country ISO code from a country name string (because AsanFinance returns country names, not codes)

---

## 2. Tech Stack

| Layer            | Technology                                   |
|------------------|----------------------------------------------|
| Framework        | Laravel 13 (latest stable)                   |
| PHP              | 8.4+ (latest stable)                         |
| Authentication   | Static API key (middleware)                  |
| Database         | MySQL 8 (external, NOT in Docker)            |
| Email            | AWS SES                                      |
| Error Tracking   | Sentry                                       |
| AI               | Laravel AI package (for country code lookup) |
| Containerization | Docker + Docker Compose (app + nginx only)   |
| HTTP Client      | Laravel Http (Guzzle)                        |

---

## 3. Architecture

Use **clean architecture** with these layers:

```
app/
├── Http/
│   ├── Controllers/          # Thin controllers — validate, call action, return response
│   ├── Requests/             # Form request validation
│   └── Middleware/
├── Actions/                  # One class per use case (replaces fat services)
│   ├── Identity/
│   │   ├── GetPersonalData.php
│   │   └── FormatIdentityData.php
│   ├── Residence/
│   │   ├── GetResidenceData.php
│   │   └── FormatResidenceData.php
│   └── Employee/
│       └── GetEmployeeData.php
├── Contracts/                # Interfaces for repositories
│   ├── IdentityRepositoryInterface.php
│   ├── ResidenceRepositoryInterface.php
│   ├── EmployeeRepositoryInterface.php
│   └── CountryRepositoryInterface.php
├── Repositories/             # Eloquent implementations
├── Models/
├── DTOs/                     # Data Transfer Objects (readonly classes)
├── Services/
│   └── AsanFinanceService.php   # Single external API client
├── Exceptions/
├── Mail/
└── Console/
```

### Key principles:
- Controllers only validate and delegate — no business logic
- Actions are single-responsibility classes with a `handle()` method
- DTOs are `readonly` PHP 8.2 classes for passing structured data
- Repositories implement interfaces — swap implementations without touching business logic
- `AsanFinanceService` is the only class that knows about the external API

---

## 4. Database Schema

> The database is external (not in Docker). Connection via env vars.
> Use Laravel migrations — **no raw SQL**. All migration files go in `database/migrations/`.

### Tables needed
| Table | Purpose |
|-------|---------|
| `countries` | ISO country data for code lookup |
| `identities` | Cached personal identity data from AsanFinance |
| `residences` | Cached residence permit data from AsanFinance |
| `employees` | Cached employment data from AsanFinance (stored as JSON) |
| `logs` | Request audit trail (type + PIN per request) |
| `migrations` | Laravel internal |

### Tables removed vs old schema
| Table | Reason |
|-------|--------|
| `users` | No user auth — replaced with static API key |
| `personal_access_tokens` | Was for Laravel Sanctum, not used |
| `password_resets` | No user auth |
| `failed_jobs` | No queued jobs in this project |

---

### Migration: `create_countries_table`
```php
Schema::create('countries', function (Blueprint $table) {
    $table->unsignedInteger('id')->autoIncrement()->primary();
    $table->unsignedSmallInteger('num_code')->unique();  // ISO 3166-1 numeric, used as country identifier
    $table->char('alpha_2', 2);                          // ISO 3166-1 alpha-2
    $table->string('alpha_3', 3)->nullable();            // ISO 3166-1 alpha-3 (useful, add it)
    $table->string('country_name', 100);
    $table->string('dialing_code', 10)->nullable();      // varchar: some codes are compound e.g. "1-242"
    $table->boolean('status')->default(true);
    $table->timestamps();
});
```

> `num_code` is the value returned in `format_data` fields (`client_nationality`, `client_birth_country`).
> Azerbaijan's `num_code` = **31**.

---

### Migration: `create_identities_table`
```php
Schema::create('identities', function (Blueprint $table) {
    $table->id();
    $table->string('PIN', 10)->unique();           // FIN code, max 7 chars in practice
    $table->string('DocumentSeria', 5)->nullable();
    $table->string('DocumentNumber', 20)->nullable();
    $table->string('Name', 100)->nullable();
    $table->string('Surname', 100)->nullable();
    $table->string('NameEn', 100)->nullable();
    $table->string('SurnameEn', 100)->nullable();
    $table->string('Patronymic', 100)->nullable();
    $table->string('BirthDate', 20)->nullable();   // stored as-is from API: "DD.MM.YYYY"
    $table->text('BirthAddress')->nullable();       // text: can be a full sentence
    $table->string('Gender', 10)->nullable();
    $table->text('RegistrationAddress')->nullable(); // text: plain string, can be long
    $table->string('GivenDate', 20)->nullable();
    $table->string('ActivationDate', 20)->nullable();
    $table->string('ExpireDate', 20)->nullable();
    $table->string('MaritalStatus', 30)->nullable();
    $table->string('GivenOrganization', 200)->nullable();
    $table->string('Citizenship', 100)->nullable();
    $table->longText('Image')->nullable();          // base64 encoded JPEG
    $table->longText('Sign')->nullable();           // base64 encoded signature image
    $table->string('MilitaryStatus', 50)->nullable();
    $table->string('BloodType', 5)->nullable();
    $table->string('EyeColor', 30)->nullable();
    $table->unsignedSmallInteger('Height')->nullable();
    $table->timestamps();
});
```

---

### Migration: `create_residences_table`
```php
Schema::create('residences', function (Blueprint $table) {
    $table->id();
    $table->string('PIN', 10)->unique();
    $table->string('Name', 100)->nullable();
    $table->string('Surname', 100)->nullable();
    $table->string('DocumentNumber', 20)->nullable();
    $table->string('DocumentType', 100)->nullable();
    $table->string('BirthDate', 20)->nullable();
    $table->text('BirthAddress')->nullable();
    $table->string('Gender', 10)->nullable();
    $table->json('RegistrationAddress')->nullable();  // JSON: {"City":"..","District":"..","Street":"..","Building":"..","Apt":".."}
    $table->string('ExpireDate', 20)->nullable();
    $table->string('GivenDate', 20)->nullable();
    $table->string('Citizenship', 100)->nullable();
    $table->longText('Image')->nullable();
    $table->timestamps();
});
```

> `RegistrationAddress` is stored as `json` type because AsanFinance returns it as a JSON string for residence records.

---

### Migration: `create_employees_table`
```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->string('pin', 10)->unique();
    $table->json('employee_data')->nullable();  // full AsanFinance Response object
    $table->timestamps();
});
```

---

### Migration: `create_logs_table`
```php
Schema::create('logs', function (Blueprint $table) {
    $table->id();
    $table->string('pin', 10)->index();
    $table->unsignedTinyInteger('type')->index(); // 1=personal, 2=employee, 3=residence
    $table->timestamps();                         // created_at used for reporting queries

    $table->index(['type', 'created_at']);        // composite index for yearlyReport() query
});
```

---

### Data Migration from Old Schema

Run these SQL statements **once** to move existing data into the new tables with correct types. Execute before dropping old tables.

```sql
-- identities: data is compatible, just re-insert to pick up new unique index and column sizes
-- If you already have the identities table with data, just add the missing index:
ALTER TABLE identities ADD UNIQUE INDEX identities_pin_unique (PIN);

-- residences: RegistrationAddress changes from varchar(255) to json.
-- The old column stored a JSON string already, so cast it:
ALTER TABLE residences MODIFY RegistrationAddress JSON NULL;
ALTER TABLE residences ADD UNIQUE INDEX residences_pin_unique (PIN);

-- employees: employee_data changes from longtext to json.
ALTER TABLE employees MODIFY employee_data JSON NULL;
ALTER TABLE employees ADD UNIQUE INDEX employees_pin_unique (pin);

-- logs: add composite index
ALTER TABLE logs ADD INDEX logs_type_created_at_index (type, created_at);

-- Drop unused tables (run only after confirming above succeeded)
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS personal_access_tokens;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS failed_jobs;
```

> **Note:** MySQL will validate existing data when converting `longtext` → `json`. If any row has malformed JSON in `employee_data` or `RegistrationAddress`, the ALTER will fail. Check with:
> ```sql
> SELECT id, pin FROM employees WHERE JSON_VALID(employee_data) = 0;
> SELECT id, PIN FROM residences WHERE JSON_VALID(RegistrationAddress) = 0;
> ```
> Fix or delete invalid rows before running the migration.

---

> No `users` table needed. Authentication is handled by a static API key — no user management required.

---

## 5. Environment Variables

```env
APP_NAME=EgovService
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=host.docker.internal    # or actual DB host
DB_PORT=3306
DB_DATABASE=egov
DB_USERNAME=egov
DB_PASSWORD=your_password

API_KEY=your_strong_random_secret_here   # clients send this as X-Api-Key header

MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@investaz.az
MAIL_FROM_NAME=InvestAZ
MAIL_DEBUG=dev@yourcompany.com  # all emails go here when APP_DEBUG=true

AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=eu-west-1

ASAN_FINANCE_KEY=your_api_key
ASAN_FINANCE_BASE_URI=https://asanfinance.gov.az
ASAN_FINANCE_REQUEST_TIMEOUT=10
ASAN_FINANCE_VERIFY_SSL_PEER=false

UPDATE_IDENTITY_AFTER_DAY=7     # cache TTL in days

SENTRY_LARAVEL_DSN=your_sentry_dsn

ANTHROPIC_API_KEY=your_claude_key  # for Laravel AI country detection
LOW_BALANCE_THRESHOLD=500
LOW_BALANCE_NOTIFY_EMAILS=admin1@company.com,admin2@company.com
```

> **Development mode:** When `APP_DEBUG=true` AND `APP_ENV=development`, the personal-info and employee-info endpoints return mock Azerbaijani Faker data instead of calling the real API.

---

## 6. API Endpoints

All endpoints (except `/report`) require the header: `X-Api-Key: {API_KEY}`

If the key is missing or wrong, return `401`:
```json
{ "code": 401, "message": "Unauthorized", "data": null }
```

### Authentication middleware (`ValidateApiKey`)
```php
// app/Http/Middleware/ValidateApiKey.php
if ($request->header('X-Api-Key') !== config('app.api_key')) {
    return response()->json(['code' => 401, 'message' => 'Unauthorized', 'data' => null], 401);
}
return $next($request);
```

Apply to all API routes via the `api` middleware group. No login/register endpoints needed.

---

### Response Envelope

**Every JSON response** (success or error) uses this structure:
```json
{
  "code": 200,
  "message": "Success",
  "data": { }
}
```

| Field | Type | Description |
|-------|------|-------------|
| `code` | integer | HTTP status code |
| `message` | string | Human-readable status message |
| `data` | object \| array \| null | Payload — `null` on errors |

Implement a base controller helper:
```php
// app/Http/Controllers/Controller.php
protected function success(mixed $data, string $message = 'Success', int $code = 200): JsonResponse
{
    return response()->json(['code' => $code, 'message' => $message, 'data' => $data], $code)
        ->header('Content-Type', 'application/json;charset=UTF-8');
}
```

Both exception classes render using the same envelope:
```php
// EgovException and UnreportableException
return response()->json([
    'code'    => $code,
    'message' => $this->getMessage(),
    'data'    => null,
], $code);
```

---

### Core Endpoints (all require `X-Api-Key` header)

#### POST `/api/personal-info`
Fetch citizen identity/passport data.

**Request:**
```json
{
  "fin": "1234567",        // required, exactly 7 alphanumeric characters
  "docNumber": "AA1234567" // optional, alphanumeric
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "raw": {
      "PIN": "1234567",
      "DocumentSeria": "AA",
      "DocumentNumber": "1234567",
      "Name": "VÜSAL",
      "Surname": "HÜSEYNOV",
      "NameEn": "VUSAL",
      "SurnameEn": "HUSEYNOV",
      "Patronymic": "Rauf oğlu",
      "BirthDate": "01.01.1990",
      "BirthAddress": "Bakı şəhəri",
      "Gender": "Kişi",
      "RegistrationAddress": "Bakı şəhəri, Nəsimi rayonu, ...",
      "GivenDate": "01.01.2020",
      "ActivationDate": "01.01.2020",
      "ExpireDate": "01.01.2030",
      "MaritalStatus": "Evli",
      "GivenOrganization": "Daxili İşlər Nazirliyi",
      "Citizenship": "Azerbaijan Republic",
      "Image": "base64string",
      "MilitaryStatus": "...",
      "BloodType": "A+",
      "EyeColor": "Qara",
      "Sign": "base64string",
      "Height": 175
    },
    "formatData": {
      "base64image": "base64string",
      "clientName": "Vüsal Rauf oğlu Hüseynov",
      "name": "Vüsal",
      "lastname": "Hüseynov",
      "patronymic": "rauf",              // stripped of "oğlu"/"qızı" suffix, lowercased
      "clientBirthDate": "1990-01-01",
      "clientBirthCountry": "31",        // ISO numeric code from countries table
      "clientBirthCity": "Bakı",
      "clientBirthDistrict": "",
      "citizenship": "Azerbaijan",       // cleaned: removes "Republic", "of"
      "clientCity": "Bakı",
      "clientDistrict": "Nəsimi",
      "clientStreet": "Hüsü Hacıyev",
      "clientBuilding": "5",
      "clientApt": "12",
      "clientPassportIssueAt": "2020-01-01",
      "clientPassportIssueOrganization": "Daxili İşlər Nazirliyi",
      "clientPassportExpiresAt": "2030-01-01",
      "clientPassportFin": "1234567",
      "clientPassportSerialNumber": "AA1234567",
      "clientGender": "1",              // "Kişi"→"1", "Qadın"→"2"
      "clientCountry": "31",            // always 31 (Azerbaijan)
      "clientMarital": "2",             // "Evli"→"2", "Boşanmış"→"3", "Dul"→"4", other→"1"
      "clientNationality": "31"         // country ID from countries table by citizenship name
    }
  }
}
```

**Error responses:**
```json
{ "code": 422, "message": "The fin field must be 7 characters.", "data": null }
{ "code": 400, "message": "API error message", "data": null }
{ "code": 450, "message": "MyGov tətbiqinə daxil olaraq...", "data": null }
{ "code": 500, "message": "Connection error #4001", "data": null }
```

> For 422 validation errors, `message` contains the first validation error string (not a nested errors object).

---

#### POST `/api/residence-info`
Fetch residence permit data (for non-citizens with residence permits).

**Request:**
```json
{
  "fin": "12345"    // required, 5-7 alphanumeric characters
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "raw": {
      "PIN": "12345",
      "Name": "JOHN",
      "Surname": "DOE",
      "DocumentNumber": "RES123456",
      "DocumentType": "Daimi yaşama icazə vəsiqəsi",
      "BirthDate": "01.01.1985",
      "BirthAddress": "Russia",
      "Gender": "Kişi",
      "RegistrationAddress": "{\"City\":\"Bakı\",\"District\":\"Nəsimi\",\"Street\":\"...\"}",
      "ExpireDate": "01.01.2025",
      "GivenDate": "01.01.2020",
      "Citizenship": "Russian Federation",
      "Image": "base64string"
    },
    "formatData": {
      "base64image": "base64string",
      "clientName": "John Doe",
      "name": "John",
      "lastname": "Doe",
      "patronymic": null,
      "clientBirthDate": "1985-01-01",
      "clientBirthCountry": "643",       // Russia numeric code
      "clientBirthCity": "Russia",
      "clientBirthDistrict": "",
      "citizenship": "Russian Federation",
      "clientCity": "Bakı",
      "clientDistrict": "Nəsimi",
      "clientStreet": "...",
      "clientBuilding": "...",
      "clientApt": "...",
      "clientPassportIssueAt": "2020-01-01",
      "clientPassportIssueOrganization": "Dövlət Miqrasiya Xidməti",  // hardcoded
      "clientPassportExpiresAt": "2025-01-01",
      "clientPassportFin": "12345",
      "clientPassportSerialNumber": "RES123456",
      "clientGender": "1",
      "clientCountry": "31",            // always 31 (Azerbaijan)
      "clientMarital": "1",             // always "1" for residence
      "clientNationality": "643",       // Russia numeric code
      "documentType": "DYI"             // "Daimi yaşama icazə vəsiqəsi"→"DYI", "Müvəqqəti yaşama icazə vəsiqəsi"→"MYI"
    }
  }
}
```

---

#### POST `/api/employee-info`
Fetch employment history data.

**Request:**
```json
{
  "fin": "1234567"    // required, 7 alphanumeric characters
}
```

**Response 200:**
```json
{
  "code": 200,
  "message": "Success",
  "data": {
    "Active": [
      {
        "Contract": {
          "Number": "CT-001",
          "Status": "Active",
          "EndDate": "2025-12-31",
          "SignDate": "2022-01-01",
          "BeginDate": "2022-01-01",
          "InsertDate": "2022-01-01",
          "PeriodType": "Indefinite",
          "NextEndDate": null,
          "Invalidation": null
        },
        "Employee": {
          "SSN": "123456789",
          "Name": "Vüsal",
          "Phone": "+994501234567",
          "Salary": 1500.00,
          "Surname": "Hüseynov",
          "Position": "Software Engineer",
          "WorkPlace": "Company Name",
          "Patronymic": "Rauf oğlu",
          "WorkPlaceType": "Private",
          "WorkCasualType": "Full-time",
          "PositionLabourContract": "Standard"
        },
        "Employer": {
          "Name": "Company LLC",
          "Voen": "1234567890",
          "Phone": "+994121234567",
          "WorkerCount": 50,
          "LegalAddress": "Bakı şəhəri ...",
          "PropertyType": "Private"
        }
      }
    ],
    "Deactive": [
      // same structure, previous employment records
    ]
  }
}
```

---

#### GET `/api/balance`
Get AsanFinance account balance.

**Response 200:**
```json
{
  "code": 200,
  "message": "Success",
  "data": [
    {
      "Balance": 1250.50,
      "Currency": "AZN",
      "Date": "2024-01-15"
    }
  ]
}
```

---

### Report (unauthenticated)

#### GET `/report`
Returns an HTML view (not JSON) showing monthly request statistics.

```
Displays a table with columns: Year | Month | Personal Requests | Employment Requests
Grouped by year and month, ordered chronologically.
```

---

## 7. AsanFinance External API

### Base URLs
| Environment | Base URL | Swagger |
|-------------|----------|---------|
| Test | `http://test.asanfinance.gov.az:8080` | `http://test.asanfinance.gov.az:8080/swagger` |
| Production | `http://prod.asanfinance.gov.az` | `http://prod.asanfinance.gov.az/swagger` |

Configure via `ASAN_FINANCE_BASE_URI` env var.

> **Test mode restriction:** In test mode only pre-approved FINs work. Any other FIN returns:
> `"Test rejimdə yalnız icazə verilmiş parametrlər üzrə axtarış mümkündür"`
> This is a Code 1 (ValidationError). The test FINs must be obtained from AsanFinance.

### Authentication
`ApiKey` header is **mandatory** on every request.

### Common Request Headers
```
ApiKey: {ASAN_FINANCE_KEY}          # mandatory
Content-Type: application/json
Accept: application/json
RequestIdentifier: {uuid-v4}        # recommended (not mandatory) — echo'd back in response for tracing
```

### Timeout
10 seconds (configurable via `ASAN_FINANCE_REQUEST_TIMEOUT`)

### Common Response Structure
```json
{
  "RequestIdentifier": "uuid",
  "Status": {
    "Name": "string",
    "Code": 0,
    "Message": "string"
  },
  "Response": { /* data object, or null on any error */ }
}
```

### Status Codes (from official documentation)

| Code | Name | Message | Meaning |
|------|------|---------|---------|
| `0` | `Successful` | *(empty)* | Request completed successfully — `Response` contains data |
| `1` | `ValidationError` | Error detail | Request validation failed (bad input) |
| `2` | `ServiceError` | Error detail | Upstream service error |
| `3` | `External` | Error detail | System/external error |
| `4` | `ServiceRestrictedByPerson` | `"Vətəndaş servis üzrə məlumatlarının sorğulanmasına məhdudiyyət tətbiq etmişdir"` | Citizen has blocked data sharing — must activate in MyGov |
| `5` | `PinValidationError` | `"FİN-də xüsusi simvolların istifadəsinə icazə verilmir"` or `"FİN-nin uzunluğu 7 simvol olmalıdır"` | Invalid FIN format |

> **Important:** Code `0` = success. A null `Response` with Code `0` means the query ran fine but no record exists for that FIN (e.g. document not found). It does NOT mean a restriction or error.

### Error Handling Logic in `AsanFinanceService`

```php
$status = $jsonResponse['Status'];

if (!is_null($jsonResponse['Response'])) {
    return $jsonResponse; // success path
}

// Response is null — map status code to appropriate exception
match ($status['Code']) {
    0 => throw new EgovException($status['Message'] ?: 'Məlumat tapılmadı', 404),
    1 => throw new EgovException($status['Message'], 422),
    2 => throw new EgovException($status['Message'], 502),
    3 => throw new EgovException($status['Message'], 502),
    4 => throw new UnreportableException(
            'MyGov tətbiqinə daxil olaraq "İcazələrin idarə edilməsi" bölməsindən ' .
            '"Fərdi məlumatlar" hissəsinə keçid edin və "InvestAZ İnvestisiya Şirkəti" ' .
            'QSC üçün sorğulanmanı aktivləşdirin. Sorğulanma aktiv edildikdən sonra, ' .
            'yenidən InvestAZ tətbiqində FİN kodu və seriya nömrəsini daxil edin.',
            450
        ),
    5 => throw new EgovException($status['Message'], 422),
    default => throw new EgovException($status['Message'], 400),
};
```

> `UnreportableException` (code 4 only) is NOT sent to Sentry — it's an expected user action case.
> All other codes use `EgovException` and ARE reported to Sentry.

### Endpoints Used

#### GET `/api/v1/PersonalInfo/{fin}`
Fetch identity by FIN only.

#### GET `/api/v1/PersonalInfo/PinAndDocNumber?pin={fin}&docNumber={doc}`
Fetch identity by FIN + document number.

**Response `Response` object:**
```json
{
  "PIN": "1234567",
  "DocumentSeria": "AA",
  "DocumentNumber": "1234567",
  "Name": "VÜSAL",
  "Surname": "HÜSEYNOV",
  "NameEn": "VUSAL",
  "SurnameEn": "HUSEYNOV",
  "Patronymic": "Rauf oğlu",
  "BirthDate": "01.01.1990",
  "BirthAddress": "Bakı şəhəri",
  "Gender": "Kişi",
  "RegistrationAddress": "Bakı şəhəri, Nəsimi rayonu, H.Hacıyev küç, ev 5, mən 12",
  "GivenDate": "01.01.2020",
  "ActivationDate": "01.01.2020",
  "ExpireDate": "01.01.2030",   // if missing, calculate as BirthDate + 100 years
  "MaritalStatus": "Evli",
  "GivenOrganization": "Daxili İşlər Nazirliyi",
  "Citizenship": "Azerbaijan Republic",
  "Image": "base64_jpeg",
  "MilitaryStatus": "...",
  "BloodType": "A+",
  "EyeColor": "Qara",
  "Sign": "base64_jpeg",
  "Height": 175
}
```

**Registration address format for personal info:**
Plain string. Parse with regex to extract:
- City: text before "rayonu" → `([^,]+)\s+rayonu`
- District: match `([^,]+)\s+rayonu`
- Street: match `([^,]+)\s+küç` or `([^,]+)\s+pr`
- Building: match `ev\s+([^,]+)` or `bina\s+([^,]+)`
- Apartment: match `mən\s+(\d+)` or `m\.(\d+)`

> Note: The address format is inconsistent in real data. Be robust with regex.

---

#### GET `/api/v1/DMXInfo/{fin}`
Fetch residence permit data.

**Response `Response` object:**
```json
{
  "PIN": "12345",
  "Name": "JOHN",
  "Surname": "DOE",
  "DocumentNumber": "RES123456",
  "DocumentType": "Daimi yaşama icazə vəsiqəsi",
  "BirthDate": "01.01.1985",
  "BirthAddress": "Russia",
  "Gender": "Kişi",
  "RegistrationAddress": "{\"City\":\"Bakı\",\"District\":\"Nəsimi\",\"Street\":\"...\",\"Building\":\"5\",\"Apt\":\"12\"}",
  "ExpireDate": "01.01.2025",
  "GivenDate": "01.01.2020",
  "Citizenship": "Russian Federation",
  "Image": "base64string"
}
```

> **Note:** `RegistrationAddress` for residence is a **JSON string** (unlike personal info which is a plain string).
> Parse it: `json_decode($address, true)` then filter null/empty values and join with `", "`.

---

#### GET `/api/v2/EmployeeInfo/{fin}`
Fetch employment history.

**Response `Response` object:**
```json
{
  "Active": [ /* array of employment records */ ],
  "Deactive": [ /* array of previous employment records */ ]
}
```
Each record structure: see endpoint 3 response format above.

---

#### GET `/api/v1/info/balance?StartDate={date}&EndDate={date}&Offset=0&Limit=10`
Get account balance. Does **not** include `RequestIdentifier` header.

---

## 8. Data Transformation Logic

### Azerbaijani Character Lowercasing
```
Ğ → ğ
Ü → ü
Ş → ş
İ → i
Ö → ö
Ç → ç
I → ı
```
Then apply `mb_strtolower()`.

### Patronymic Cleaning
Remove suffix after lowercasing:
- Strip `" oğlu"` (son of)
- Strip `" qızı"` (daughter of)

### Gender Mapping
- `"Kişi"` → `"1"` (male)
- `"Qadın"` → `"2"` (female)

### Marital Status Mapping
- `"Evli"` → `"2"` (married)
- `"Boşanmış"` → `"3"` (divorced)
- `"Dul"` → `"4"` (widowed)
- anything else → `"1"` (single/unknown)

### Document Type Mapping (residence only)
- `"Daimi yaşama icazə vəsiqəsi"` → `"DYI"` (permanent residence)
- `"Müvəqqəti yaşama icazə vəsiqəsi"` → `"MYI"` (temporary residence)

### Date Formatting
- Input from API: `"DD.MM.YYYY"` format
- Output to client: `"YYYY-MM-DD"` format

### ExpireDate Fallback
If `ExpireDate` is missing from AsanFinance response:
```
ExpireDate = BirthDate + 100 years (formatted as DD.MM.YYYY)
```

### Country Code Lookup
AsanFinance returns country names as strings (e.g., `"Azerbaijan Republic"`, `"Russian Federation"`).
Must be converted to ISO numeric codes using the `countries` table.

**Old approach:** Direct SQL LIKE query (fragile, case-sensitive issues with Azerbaijani chars)

**New approach:** Use **Laravel AI package** to detect the ISO country code from a country name string. Prompt the AI: *"Return only the ISO 3166-1 numeric country code for: [country name]"*. Cache the result to avoid repeated AI calls for the same country.

**Citizenship cleaning (before lookup):**
Remove words: `"republic"`, `"of"` (case insensitive) from citizenship string before querying.

### Name Formatting
- All names come from API in ALL CAPS
- Apply `mb_strtolower()` with Azerbaijani char map first
- Then `ucfirst()` each word
- Full name = `"{Name} {Patronymic} {Surname}"` (with cleaned patronymic)

### `clientCountry` Field
Always hardcoded to `"31"` (Azerbaijan numeric code). This represents where the document was issued, not citizenship.

### Response Key Naming Convention
- `raw` object keys: **PascalCase** — mirrors AsanFinance API response exactly (no transformation)
- `formatData` object keys: **camelCase** — application-facing, normalized output
- Top-level response envelope keys: **camelCase** (`code`, `message`, `data`, `formatData`)

---

## 9. Caching Logic

For identity, residence, and employee data:

```
1. Check DB for record by PIN
2. If NOT in DB:
   → Fetch from AsanFinance API
   → Save to DB
   → Return fetched data
3. If IN DB:
   → Check if ExpireDate has passed (document expired)
   → OR check if updated_at > UPDATE_IDENTITY_AFTER_DAY days ago
   → If either condition true: fetch fresh from API, update DB record, return fresh data
   → Otherwise: return cached DB record
```

`UPDATE_IDENTITY_AFTER_DAY` default = 7 (days).

---

## 10. Response Format

Every response — success or error — uses the same envelope:
```json
{
  "code": 200,
  "message": "Success",
  "data": { }
}
```

On errors, `data` is always `null`:
```json
{
  "code": 400,
  "message": "Human readable error message",
  "data": null
}
```

| Code | Meaning | Source |
|------|---------|--------|
| 200 | Success | — |
| 400 | Unrecognised AsanFinance error | AsanFinance Status.Code default |
| 401 | Invalid or missing API key | `ValidateApiKey` middleware |
| 404 | No record found for FIN / route not found | AsanFinance Code 0 + null Response |
| 422 | Validation error (request or FIN format) | AsanFinance Code 1 or 5, or form request |
| 450 | Citizen has blocked data sharing — must activate in MyGov | AsanFinance Code 4 |
| 500 | Unexpected application error | Unhandled exception |
| 502 | AsanFinance service/external error | AsanFinance Code 2 or 3 |
| 503 | AsanFinance unreachable (connection timeout) | Network / circuit breaker |

### Exception Types
- **`EgovException`**: general errors, logged to Sentry
- **`UnreportableException`**: user-facing errors (e.g., MyGov restriction), NOT sent to Sentry

Both render identically:
```php
return response()->json([
    'code'    => $code,
    'message' => $this->getMessage(),
    'data'    => null,
], $code);
```

### 422 Validation errors
Override `failedValidation()` in all form requests to return the envelope format with the first error message:
```php
protected function failedValidation(Validator $validator): void
{
    throw new HttpResponseException(response()->json([
        'code'    => 422,
        'message' => $validator->errors()->first(),
        'data'    => null,
    ], 422));
}
```

### 404 Route not found
```php
// In bootstrap/app.php or exception handler
$this->renderable(function (NotFoundHttpException $e) {
    return response()->json(['code' => 404, 'message' => 'Route not defined', 'data' => null], 404);
});
```

---

## 11. Scheduled Tasks

```php
// Runs every hour
$schedule->command('notify:balance')->hourly();
```

### `notify:balance` Command
1. Calls `GET /api/v1/info/balance` on AsanFinance
2. Extracts `Response.Balance`
3. If `Balance <= LOW_BALANCE_THRESHOLD (500)`:
   - Sends `LowBalanceMail` to all emails in `LOW_BALANCE_NOTIFY_EMAILS`
4. In debug mode: all emails redirected to `MAIL_DEBUG`

---

## 12. Authentication Details

- **Type:** Static API key
- **Header:** `X-Api-Key: {value}`
- **Config:** `API_KEY` env var → `config('app.api_key')`
- **Middleware:** `ValidateApiKey` applied to all API routes
- **No user table, no token management, no Passport**

Generate a strong key for production: `openssl rand -hex 32`

---

## 13. Docker Setup

Dockerize **only** the application and nginx. The database is external.

### Directory structure:
```
docker/
├── php/
│   └── Dockerfile
└── nginx/
    └── default.conf
docker-compose.yml
```

### `docker/php/Dockerfile`
```dockerfile
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql zip gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
```

### `docker/nginx/default.conf`
```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### `docker-compose.yml`
```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: egov_app
    restart: unless-stopped
    volumes:
      - ./storage:/var/www/html/storage
    env_file:
      - .env
    networks:
      - egov_net

  nginx:
    image: nginx:alpine
    container_name: egov_nginx
    restart: unless-stopped
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app
    networks:
      - egov_net

networks:
  egov_net:
    driver: bridge
```

### Running in production:
```bash
docker-compose up -d --build
docker-compose exec app php artisan migrate --force
docker-compose exec app php artisan db:seed  # seed countries table
```

### Cron job (on host machine, not in Docker):
```cron
* * * * * docker exec egov_app php artisan schedule:run >> /dev/null 2>&1
```

---

## 14. Known Bugs to Fix in Rebuild

1. **Wrong error for "document not found" — root cause now confirmed by official docs:** The official API spec defines Code `4` (`ServiceRestrictedByPerson`) as the MyGov restriction case. Code `0` means *success* — a null `Response` with Code `0` simply means no record was found. The old code incorrectly checked `Code == 0` and threw the MyGov activation message for it. Fix: handle each status code explicitly using the match statement in Section 7. Code `4` → 450 + MyGov message. Code `0` + null Response → 404 with the actual message from the API.

2. **Missing migrations:** `residences`, `employees`, and `logs` tables were never committed to migrations. Create proper migrations for all three.

3. **Hardcoded email addresses:** `notify:balance` command had hardcoded email addresses. Move to `LOW_BALANCE_NOTIFY_EMAILS` env var (comma-separated).

4. **Hardcoded auth credentials in artisan command:** The `refresh:token` command has hardcoded email/password. Remove this command entirely in the rebuild — it was a development utility.

5. **Country lookup fragility:** Old code used LIKE query with manual character substitution. Replace with Laravel AI.

6. **Residence log not tracked:** The `getResidenceInfoByFin()` method does not call `addLog()`. All three types should be logged consistently.

---

## 15. Packages to Install

```bash
# AI (for country code detection)
composer require prism-php/prism
# OR
composer require openai-php/laravel

# Error tracking
composer require sentry/sentry-laravel

# Development
composer require --dev fakerphp/faker
composer require --dev barryvdh/laravel-ide-helper
```

> Check the Laravel AI package available at time of build — use whichever official Laravel AI integration is available that supports Anthropic Claude. Configure it to use Claude haiku for cost efficiency (country detection is a simple task).

---

## 16. Seeder: Countries Table

The countries table must be pre-seeded with ISO 3166-1 data. At minimum, include all countries with:
- `num_code`: ISO numeric code (e.g., 31 for Azerbaijan, 643 for Russia)
- `alpha_2`: 2-letter code (AZ, RU, etc.)
- `country_name`: English name as returned by AsanFinance (use common variations)
- `dialing_code`
- `status`: true

Azerbaijan's `num_code` = **31** (this is used as `client_country` hardcoded value throughout the app).

---

## 17. Report View

The `/report` route returns an HTML view at `resources/views/report.blade.php`. It receives `$reportData` — a collection with fields:
- `request_year`
- `request_month`
- `personal_requests`
- `employment_requests`

Render as a simple HTML table. No authentication required on this route.

---

## 18. Mock Data (Development Mode)

When `APP_DEBUG=true` AND `APP_ENV=development`, `/api/personal-info` and `/api/employee-info` return Faker-generated data instead of calling AsanFinance.

Use `fakerphp/faker` with `az_AZ` locale. The mock identity object must have the same structure as an AsanFinance `Response` object (see Section 7). The mock employee object must have Active/Deactive arrays with the employer/employee/contract structure.

---

## 19. Middleware

### CORS
Add CORS headers to all responses:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN
```

### Rate Limiting
Default Laravel: `throttle:60,1` on API routes (60 requests per minute per user/IP).

### 404 Handling
Return JSON for undefined routes:
```json
{ "message": "Route not defined" }
```

---

## 20. Checklist for Rebuild

- [ ] Laravel 13 + PHP 8.4 project scaffolded
- [ ] Docker setup (app + nginx containers)
- [ ] `.env` configured with all variables from Section 5
- [ ] All migrations created (countries, identities, residences, employees, logs — see Section 4)
- [ ] Data migration SQL from Section 4 run on existing database (unique indexes, json type casts, drop unused tables)
- [ ] Countries table seeded with ISO data
- [ ] `ValidateApiKey` middleware applied to all API routes
- [ ] `AsanFinanceService` built with all 5 API methods
- [ ] Identity, Residence, Employee repositories with interfaces
- [ ] Actions for GetPersonalData, GetResidenceData, GetEmployeeData
- [ ] FormatIdentityData action (all transformation logic from Section 8)
- [ ] FormatResidenceData action
- [ ] Country code lookup via Laravel AI (cached)
- [ ] All 4 API controllers (no auth controller needed)
- [ ] All form request validators
- [ ] EgovException + UnreportableException with render()
- [ ] Sentry integration (non-debug mode only)
- [ ] `notify:balance` artisan command + hourly schedule
- [ ] LowBalanceMail with markdown template
- [ ] CustomMailable base class (redirects to MAIL_DEBUG in debug mode)
- [ ] Mock data for development mode
- [ ] Report view + unauthenticated route
- [ ] CORS middleware active on all routes
- [ ] Bug fixes from Section 14 applied
- [ ] All error responses match format in Section 10
- [ ] Cron job set up on host for Laravel scheduler

---

---

# OPTIONAL IMPROVEMENTS

> Everything below is a separate tab of suggestions. None of it is required to ship a working rebuild. Pick what makes sense.

---

## OPT-1. Replace Base64 Images in Database with Object Storage

**Problem:** `Image` and `Sign` columns are `LONGTEXT` storing base64-encoded JPEGs. A single identity row can be 200–500KB. With thousands of records this bloats the DB, slows backups, and makes queries that touch the table unnecessarily heavy.

**Solution:** Store images in S3 (or any object storage). Save only the URL/path in the DB column.

```
identities.Image  → VARCHAR(500)  stores: "identities/1234567/photo.jpg"
identities.Sign   → VARCHAR(500)  stores: "identities/1234567/sign.jpg"
```

**On write:** upload to S3 via Laravel's Storage facade, store the path.
**On read:** generate a pre-signed URL or serve via a `/image/{path}` proxy endpoint.
**Benefit:** DB rows shrink from ~400KB to ~1KB. Queries get much faster. S3 handles CDN, expiry, and access control.

**Migration path from existing data:**
```php
// One-time artisan command: extract base64, upload to S3, update column
Identity::whereNotNull('Image')->chunkById(100, function ($records) {
    foreach ($records as $record) {
        $path = "identities/{$record->PIN}/photo.jpg";
        Storage::disk('s3')->put($path, base64_decode($record->Image));
        $record->update(['Image' => $path]);
    }
});
```

---

## OPT-2. Redis Caching Layer on Top of DB Cache

**Problem:** Every request hits MySQL to check if a record exists and whether it's expired. For high-traffic scenarios this adds unnecessary DB load.

**Solution:** Add a Redis cache layer in front of the DB lookup. Warm Redis on first fetch, invalidate on update.

```php
// In the repository / action
$cacheKey = "identity:{$fin}";
$ttl = now()->addHours(6);

return Cache::remember($cacheKey, $ttl, function () use ($fin) {
    return $this->identityRepository->getByPin($fin);
});

// On update, bust the cache
Cache::forget("identity:{$fin}");
```

**Add to docker-compose.yml:**
```yaml
redis:
  image: redis:7-alpine
  container_name: egov_redis
  restart: unless-stopped
  networks:
    - egov_net
```

**Add to .env:**
```env
CACHE_STORE=redis
REDIS_HOST=redis
REDIS_PORT=6379
```

**Benefit:** Eliminates DB reads for hot/repeated lookups. Particularly useful since the same FIN is often looked up multiple times within a session.

---

## OPT-3. Queue Logs Asynchronously

**Problem:** `addLog()` writes to the DB synchronously on every API request, adding a DB write to the critical path.

**Solution:** Dispatch a queued job for logging so the HTTP response returns immediately.

```php
// Instead of: DB::table('logs')->insert([...])
// Do:
dispatch(new WriteRequestLog($fin, $type))->onQueue('logs');
```

Use Laravel's database queue driver (no extra infra needed) or Redis queue if OPT-2 is implemented.

**Add worker to docker-compose.yml:**
```yaml
worker:
  build:
    context: .
    dockerfile: docker/php/Dockerfile
  container_name: egov_worker
  restart: unless-stopped
  command: php artisan queue:work --queue=logs --tries=3
  env_file: .env
  networks:
    - egov_net
```

---

## OPT-4. Rate Limiting Per API Key

**Problem:** Default `throttle:60,1` rate limits by IP. If the client is behind a NAT or proxy, all traffic shares one limit. Also doesn't differentiate between API keys.

**Solution:** Rate limit by the `X-Api-Key` header value instead of IP.

```php
// In RouteServiceProvider or bootstrap/app.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->header('X-Api-Key') ?? $request->ip());
});
```

**Bonus:** If you ever issue multiple keys (different clients), each gets its own bucket.

---

## OPT-5. Circuit Breaker for AsanFinance

**Problem:** If AsanFinance is down, every request waits the full 10-second timeout before failing. Under load this starves the connection pool.

**Solution:** Use a circuit breaker. After N consecutive failures, fail immediately for a cooldown period without attempting the real call.

```php
composer require ackintosh/ganesha  // or implement manually with Redis counters
```

Simple manual approach with Redis:
```php
// In AsanFinanceService::sendRequest()
$failKey = 'asan_finance:failures';
if (Cache::get($failKey, 0) >= 5) {
    throw new EgovException('AsanFinance service temporarily unavailable. Try again shortly.', 503);
}

try {
    $response = Http::timeout(10)->...->get($endpoint);
    Cache::forget($failKey);
} catch (ConnectionException $e) {
    Cache::increment($failKey);
    Cache::put($failKey, Cache::get($failKey), now()->addMinutes(2)); // cooldown 2 min
    throw new EgovException($e->getMessage() . ' #4001', 503);
}
```

---

## OPT-6. Health Check Endpoint

**Problem:** Docker and load balancers need a way to check if the app is alive and its dependencies are reachable.

**Solution:** Add an unauthenticated `/health` endpoint.

```php
// GET /health  (no auth)
return response()->json([
    'code'    => 200,
    'message' => 'OK',
    'data'    => [
        'database' => $this->checkDb(),       // try DB::select('SELECT 1')
        'asanfinance' => $this->checkAsanFinance(), // lightweight ping or balance call
    ]
]);
```

**In docker-compose.yml:**
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/health"]
  interval: 30s
  timeout: 5s
  retries: 3
```

---

## OPT-7. API Versioning

**Problem:** The current routes have no version prefix. If the response format changes in the future, you have no way to roll it out without breaking existing clients.

**Solution:** Prefix all API routes with `/v1/`.

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::post('personal-info', [IdentityController::class, 'getPersonalData']);
    // ...
});
```

Cost: clients need to update their base URL once. Benefit: any future breaking change becomes `/v2/` and old clients keep working.

---

## OPT-8. OpenAPI / Swagger Documentation

**Problem:** There is no machine-readable API contract. Clients have to read the markdown or ask you.

**Solution:** Add `darkaonline/l5-swagger` and annotate controllers with OpenAPI attributes.

```bash
composer require darkaonline/l5-swagger
```

```php
#[OA\Post(
    path: '/api/v1/personal-info',
    summary: 'Fetch citizen identity data by FIN',
    security: [['ApiKey' => []]],
    requestBody: new OA\RequestBody(...),
    responses: [new OA\Response(response: 200, ...)]
)]
public function getPersonalData(GetPersonalDataRequest $request): JsonResponse
```

Generates a Swagger UI at `/api/documentation` automatically.

---

## OPT-9. Structured Logging

**Problem:** Sentry catches exceptions but there is no structured log of normal request flow — which FINs were queried, cache hits vs misses, AsanFinance response times.

**Solution:** Use Laravel's built-in logging with context, sending to a log aggregator (e.g. Papertrail, Logtail, or just a JSON log file picked up by a sidecar).

```php
Log::info('identity.fetched', [
    'fin'    => $fin,
    'source' => 'cache',  // or 'api'
    'ms'     => $elapsed,
]);

Log::warning('asanfinance.slow', [
    'fin'      => $fin,
    'endpoint' => $endpoint,
    'ms'       => $elapsed,
]);
```

Add `LOG_CHANNEL=stderr` in Docker so logs go to stdout and are captured by the container runtime.

---

## OPT-10. Support Multiple API Keys

**Problem:** A single `API_KEY` env var means you can't rotate the key without downtime, and you can't give different clients different keys.

**Solution:** Store keys in a `api_keys` table with an optional `name` and `active` flag.

```php
// Migration
Schema::create('api_keys', function (Blueprint $table) {
    $table->id();
    $table->string('name');               // e.g. "InvestAZ Mobile"
    $table->string('key', 64)->unique();  // hashed or plain
    $table->boolean('active')->default(true);
    $table->timestamps();
});
```

```php
// ValidateApiKey middleware
$key = ApiKey::where('key', $request->header('X-Api-Key'))
             ->where('active', true)
             ->first();

if (!$key) {
    return response()->json(['code' => 401, 'message' => 'Unauthorized', 'data' => null], 401);
}
```

**Benefit:** Rotate keys without redeployment. Revoke a single client. Log which key made each request.

---

## OPT-11. Soft-Delete Cached Records Instead of Overwriting

**Problem:** When a cached identity is refreshed, the old data is overwritten with no history. If AsanFinance returns bad data, you lose the previous good record.

**Solution:** Use `SoftDeletes` on identity/residence models. On cache refresh, soft-delete the old row and insert a new one. Keeps an audit trail of what data was returned at what time.

```php
// In migration — add to identities, residences, employees
$table->softDeletes();
```

```php
// In repository update method
$existing = Identity::where('PIN', $pin)->first();
$existing->delete(); // soft delete
Identity::create($newData);
```

---

## OPT-12. Dedicated Exception for AsanFinance Connection Errors

**Problem:** The current design uses `EgovException` for everything. Connection errors to AsanFinance (network down, timeout) are indistinguishable from business logic errors in Sentry.

**Solution:** Create `AsanFinanceConnectionException` extending `EgovException`. Tag it differently in Sentry for alerting.

```php
class AsanFinanceConnectionException extends EgovException
{
    // Sentry will group these separately
    // Can set up a Sentry alert rule: "if AsanFinanceConnectionException > 5/min → page oncall"
}
```
