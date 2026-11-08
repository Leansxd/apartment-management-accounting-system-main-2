# Graph Report - apartment-management-accounting-system-main  (2026-11-08)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 1407 nodes · 3411 edges · 164 communities (51 shown, 113 thin omitted)
- Extraction: 88% EXTRACTED · 12% INFERRED · 0% AMBIGUOUS · INFERRED: 407 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `061c59b5`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- MeterAndReservationTest
- ResidentController.php
- ReportController.php
- OperationsController
- FinanceException
- Illuminate\Database\Eloquent\Relations\BelongsTo
- ReportService
- AccountingReportService.php
- DashboardService.php
- TestCase
- Illuminate\Http\Request
- CommunicationController.php
- Illuminate\Database\Eloquent\Model
- .categories
- Cashbox
- package.json
- Money
- Illuminate\Validation\Rule
- Meeting
- Illuminate\Database\Schema\Blueprint
- .collect
- Carbon\CarbonImmutable
- Unit
- Illuminate\Http\Request
- Property
- 2026_11_02_100200_create_operations_tables.php
- UserController.php
- Illuminate\Database\Migrations\Migration
- Illuminate\Database\Eloquent\Relations\BelongsToMany
- Document
- NewModulesHttpTest
- AppServiceProvider.php
- CommunicationController
- BankImportService.php
- InstallService
- .units
- Debt
- Apartment
- Expense
- 2026_11_02_100100_create_finance_tables.php
- web.php
- BankController
- PayrollService
- AuthController
- User
- ReportService.php
- 2026_11_02_100000_create_apartment_structure_tables.php
- Controller
- FinanceService
- Block
- composer.json
- scripts
- 2026_11_10_000100_extend_accounting.php
- DashboardService
- DatabaseSeeder.php
- require-dev
- SetupWizardTest
- UserFactory.php
- config
- ManagementServicesTest
- ExpenseController.php
- CommonArea
- TicketController.php
- DuesDefinition
- psr-4
- 0001_01_01_000000_create_users_table.php
- require
- SetupCommandTest
- ExampleTest
- autoload-dev
- extra
- app.js
- cashboxes.blade.php
- collections.blade.php
- overview.blade.php
- vendors.blade.php
- assets.blade.php
- reservations.blade.php
- staff.blade.php
- PanelTest
- ResidentApiTest
- laravel-boost
- laravel-boost
- components.dash.classic-quick-forms
- layout.blade.php

## God Nodes (most connected - your core abstractions)
1. `Apartment` - 289 edges
2. `User` - 86 edges
3. `FinanceService` - 63 edges
4. `Debt` - 49 edges
5. `Unit` - 44 edges
6. `FinanceException` - 36 edges
7. `Controller` - 35 edges
8. `CashTransaction` - 30 edges
9. `TestCase` - 30 edges
10. `Money` - 25 edges

## Surprising Connections (you probably didn't know these)
- `AccountingExtensionsTest` --references--> `FinanceService`  [EXTRACTED]
  tests/Feature/AccountingExtensionsTest.php → app/Services/FinanceService.php
- `FinanceServiceTest` --references--> `FinanceService`  [EXTRACTED]
  tests/Feature/FinanceServiceTest.php → app/Services/FinanceService.php
- `NewModulesHttpTest` --references--> `Apartment`  [EXTRACTED]
  tests/Feature/NewModulesHttpTest.php → app/Models/Apartment.php
- `NewModulesHttpTest` --references--> `User`  [EXTRACTED]
  tests/Feature/NewModulesHttpTest.php → app/Models/User.php
- `ClassicHudTest` --references--> `Apartment`  [EXTRACTED]
  tests/Feature/ClassicHudTest.php → app/Models/Apartment.php

## Import Cycles
- None detected.

## Communities (164 total, 113 thin omitted)

