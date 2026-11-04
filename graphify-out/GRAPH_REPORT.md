# Graph Report - apartment-management-accounting-system-main  (2026-11-03)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 1297 nodes · 3155 edges · 157 communities (47 shown, 110 thin omitted)
- Extraction: 88% EXTRACTED · 12% INFERRED · 0% AMBIGUOUS · INFERRED: 365 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `a12b032f`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- TestCase
- ResidentController.php
- ReportController.php
- OperationsController
- FinanceException
- Illuminate\Database\Eloquent\Relations\BelongsTo
- ReportService
- AccountingReportService.php
- DashboardService.php
- Apartment
- FinanceController.php
- CommunicationController.php
- Illuminate\Database\Eloquent\Model
- .categories
- Cashbox
- package.json
- Money
- Illuminate\Validation\Rule
- Meeting
- Illuminate\Database\Schema\Blueprint
- .accrue
- FinanceService
- Unit
- Illuminate\Http\Request
- Property
- 2026_11_02_100200_create_operations_tables.php
- UserController.php
- Illuminate\Database\Migrations\Migration
- Illuminate\Database\Eloquent\Relations\BelongsToMany
- Document
- .cashboxes
- AppServiceProvider.php
- CashTransaction
- BankImportService.php
- EnsureSiteRole.php
- .units
- Debt
- .collect
- 2026_11_02_100100_create_finance_tables.php
- web.php
- BankController
- PayrollService
- AuthController
- ReportService.php
- 2026_11_02_100000_create_apartment_structure_tables.php
- Controller
- DebtActionController
- Block
- composer.json
- scripts
- 2026_11_10_000100_extend_accounting.php
- User
- bootstrap/app.php
- require-dev
- UserFactory.php
- BankImportService
- config
- ExpenseController
- Vendor
- CommonArea
- Ticket
- DuesDefinition
- psr-4
- 0001_01_01_000000_create_users_table.php
- require
- MeetingAttendance
- ExampleTest
- autoload-dev
- extra
- app.js
- site/dashboard.blade.php
- cashboxes.blade.php
- collections.blade.php
- overview.blade.php
- vendors.blade.php
- assets.blade.php
- reservations.blade.php
- staff.blade.php

## God Nodes (most connected - your core abstractions)
1. `Apartment` - 281 edges
2. `User` - 74 edges
3. `FinanceService` - 63 edges
4. `Debt` - 47 edges
5. `Unit` - 44 edges
6. `FinanceException` - 36 edges
7. `Controller` - 34 edges
8. `CashTransaction` - 29 edges
9. `Money` - 25 edges
10. `Cashbox` - 24 edges

## Surprising Connections (you probably didn't know these)
- `PanelTest` --references--> `Apartment`  [EXTRACTED]
  tests/Feature/PanelTest.php → app/Models/Apartment.php
- `PanelTest` --references--> `User`  [EXTRACTED]
  tests/Feature/PanelTest.php → app/Models/User.php
- `AccountingExtensionsTest` --references--> `FinanceService`  [EXTRACTED]
  tests/Feature/AccountingExtensionsTest.php → app/Services/FinanceService.php
- `FinanceServiceTest` --references--> `FinanceService`  [EXTRACTED]
  tests/Feature/FinanceServiceTest.php → app/Services/FinanceService.php
- `NewModulesHttpTest` --references--> `Apartment`  [EXTRACTED]
  tests/Feature/NewModulesHttpTest.php → app/Models/Apartment.php

## Import Cycles
- None detected.

## Communities (157 total, 110 thin omitted)

### Community 0 - "TestCase"
Cohesion: 0.05
Nodes (7): DatabaseSeeder, ExampleTest, ManagementServicesTest, MeterAndReservationTest, PanelTest, ResidentApiTest, TestCase

