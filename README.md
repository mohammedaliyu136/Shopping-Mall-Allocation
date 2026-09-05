# Shopping Mall Allocation, Billing and Owner Management System

A complete implementation of the Software Requirements Specification for a system that
manages shopping mall spaces, shop owners, allocations, allocation documentation, billing,
payments, ownership transfers, notifications, customer support and QR-code verification of
allocation documents.

Built with **Laravel 13 (PHP 8.3+) and SQLite**.

---

## Try it without setting anything up

Open the repository on GitHub, press **Code → Codespaces → Create codespace on
this branch**. This works from a phone or tablet browser — no local machine
needed.

The codespace installs dependencies, creates the SQLite database, loads the
demonstration data and starts the server automatically. When it finishes, open
the **Ports** tab and click the forwarded address for port 8000.

Forwarded ports are private to your GitHub account by default. To show someone
else, set the port's visibility to Public in the Ports tab.

### A public URL that stays up

To host it instead, the repository ships a `Dockerfile` and a `render.yaml`
blueprint. On [Render](https://render.com), choose **New → Blueprint** and point
it at this repository; it will ask you for a `DEMO_PASSWORD` for the seeded
accounts, since the deployed URL is public.

On Render's free plan the filesystem is ephemeral, so the database rebuilds
itself with fresh demonstration data whenever the service restarts. Attach a
persistent disk to keep changes between restarts.

> The container serves through PHP's built-in server, which suits a demo or a
> test instance. Put PHP-FPM and nginx in front of it for production traffic.

---

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed

php artisan serve
```

Then open <http://127.0.0.1:8000>.

There is no front-end build step — the stylesheet is served directly from `public/css/app.css`,
so `npm install` is not required to run the application.

### Demonstration accounts

`php artisan migrate --seed` loads a worked data set: one mall with two blocks, 32 shops,
five registered owners with active allocations, generated letters and cards, invoices,
payments and receipts, notifications and a support ticket.

Every account below uses the password `password`. Set a `DEMO_PASSWORD`
environment variable before seeding to use something else — do that for any
instance reachable over the internet, since these accounts include a Super
Administrator.

| Role | Email | Sees |
|---|---|---|
| Super Administrator | `admin@mall.test` | Everything, including users, roles and settings |
| Mall Administrator | `mall.admin@mall.test` | Shops, owners, allocations, documents, notifications, transfers |
| Allocation Officer | `allocation@mall.test` | Owners, allocations, documents, transfer requests |
| Finance Officer | `finance@mall.test` | Billing, invoices, payments, receipts, financial reports |
| Customer Support Officer | `support@mall.test` | Support tickets and notifications |
| Auditor | `auditor@mall.test` | Read-only access, including the audit trail |
| Shop Owner | `aisha.bello@example.test` | The owner self-service portal |

The public verification portal at `/verify` needs no login at all.

---

## The three portals

| Portal | Path | Who |
|---|---|---|
| Administration | `/admin` | Mall management and staff, filtered by role permissions |
| Owner self-service | `/portal` | Registered shop owners |
| Public verification | `/verify` | Anyone holding an allocation letter or card |

---

## How the system works

### Allocation lifecycle

```
Create owner → Create/select shop → Create allocation (draft)
    → Submit for approval → Approval chain → Approved
    → Letter + card + QR code issued → Activate → Active
```

A shop carrying a live allocation cannot be allocated again: `AllocationService::create()`
locks the shop row and refuses the attempt, so duplicate allocation is impossible even
under concurrent requests.

The approval chain is configurable in **System Settings** (`allocation.approval_stages`).
Each approval clears one level; the allocation is only approved — and its documents only
issued — once every level has signed off. Setting a single stage gives single-step approval.

### Money

Allocations, invoices and payments are settled in exact decimal arithmetic through
`App\Support\Money`, which works in integer minor units. Balances never drift the way
binary floating point does, and the application needs no `ext-bcmath`.

### Billing and payments

Invoices carry line items per billing category. Recording a payment recalculates the
invoice's paid amount, balance and status inside a locked transaction, so an invoice can
never be overpaid and partial payments are handled correctly. Every confirmed payment
issues a receipt. A gateway reference is unique across the payments table, so the same
settlement can never be recorded twice.

Two scheduled commands run the finance cycle:

```bash
php artisan mall:run-billing        # FR-092 annual invoices for every active allocation
php artisan mall:process-overdue    # FR-094 identify overdue invoices and remind owners
```

Both are safe to re-run: billing skips allocations already billed for the period, and an
owner is reminded about a given invoice only once. They are also registered on Laravel's
scheduler in `routes/console.php`.

### Ownership transfers keep history

Approving a transfer never edits or deletes the outgoing allocation. The old allocation is
closed with status `transferred`, and a fresh allocation — with its own reference, letter,
card and QR verification record — is created for the incoming owner and linked back to it.
A shop's complete ownership history is therefore always reconstructable, which is the SRS's
central design principle. Outstanding obligations are re-checked at approval time, not only
when the request was raised.

### QR verification

Each allocation carries a 40-character random `verification_token`. The QR code printed on
letters and cards encodes `/verify/{token}`. The public page reports the live status of the
record: `VERIFIED` for an active allocation, and the true current state (`CANCELLED`,
`TRANSFERRED TO A NEW OWNER`, `SUSPENDED`, `EXPIRED`) for anything else, so a revoked
document is never silently accepted. Unknown tokens return
`INVALID OR UNRECOGNISED ALLOCATION`. The page deliberately shows only what a verifier
needs — it does not expose the owner's contact details, identification or financial position.

### Roles and permissions

Roles are database records with a configurable permission set; `App\Support\Permissions`
holds the catalogue and the shipped default matrix for the seven users in SRS section 5.
Every administration route is gated on the permission its feature needs, navigation is
filtered to what the signed-in user may reach, and owner portal accounts are confined to
their own records.

### Audit trail

The `Auditable` trait writes create, update and delete activity — including the values
before and after each change — for allocations, owners, shops, invoices, payments,
transfers, tickets and users, alongside explicit domain events such as card reissues and
sign-in attempts. Password hashes are excluded. `AuditLog` refuses `update()` and
`delete()`, so entries are append-only as FR-171 requires.

---

## Layout

```
app/
  Console/Commands/    Scheduled billing and overdue processing
  Exceptions/          Allocation, billing and transfer domain errors
  Http/
    Controllers/       Admin/, Portal/, Auth/, verification, PDF downloads
    Middleware/        Permission gate, session timeout, portal separation
  Models/              26 Eloquent models covering the SRS entities
  Services/            Allocation, billing, payment, transfer, notification,
                       support, reporting, QR, PDF and reference generation
  Support/             Auditable trait, permission catalogue, Money
database/
  migrations/          Nine grouped migrations for all SRS section 8 entities
  seeders/             Roles/permissions, settings, billing categories, demo data
resources/views/
  admin/ portal/ verify/ documents/    Blade templates for the three portals
                                       and the four generated PDFs
tests/
  Feature/AcceptanceCriteriaTest.php   One test per SRS section 14 criterion
  Feature/SecurityAndWorkflowTest.php  Authentication, access control, workflows
  Unit/MoneyTest.php                   Decimal arithmetic
```

---

## Tests

```bash
php artisan test
```

62 tests / 327 assertions, all passing. `AcceptanceCriteriaTest` maps one-to-one onto the
sixteen acceptance criteria in SRS section 14, so the suite is a direct check of whether the
system meets its stated definition of done.

---

## Requirements coverage

| SRS | Requirement | Where |
|---|---|---|
| FR-001 – FR-003 | Login, password management, session timeout | `Auth/LoginController`, `Auth/PasswordController`, `EnforceSessionTimeout` |
| FR-010, FR-011 | Roles, permissions, blocked unauthorised access | `Support/Permissions`, `EnsurePermission`, `Admin/RoleController` |
| FR-020, FR-021 | Malls, blocks, floors | `Admin/MallController` |
| FR-030 – FR-032 | Shops, statuses, duplicate-allocation guard | `Admin/ShopController`, `AllocationService::assertShopIsAllocatable()` |
| FR-040 – FR-043 | Owner registration, business details, documents, search | `Admin/OwnerController`, `Owner::scopeSearch()` |
| FR-050 – FR-053 | Allocation creation, reference format, statuses, approval chain | `AllocationService`, `ReferenceGenerator` |
| FR-060 – FR-062 | Allocation letter, PDF, verification reference | `DocumentService`, `documents/allocation-letter.blade.php` |
| FR-070 – FR-072 | Allocation card, statuses, reissue with history | `AllocationService::reissueCard()` |
| FR-080 – FR-083 | QR generation, public verification, invalid and revoked handling | `QrCodeService`, `VerificationController` |
| FR-090 – FR-094 | Billing categories, invoices, recurring billing, balances, overdue | `BillingService`, `mall:run-billing`, `mall:process-overdue` |
| FR-100 – FR-103 | Payments, methods, partial payments, gateway reconciliation | `PaymentService` |
| FR-110, FR-111 | Receipt generation and download | `PaymentService::issueReceipt()`, `DocumentController` |
| FR-120 – FR-124 | Transfer requests, obligation checks, approval, permanent history | `TransferService` |
| FR-130 – FR-133 | Notification creation, targeting, channels, automated triggers | `NotificationService` |
| FR-140 – FR-143 | Tickets, categories, statuses, threaded communication | `SupportTicketService` |
| FR-150 – FR-152 | Owner dashboard, document access, profile management | `Portal/*` |
| FR-160 – FR-163 | Allocation, financial, owner and support reports | `ReportService`, `Admin/ReportController` |
| FR-170, FR-171 | Activity logging, protected records | `Support/Auditable`, `AuditLog` |

---

## Notes on the implementation

**SQLite.** The SRS recommends MySQL or PostgreSQL; SQLite was requested and is what the
application is configured and tested against. Nothing in the schema or queries is
SQLite-specific beyond two reporting queries that use `strftime`/`julianday`, which are
isolated in `ReportService` — a port to MySQL or PostgreSQL means changing those two
expressions and the database connection.

**External services.** SRS section 11 lists an SMS gateway, a payment gateway and cloud
storage as external integrations. They are wired to their boundaries rather than to a
specific vendor: email uses Laravel's configured mailer; SMS dispatches through
`NotificationService::deliverSms()`, which logs the message and records the delivery outcome
until a provider is configured; and online payments are recorded as pending against a
generated gateway reference, with `PaymentService::confirm()` as the callback entry point
that settles the payment and refuses duplicate references.

**Owner photographs** upload and are stored, and the allocation card reserves the space for
one; the card template prints a placeholder rather than embedding the image file.