### Community 1 - "ResidentController.php"
Cohesion: 0.09
Nodes (4): {closure#5}(), {closure#8}(), ResidentController, ReservationService

### Community 2 - "ReportController.php"
Cohesion: 0.06
Nodes (5): MemberController, {closure#11}(), {closure#5}(), ReportController, Member

### Community 3 - "OperationsController"
Cohesion: 0.08
Nodes (5): {closure#5}(), OperationsController, InventoryItem, Meter, Task

### Community 4 - "FinanceException"
Cohesion: 0.24
Nodes (15): FinanceException, {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#3}() (+7 more)

### Community 5 - "Illuminate\Database\Eloquent\Relations\BelongsTo"
Cohesion: 0.08
Nodes (5): {closure#12}(), BankStatementLine, CashTransaction, Reservation, Ticket

### Community 7 - "AccountingReportService.php"
Cohesion: 0.10
Nodes (5): AssetController, {closure#1}(), {closure#2}(), Asset, {closure#8}()

### Community 8 - "DashboardService.php"
Cohesion: 0.07
Nodes (3): AuditLog, {closure#14}(), {closure#22}()

### Community 9 - "TestCase"
Cohesion: 0.16
Nodes (3): ClassicHudTest, ExampleTest, TestCase

### Community 10 - "Illuminate\Http\Request"
Cohesion: 0.14
Nodes (5): ApartmentController, {closure#10}(), {closure#6}(), {closure#9}(), FinanceController

### Community 11 - "CommunicationController.php"
Cohesion: 0.07
Nodes (6): {closure#10}(), {closure#11}(), {closure#3}(), {closure#4}(), Announcement, Poll

### Community 12 - "Illuminate\Database\Eloquent\Model"
Cohesion: 0.10
Nodes (7): BudgetItem, Decision, MeetingAttendance, MeterReading, PollOption, PollVote, TransactionCategory

### Community 13 - ".categories"
Cohesion: 0.14
Nodes (3): AccountingReportController, BudgetController, AccountingReportService

### Community 14 - "Cashbox"
Cohesion: 0.11
Nodes (10): {closure#1}(), {closure#3}(), {closure#3}(), {closure#11}(), {closure#12}(), Cashbox, {closure#13}(), {closure#1}() (+2 more)

### Community 15 - "package.json"
Cohesion: 0.12
Nodes (22): devDependencies, concurrently@^10.0.6, laravel-vite-plugin@^3.2.0, lucide-static@^1.66.0, tailwindcss@^4.3.4, @tailwindcss/vite@^4.3.4, vite@^8.3.5, optionalDependencies (+14 more)

### Community 16 - "Money"
Cohesion: 0.13
Nodes (6): tl(), tour(), {closure#2}(), MeterBillService, Money, {closure#1}()

### Community 18 - "Meeting"
Cohesion: 0.21
Nodes (3): {closure#2}(), MeetingController, Meeting

### Community 19 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.18
Nodes (17): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+9 more)

### Community 21 - "Carbon\CarbonImmutable"
Cohesion: 0.16
Nodes (3): LateFeeController, {closure#2}(), {closure#1}()

### Community 22 - "Unit"
Cohesion: 0.13
Nodes (7): AdvanceController, {closure#1}(), Unit, {closure#1}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 23 - "Illuminate\Http\Request"
Cohesion: 0.18
Nodes (4): {closure#4}(), {closure#5}(), {closure#6}(), ReservationController

### Community 25 - "2026_11_02_100200_create_operations_tables.php"
Cohesion: 0.11
Nodes (15): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#2}() (+7 more)

### Community 27 - "Illuminate\Database\Migrations\Migration"
Cohesion: 0.13
Nodes (6): {closure#1}(), {closure#2}(), {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}()

### Community 28 - "Illuminate\Database\Eloquent\Relations\BelongsToMany"
Cohesion: 0.16
Nodes (3): {closure#7}(), SiteNotification, UnitGroup

### Community 31 - "AppServiceProvider.php"
Cohesion: 0.12
Nodes (5): AppServiceProvider, {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}()

### Community 33 - "BankImportService.php"
Cohesion: 0.15
Nodes (7): {closure#10}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#7}(), {closure#8}(), {closure#9}()

### Community 34 - "InstallService"
Cohesion: 0.05
Nodes (11): SetupController, EnsureAdmin, EnsureInstalled, EnsureResident, EnsureSiteRole, {closure#1}(), InstallService, {closure#1}() (+3 more)

### Community 35 - ".units"
Cohesion: 0.11
Nodes (3): {closure#2}(), StructureController, {closure#1}()

### Community 36 - "Debt"
Cohesion: 0.11
Nodes (10): {closure#2}(), {closure#13}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#8}(), {closure#15}(), Debt (+2 more)

### Community 38 - "Expense"
Cohesion: 0.13
Nodes (3): Expense, Staff, {closure#1}()

### Community 39 - "2026_11_02_100100_create_finance_tables.php"
Cohesion: 0.15
Nodes (10): {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}() (+2 more)

### Community 41 - "BankController"
Cohesion: 0.15
Nodes (5): BankController, {closure#1}(), {closure#3}(), {closure#4}(), BankImportService

### Community 45 - "ReportService.php"
Cohesion: 0.15
Nodes (3): {closure#6}(), {closure#7}(), {closure#8}()

### Community 46 - "2026_11_02_100000_create_apartment_structure_tables.php"
Cohesion: 0.20
Nodes (7): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}(), {closure#7}()

### Community 47 - "Controller"
Cohesion: 0.22
Nodes (3): Controller, MeterBillController, MeterBill

### Community 48 - "FinanceService"
Cohesion: 0.11
Nodes (6): {closure#1}(), {closure#2}(), DebtActionController, {closure#10}(), FinanceService, PayrollService

### Community 50 - "composer.json"
Cohesion: 0.22
Nodes (8): description, keywords, license, minimum-stability, name, prefer-stable, $schema, type

### Community 51 - "scripts"
Cohesion: 0.22
Nodes (9): scripts, dev, post-autoload-dump, post-create-project-cmd, post-root-package-install, post-update-cmd, pre-package-uninstall, setup (+1 more)

### Community 52 - "2026_11_10_000100_extend_accounting.php"
Cohesion: 0.22
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}()

### Community 54 - "DatabaseSeeder.php"
Cohesion: 0.17
Nodes (3): DebtPayment, LedgerEntry, DatabaseSeeder

### Community 55 - "require-dev"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/boost, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision (+1 more)

### Community 59 - "config"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 61 - "ExpenseController.php"
Cohesion: 0.17
Nodes (3): {closure#4}(), ExpenseController, Vendor

### Community 64 - "DuesDefinition"
Cohesion: 0.18
Nodes (3): {closure#2}(), DuesController, DuesDefinition

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

### Community 75 - "app.js"
Cohesion: 0.18
Nodes (13): formats, MODALS, renderPrefs(), setPref, startPanelTour(), storedTheme(), syncHud(), syncTheme() (+5 more)

### Community 94 - "laravel-boost"
Cohesion: 0.29
Nodes (6): command, enabled, type, mcp, laravel-boost, $schema

### Community 103 - "layout.blade.php"
Cohesion: 0.50
Nodes (3): components.dash.classic-hud, components.dash.classic-nav, components.dash.settings-modal

## Knowledge Gaps
- **12 isolated node(s):** `components.dash.classic-hud`, `components.dash.classic-nav`, `components.dash.settings-modal`, `site.finance._transactions`, `site.finance._transactions` (+7 more)
  These have ≤1 connection - possible missing edges. (Counts symbols only; 432 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **113 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Apartment` connect `Apartment` to `ResidentController.php`, `ReportController.php`, `OperationsController`, `FinanceException`, `ReportService`, `AccountingReportService.php`, `DashboardService.php`, `TestCase`, `Illuminate\Http\Request`, `CommunicationController.php`, `Illuminate\Database\Eloquent\Model`, `.categories`, `Cashbox`, `Money`, `Illuminate\Validation\Rule`, `Meeting`, `Carbon\CarbonImmutable`, `Unit`, `Illuminate\Http\Request`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `Document`, `NewModulesHttpTest`, `AppServiceProvider.php`, `CommunicationController`, `BankImportService.php`, `InstallService`, `.units`, `Debt`, `Expense`, `BankController`, `PayrollService`, `AuthController`, `User`, `ReportService.php`, `Controller`, `FinanceService`, `Block`, `DashboardService`, `DatabaseSeeder.php`, `ExpenseController.php`, `CommonArea`, `TicketController.php`, `DuesDefinition`, `PanelTest`?**
  _High betweenness centrality (0.254) - this node is a cross-community bridge._
- **What connects `components.dash.classic-hud`, `components.dash.classic-nav`, `components.dash.settings-modal` to the rest of the system?**
  _12 weakly-connected nodes found - possible missing edges._
- **Should `ResidentController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.08571428571428572 - nodes in this community are weakly interconnected._
- **Why does `User` connect `User` to `ResidentController.php`, `ReportController.php`, `FinanceException`, `DashboardService.php`, `TestCase`, `Illuminate\Http\Request`, `Cashbox`, `Money`, `.collect`, `Carbon\CarbonImmutable`, `Unit`, `Property`, `UserController.php`, `Illuminate\Database\Eloquent\Relations\BelongsToMany`, `Document`, `NewModulesHttpTest`, `AppServiceProvider.php`, `BankImportService.php`, `InstallService`, `Debt`, `Expense`, `BankController`, `AuthController`, `ReportService.php`, `FinanceService`, `DashboardService`, `DatabaseSeeder.php`, `UserFactory.php`, `UserController.php`, `PanelTest`, `ResidentApiTest`?**
  _High betweenness centrality (0.068) - this node is a cross-community bridge._
- **Should `ReportController.php` be split into smaller, more focused modules?**
  _Cohesion score 0.06161616161616162 - nodes in this community are weakly interconnected._
- **Why does `SetupWizardTest` connect `SetupWizardTest` to `TestCase`, `NewModulesHttpTest`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **Should `OperationsController` be split into smaller, more focused modules?**
  _Cohesion score 0.08377896613190731 - nodes in this community are weakly interconnected._