### Community 1 - "ResidentController.php"
Cohesion: 0.06
Nodes (8): {closure#10}(), {closure#5}(), {closure#8}(), ResidentController, {closure#4}(), {closure#3}(), Poll, ReservationService

### Community 2 - "ReportController.php"
Cohesion: 0.06
Nodes (5): MemberController, {closure#11}(), {closure#5}(), ReportController, Member

### Community 3 - "OperationsController"
Cohesion: 0.08
Nodes (6): {closure#5}(), OperationsController, Meter, Staff, Task, {closure#1}()

### Community 4 - "FinanceException"
Cohesion: 0.19
Nodes (17): FinanceException, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#3}() (+9 more)

### Community 5 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.09
Nodes (5): {closure#12}(), BankStatementLine, DebtPayment, Expense, Reservation

### Community 6 - "ReportService"
Cohesion: 0.14
Nodes (3): DashboardController, DashboardService, ReportService

### Community 7 - "AccountingReportService.php"
Cohesion: 0.09
Nodes (6): AssetController, {closure#1}(), {closure#2}(), Asset, {closure#10}(), {closure#8}()

### Community 8 - "DashboardService.php"
Cohesion: 0.06
Nodes (4): AuditLog, InventoryItem, {closure#11}(), {closure#20}()

### Community 9 - "Apartment"
Cohesion: 0.14
Nodes (5): ApartmentController, {closure#1}(), {closure#2}(), DuesController, Apartment

### Community 10 - "FinanceController.php"
Cohesion: 0.09
Nodes (10): {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#6}() (+2 more)

### Community 11 - "CommunicationController.php"
Cohesion: 0.09
Nodes (4): {closure#11}(), {closure#3}(), CommunicationController, Announcement

### Community 12 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.09
Nodes (8): BudgetItem, Decision, LedgerEntry, MeterBill, MeterReading, PollOption, PollVote, TransactionCategory

### Community 13 - ".categories"
Cohesion: 0.11
Nodes (3): AccountingReportController, BudgetController, AccountingReportService

### Community 14 - "Cashbox"
Cohesion: 0.11
Nodes (7): {closure#2}(), {closure#3}(), Cashbox, {closure#13}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 15 - "package.json"
Cohesion: 0.12
Nodes (22): devDependencies, concurrently@^10.0.6, laravel-vite-plugin@^3.2.0, lucide-static@^1.66.0, tailwindcss@^4.3.4, @tailwindcss/vite@^4.3.4, vite@^8.3.5, optionalDependencies (+14 more)

### Community 16 - "Money"
Cohesion: 0.13
Nodes (6): tl(), MeterBillController, {closure#8}(), MeterBillService, Money, {closure#1}()

### Community 17 - "Illuminate\Validation\Rule"
Cohesion: 0.12
Nodes (3): {closure#2}(), SecurityController, SecurityEntry

### Community 18 - "Meeting"
Cohesion: 0.18
Nodes (3): {closure#2}(), MeetingController, Meeting

### Community 19 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.18
Nodes (17): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+9 more)

### Community 21 - "FinanceService"
Cohesion: 0.15
Nodes (5): {closure#1}(), {closure#2}(), {closure#3}(), LateFeeController, FinanceService

### Community 22 - "Unit"
Cohesion: 0.15
Nodes (7): {closure#1}(), Unit, {closure#9}(), {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 23 - "Illuminate\Http\Request"
Cohesion: 0.19
Nodes (4): {closure#4}(), {closure#5}(), {closure#6}(), ReservationController

### Community 25 - "2026_11_02_100200_create_operations_tables.php"
Cohesion: 0.11
Nodes (15): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#2}() (+7 more)

### Community 27 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.13
Nodes (6): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}()

### Community 28 - "Illuminate\Database\Eloquent\Relations\BelongsToMany"
Cohesion: 0.14
Nodes (3): {closure#7}(), SiteNotification, UnitGroup

### Community 31 - "AppServiceProvider.php"
Cohesion: 0.13
Nodes (5): AppServiceProvider, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 33 - "BankImportService.php"
Cohesion: 0.16
Nodes (6): {closure#10}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#7}(), {closure#8}()

### Community 34 - "EnsureSiteRole.php"
Cohesion: 0.23
Nodes (3): EnsureAdmin, EnsureResident, EnsureSiteRole

### Community 36 - "Debt"
Cohesion: 0.18
Nodes (4): {closure#2}(), {closure#8}(), {closure#15}(), Debt

### Community 39 - "2026_11_02_100100_create_finance_tables.php"
Cohesion: 0.15
Nodes (10): {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+2 more)

### Community 41 - "BankController"
Cohesion: 0.20
Nodes (4): BankController, {closure#1}(), {closure#3}(), {closure#4}()

### Community 42 - "PayrollService"
Cohesion: 0.23
Nodes (3): {closure#4}(), PayrollController, PayrollService

### Community 45 - "ReportService.php"
Cohesion: 0.20
Nodes (3): {closure#6}(), {closure#7}(), {closure#8}()

### Community 46 - "2026_11_02_100000_create_apartment_structure_tables.php"
Cohesion: 0.20
Nodes (7): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 48 - "DebtActionController"
Cohesion: 0.28
Nodes (3): {closure#1}(), {closure#2}(), DebtActionController

### Community 50 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 51 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 52 - "2026_11_10_000100_extend_accounting.php"
Cohesion: 0.22
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 54 - "bootstrap/app.php"
Cohesion: 0.32
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 55 - "require-dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 59 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 65 - "psr-4"
Cohesion: 0.33
Nodes (6): autoload, files, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 66 - "0001_01_01_000000_create_users_table.php"
Cohesion: 0.33
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 67 - "require"
Cohesion: 0.40
Nodes (5): require, laravel/framework, laravel/sanctum, laravel/tinker, php

### Community 73 - "autoload-dev"
Cohesion: 0.67
Nodes (3): autoload-dev, psr-4, Tests\\

### Community 74 - "extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

## Knowledge Gaps
- **8 isolated node(s):** `site.finance._transactions`, `site.finance._transactions`, `site.finance._transactions`, `site.finance._transactions`, `site.finance._vendor-fields` (+3 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 384 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **110 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Apartment` connect `Apartment` to `TestCase`, `ResidentController.php`, `ReportController.php`, `OperationsController`, `FinanceException`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `ReportService`, `AccountingReportService.php`, `DashboardService.php`, `FinanceController.php`, `CommunicationController.php`, `Illuminate\Database\Eloquent\Model`, `.categories`, `Cashbox`, `Money`, `Illuminate\Validation\Rule`, `Meeting`, `.accrue`, `FinanceService`, `Unit`, `Illuminate\Http\Request`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `Document`, `.cashboxes`, `AppServiceProvider.php`, `BankImportService.php`, `EnsureSiteRole.php`, `.units`, `Illuminate\Database\Eloquent\Relations\HasMany`, `web.php`, `BankController`, `PayrollService`, `ReportService.php`, `DebtActionController`, `Block`, `User`, `BankImportService`, `ExpenseController`, `Vendor`, `CommonArea`, `Ticket`?**
  _High betweenness centrality (0.262) - this node is a cross-community bridge._
- **What connects `site.finance._transactions`, `site.finance._transactions`, `site.finance._transactions` to the rest of the system?**
  _8 weakly-connected nodes found - possible missing edges._
- **Should `TestCase` be split into smaller, more focused modules?**
  _Cohesion score 0.05185185185185185 - nodes in this community are weakly interconnected._
- **Why does `User` connect `User` to `TestCase`, `ResidentController.php`, `OperationsController`, `FinanceException`, `Illuminate\Database\Eloquent\Relations\BelongsTo`, `ReportService`, `DashboardService.php`, `Apartment`, `Cashbox`, `Money`, `.accrue`, `FinanceService`, `Unit`, `Property`, `UserController.php`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `.cashboxes`, `AppServiceProvider.php`, `BankImportService.php`, `Debt`, `.collect`, `AuthController`, `User.php`, `Controller`, `Illuminate\Support\Str`, `UserFactory.php`, `BankImportService`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._
- **Should `ResidentController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.0647342995169082 - nodes in this community are weakly interconnected._
- **Why does `Debt` connect `Debt` to `TestCase`, `ResidentController.php`, `ReportController.php`, `FinanceException`, `.collect`, `AccountingReportService.php`, `DashboardService.php`, `FinanceController.php`, `Illuminate\Database\Eloquent\Model`, `ReportService.php`, `Cashbox`, `DebtActionController`, `Money`, `.accrue`, `FinanceService`, `Unit`, `UserController.php`?**
  _High betweenness centrality (0.024) - this node is a cross-community bridge._
- **Should `ReportController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.06236786469344609 - nodes in this community are weakly interconnected._