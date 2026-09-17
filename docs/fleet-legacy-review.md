# Fleet / Legacy Module Review

**Status: DEPRECATED / UNROUTED — not part of live NEWPOS navigation.**

Generated during NEWPOS phase completion (Sep 2026). Updated during production QA.
Do not delete assets until business confirms. Do not wire into `routes/web.php` without product decision.

## KEEP
- Current Sellix routes in `routes/web.php` and fleet layout (`layouts.fleet`, `fleet-sidebar`)
- Sellix models/services under `app/Models`, `app/Services`
- Active HR core (departments, employees, leave, advances, allowances)
- New modules: credit notes, stock transfers, quotations, payroll, attendance, HR payments/reports
- Accounting catalog + AccountingPoster auto-post

## DEPRECATED (unrouted; do not wire without product decision)
- All `Fleet*Controller` (~28) under `app/Http/Controllers`
- Fleet views under `resources/views/fleet/`
- `config/fleet.php` and fleet migrations under `database/migrations_legacy/`
- Legacy Indonesian controllers: Penjualan, Produk, Pembelian, Pengeluaran, Kategori, Member, Laporan, etc.
- Legacy layout `layouts/master.blade.php` + `layouts/sidebar.blade.php` (references missing route names)
- Legacy payroll views under `resources/views/payroll/` (superseded by `hr/payroll`)
- Old `PayrollController`, `EmployeeController`, `InvoiceController`, `PaymentController`, `UserController`, `BackupController`, `AccountController`

## UNUSED / REQUIRES REVIEW
- `routes/api.php` effectively empty beyond auth stubs
- Jetstream team features if unused by Sellix UX
- Duplicate report blades under old `resources/views/reports` Indonesian paths if any remain
- `hasModulePermission` references in legacy controllers (method removed from User)

## REMOVE (only after confirmation)
- Fleet package after export/backup of any still-needed data
- Indonesian legacy controllers/views once no production bookmarks remain
- `database/migrations_legacy` after archival

## QA verification (Sep 2026)
- No `fleet*` / `penjualan*` / `produk*` named routes in live `web.php`
- Fleet does not appear in Sellix sidebar
- No deletions performed

## Status
No deletions performed. Fleet/legacy remain on disk, unrouted, labeled DEPRECATED.
