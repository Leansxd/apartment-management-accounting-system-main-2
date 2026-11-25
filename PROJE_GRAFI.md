# Apartman Yönetim ve Muhasebe Sistemi — Proje Grafı

Laravel 13 · PHP 8.3+ · Sanctum (API) · Google2FA · PHPUnit 12  
Kaynak: `app/`, `routes/`, `database/migrations/` analizi (2026-11-11).

> Not: `graphify-out/` klasöründeki eski graf (8 Kasım, Windows'ta üretilmiş) 10 Kasım migration'larından
> önceki durumu gösterir ve güncel değildir. Bu dosya güncel koddan elle üretilmiştir.

## 1. Mimari katmanlar

```mermaid
graph LR
    subgraph Giris["Giriş (routes)"]
        W[web.php]
        A[api.php]
    end
    subgraph MW["Middleware"]
        EI[EnsureInstalled]
        EA[EnsureAdmin]
        ER[EnsureResident]
        ESR[EnsureSiteRole]
        SH[SecurityHeaders]
    end
    subgraph Ctrl["Controller"]
        C_Auth[Auth / PasswordReset / TwoFactor]
        C_Fin[Finance / Expense / Dues / Advance / DebtAction / LateFee / Bank / Budget / Payroll]
        C_Rep[Report / AccountingReport / Dashboard]
        C_Ops[Operations / Security / Asset / Meeting / Document / Communication]
        C_Str[Apartment / Structure / Member / Property / User]
        C_Svc[Ticket / Reservation / MeterBill]
        C_Res[ResidentPortal / Api.Resident / Api.Auth]
        C_Sys[System / Setup / SiteSearch]
    end
    subgraph Svc["Service"]
        FS[FinanceService]
        ARS[AccountingReportService]
        RS[ReportService]
        MRS[ModuleReportService]
        MSS[ModuleStatsService]
        DS[DashboardService]
        BIS[BankImportService]
        ERS[ExchangeRateService]
        PS[PayrollService]
        MBS[MeterBillService]
        RSV[ReservationService]
        TS[TicketService]
        RES[ResidentService]
        REM[ReminderService]
        AT[AuditTrail]
        TFA[TwoFactorService]
        DBS[DatabaseBackupService]
        INS[InstallService]
        PCS[ProductionCheckService]
    end
    M[(Eloquent Models<br/>40 adet)]
    DB[(MySQL)]

    W --> MW --> Ctrl
    A --> C_Res
    C_Auth --> TFA
    C_Auth --> AT
    C_Fin --> FS
    C_Fin --> ERS
    C_Fin --> BIS
    C_Fin --> PS
    C_Rep --> ARS
    C_Rep --> RS
    C_Rep --> MRS
    C_Rep --> DS
    C_Ops --> AT
    C_Ops --> REM
    C_Str --> AT
    C_Str --> MSS
    C_Svc --> TS
    C_Svc --> RSV
    C_Svc --> MBS
    C_Res --> RES
    C_Res --> TS
    C_Res --> RSV
    C_Sys --> DBS
    C_Sys --> INS
    Svc --> M --> DB
```

## 2. Controller → Service bağımlılıkları

```mermaid
graph LR
    ApartmentController --> FinanceService
    ApartmentController --> ModuleStatsService
    ApartmentController --> AuditTrail
    FinanceController --> FinanceService
    FinanceController --> ExchangeRateService
    FinanceController --> ModuleStatsService
    FinanceController --> ReportService
    ExpenseController --> FinanceService
    ExpenseController --> ExchangeRateService
    DuesController --> FinanceService
    AdvanceController --> FinanceService
    DebtActionController --> FinanceService
    LateFeeController --> FinanceService
    BankController --> BankImportService
    BankController --> ExchangeRateService
    BudgetController --> AccountingReportService
    AccountingReportController --> AccountingReportService
    PayrollController --> PayrollService
    PayrollController --> ExchangeRateService
    ReportController --> ReportService
    ReportController --> ModuleReportService
    DashboardController --> DashboardService
    DashboardController --> ModuleStatsService
    CommunicationController --> ReminderService
    CommunicationController --> ReportService
    MemberController --> ReportService
    StructureController --> ReportService
    TicketController --> TicketService
    ReservationController --> ReservationService
    MeterBillController --> MeterBillService
    ResidentPortalController --> ResidentService
    ResidentPortalController --> TicketService
    ResidentPortalController --> ReservationService
    ResidentPortalController --> ReportService
    Api_ResidentController --> ResidentService
    Api_ResidentController --> TicketService
    Api_ResidentController --> ReservationService
    SystemController --> DatabaseBackupService
    SetupController --> InstallService
    AccountTwoFactorController --> TwoFactorService
    TwoFactorChallengeController --> TwoFactorService
```

## 3. Rota grupları (web.php)

```mermaid
graph TD
    R[web.php]
    R --> G1["guest: giriş, şifre sıfırlama, kurulum"]
    R --> G2["auth + prefix portal → ResidentPortalController (17 rota)<br/>+ middleware resident"]
    R --> G3["auth + admin"]
    G3 --> T["throttle:login"]
    G3 --> S["can:super-admin · prefix system → SystemController"]
    G3 --> ST["prefix sites/{apartment}<br/>can:manage + site.role<br/>ApartmentController + finans/operasyon/rapor controller'ları"]
    R2[api.php] --> SAN["Sanctum → Api\\AuthController, Api\\ResidentController"]
```

## 4. Veri modeli (ilişkiler)

```mermaid
erDiagram
    User ||--o{ Member : "hesap"
    User }o--o{ Apartment : "apartment_manager (role)"
    Apartment }o--|| User : "owner"
    Apartment ||--o{ Block : ""
    Block ||--o{ Unit : ""
    Apartment ||--o{ Unit : ""
    Apartment ||--o{ UnitGroup : ""
    Unit }o--o{ UnitGroup : "unit_group_unit"
    Unit ||--o{ Member : ""
    Apartment ||--o{ Member : ""

    Apartment ||--o{ Cashbox : ""
    Cashbox ||--o{ CashTransaction : ""
    Unit ||--o{ CashTransaction : ""
    Member ||--o{ CashTransaction : "payer"
    User ||--o{ CashTransaction : "performed_by"
    TransactionCategory ||--o{ CashTransaction : ""
    Debt ||--o{ CashTransaction : ""
    Apartment ||--o{ TransactionCategory : ""

    Unit ||--o{ Debt : ""
    TransactionCategory ||--o{ Debt : ""
    Debt ||--o{ DebtPayment : ""
    CashTransaction ||--o{ DebtPayment : ""
    Expense ||--o| Debt : "yeniden borçlandırma"

    Apartment ||--o{ Expense : ""
    Vendor ||--o{ Expense : ""
    Cashbox ||--o{ Expense : ""
    TransactionCategory ||--o{ Expense : ""
    Apartment ||--o{ Vendor : ""

    Apartment ||--o{ LedgerEntry : "çift taraflı kayıt"
    Apartment ||--o{ DuesDefinition : ""
    TransactionCategory ||--o{ DuesDefinition : ""
    TransactionCategory ||--o{ BudgetItem : ""
    Apartment ||--o{ BudgetItem : ""
    Apartment ||--o{ BankStatementLine : ""
    Cashbox ||--o{ BankStatementLine : ""
    CashTransaction ||--o| BankStatementLine : "eşleşme"

    Apartment ||--o{ Meter : ""
    Unit ||--o{ Meter : ""
    Meter ||--o{ MeterReading : ""
    Apartment ||--o{ MeterBill : ""

    Apartment ||--o{ CommonArea : ""
    CommonArea ||--o{ Reservation : ""
    Unit ||--o{ Reservation : ""
    User ||--o{ Reservation : ""
    Debt ||--o{ Reservation : "ücret"

    Apartment ||--o{ Ticket : ""
    Unit ||--o{ Ticket : ""
    User ||--o{ Ticket : ""
    Ticket ||--o{ TicketMessage : ""
    User ||--o{ TicketMessage : ""

    Apartment ||--o{ Meeting : ""
    Meeting ||--o{ MeetingAttendance : ""
    Unit ||--o{ MeetingAttendance : ""
    Meeting ||--o{ Decision : ""
    Apartment ||--o{ Poll : ""
    Poll ||--o{ PollOption : ""
    PollOption ||--o{ PollVote : ""
    Poll ||--o{ PollVote : ""

    Apartment ||--o{ Staff : ""
    Staff ||--o{ Task : ""
    Apartment ||--o{ Task : ""
    Apartment ||--o{ Asset : ""
    Vendor ||--o{ Asset : ""
    Apartment ||--o{ InventoryItem : ""
    Apartment ||--o{ SecurityEntry : ""
    Apartment ||--o{ Announcement : ""
    Apartment ||--o{ SiteNotification : ""
    Unit ||--o{ SiteNotification : ""
    Apartment ||--o{ Document : ""
    User ||--o{ Document : "uploaded_by"
    Apartment ||--o{ AuditLog : ""
    User ||--o{ AuditLog : ""
    User ||--o{ Property : "created_by"
```

## 5. Servis → Model etkileşimi (ana çekirdek)

```mermaid
graph LR
    FinanceService --> Cashbox & CashTransaction & Debt & DebtPayment & Expense & LedgerEntry & Unit & BankStatementLine
    AccountingReportService --> LedgerEntry & CashTransaction & Debt
    DashboardService --> CashTransaction & Debt & Expense & Ticket & Reservation & Property & BankStatementLine
    BankImportService --> BankStatementLine
    MeterBillService --> MeterReading & Debt
    ReservationService --> Reservation
    ResidentService --> Member
    AuditTrail --> AuditLog
    ExchangeRateService --> Cashbox & CashTransaction
    ReportService --> Cashbox & Debt
```

## 6. Test kapsamı

- `tests/Feature` (45 dosya): finans (FinanceService, kısmi/yabancı para ödeme, dönem kapama, otomatik faturalama), muhasebe raporları, portal/API, 2FA, kurulum, yedekleme, denetim izi, sayaç/rezervasyon, destek talepleri.
- `tests/Unit` (3 dosya): Money yazıyla tutar, ekstre tutar ayrıştırma.

## Gözlemler

- Merkez model **Apartment** (site); neredeyse tüm tablolar `apartment_id` ile ona bağlı (çok kiracılı yapı, `scopeBindings` + `can:manage`).
- Finans çekirdeği: **FinanceService** → `Debt → DebtPayment → CashTransaction → LedgerEntry` zinciri; `Expense` yeniden borçlandırma ile `Debt`'e dönüşebilir.
- `Property` modeli `apartment_id` taşımıyor (yalnızca `created_by`); site kapsamı dışında ayrı bir modül.
- Yetki katmanı üç kademeli: `super-admin` (sistem), `manage` + `site.role` (site yöneticisi rolleri, `SitePermissions`), `resident` (portal).
