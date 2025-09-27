# Development Phases

## Overview

The system development will be carried out in 6 progressive phases, each delivering complete and testable functionalities. Each phase follows TDD, DRY, SOLID and KISS principles.

## ⚠️ URGENT TIMELINE: January 15 Deadline

**Total available time: ~15 days**
**Accelerated development required**

## Phase 1: Authentication and Users

### Objective
Implement authentication system and user management with different access profiles.

### Features
- [x] Authentication with Laravel Fortify
- [x] Role system (Admin, Manager, Sales)
- [x] User CRUD (Admin only)
- [x] Authorization middleware
- [x] "My Account" screen for personal data editing
- [x] Toast notification system
- [x] Search and filters for user listing
- [x] Brazilian localization (Portuguese)
- [x] Responsive design
- [x] Security restrictions (admin cannot delete/change own role)

### Implementation

#### 1.1 Tests (Agent Tester)
```php
// tests/Feature/AuthTest.php
public function test_user_can_login()
public function test_user_can_logout()
public function test_admin_can_create_user()
public function test_manager_cannot_create_user()
public function test_user_can_edit_own_profile()
public function test_user_cannot_edit_username()
```

#### 1.2 Backend (Agent Backend)
```php
// app/Models/User.php - Add roles
// app/Http/Controllers/UserController.php
// app/Http/Middleware/CheckRole.php
// database/migrations/add_role_to_users_table.php
```

#### 1.3 Frontend (Agent Frontend)
```vue
<!-- resources/js/Pages/Users/Index.vue -->
<!-- resources/js/Pages/Users/Create.vue -->
<!-- resources/js/Pages/Users/Edit.vue -->
<!-- resources/js/Pages/Profile/Edit.vue -->
```

### Acceptance Criteria
- [x] User can login/logout
- [x] Admin can create/edit users
- [x] Manager and Sales cannot manage users
- [x] User can edit name, email and password
- [x] User cannot edit username
- [x] Middleware protects routes by role
- [x] Tests pass with 100% coverage
- [x] Toast notifications for user feedback
- [x] Search and filter functionality
- [x] Brazilian localization
- [x] Responsive design
- [x] Security restrictions implemented

### Status: ✅ COMPLETED
### Actual Time: 2 days
### Tests: 16 passing tests

---

## Phase 2: Order Import

### Objective
Implement order import from WooCommerce and Shopify systems.

### Features
- [ ] WooCommerce API Service
- [ ] Shopify API Service
- [ ] Background import jobs
- [ ] Received data validation
- [ ] API error handling
- [ ] Order model with relationships

### Implementation

#### 2.1 Tests (Agent Tester)
```php
// tests/Unit/Services/WooCommerceServiceTest.php
public function test_can_fetch_orders_from_woocommerce()
public function test_handles_api_errors_gracefully()
public function test_validates_order_data()

// tests/Unit/Services/ShopifyServiceTest.php
public function test_can_fetch_orders_from_shopify()
public function test_handles_api_errors_gracefully()
public function test_validates_order_data()

// tests/Feature/Jobs/ImportOrdersJobTest.php
public function test_can_import_woocommerce_orders()
public function test_can_import_shopify_orders()
public function test_handles_duplicate_orders()
```

#### 2.2 Backend (Agent Backend)
```php
// app/Services/WooCommerceService.php
// app/Services/ShopifyService.php
// app/Jobs/ImportWooCommerceOrdersJob.php
// app/Jobs/ImportShopifyOrdersJob.php
// app/Models/Order.php
// app/Http/Controllers/OrderController.php
```

#### 2.3 Integration (Agent Integration)
```php
// config/woocommerce.php
// config/shopify.php
// app/Http/Client/WooCommerceClient.php
// app/Http/Client/ShopifyClient.php
```

### Acceptance Criteria
- [ ] Imports only paid orders (PROCESSING)
- [ ] Validates required data
- [ ] Handles API errors properly
- [ ] Avoids order duplication
- [ ] Jobs run in background
- [ ] Import logs
- [ ] Tests pass with 100% coverage

### Estimate: 3 days (accelerated)

---

## Phase 3: Bling Integration

### Objective
Implement Bling API integration to manage invoices and labels.

### Features
- [ ] Bling API Service
- [ ] Invoice model for invoices
- [ ] Label model for labels
- [ ] NFE PDF download
- [ ] Label status management
- [ ] Synchronization jobs

### Implementation

#### 3.1 Tests (Agent Tester)
```php
// tests/Unit/Services/BlingServiceTest.php
public function test_can_fetch_invoices()
public function test_can_fetch_invoice_pdf_url()
public function test_can_fetch_labels()
public function test_handles_api_errors()

// tests/Feature/Models/InvoiceTest.php
public function test_invoice_belongs_to_order()
public function test_can_download_invoice_pdf()

// tests/Feature/Models/LabelTest.php
public function test_label_belongs_to_order()
public function test_can_update_label_status()
```

#### 3.2 Backend (Agent Backend)
```php
// app/Services/BlingService.php
// app/Models/Invoice.php
// app/Models/Label.php
// app/Jobs/SyncBlingDataJob.php
// app/Http/Controllers/InvoiceController.php
// app/Http/Controllers/LabelController.php
```

#### 3.3 Integration (Agent Integration)
```php
// config/bling.php
// app/Http/Client/BlingClient.php
// app/Jobs/DownloadInvoicePdfJob.php
```

### Acceptance Criteria
- [ ] Fetches invoices from Bling
- [ ] Gets PDF download link
- [ ] Manages shipping labels
- [ ] Updates label status
- [ ] Handles API errors
- [ ] Jobs run in background
- [ ] Tests pass with 100% coverage

### Estimate: 2 days (accelerated)

---

## Phase 4: Tracking APIs

### Objective
Implement integration with tracking APIs (Correios, Total Express, Melhor Envio).

### Features
- [ ] Correios API Service
- [ ] Total Express API Service
- [ ] Melhor Envio API Service
- [ ] Unified tracking system
- [ ] Automatic status updates
- [ ] Movement history

### Implementation

#### 4.1 Tests (Agent Tester)
```php
// tests/Unit/Services/CorreiosServiceTest.php
public function test_can_track_package()
public function test_handles_invalid_tracking_code()
public function test_handles_api_errors()

// tests/Unit/Services/TotalExpressServiceTest.php
public function test_can_track_package()
public function test_handles_invalid_tracking_code()
public function test_handles_api_errors()

// tests/Unit/Services/MelhorEnvioServiceTest.php
public function test_can_track_package()
public function test_handles_invalid_tracking_code()
public function test_handles_api_errors()

// tests/Feature/Services/TrackingServiceTest.php
public function test_can_track_package_from_any_provider()
public function test_updates_order_tracking_status()
public function test_stores_tracking_history()
```

#### 4.2 Backend (Agent Backend)
```php
// app/Services/CorreiosService.php
// app/Services/TotalExpressService.php
// app/Services/MelhorEnvioService.php
// app/Services/TrackingService.php
// app/Models/TrackingHistory.php
// app/Jobs/UpdateTrackingStatusJob.php
```

#### 4.3 Integration (Agent Integration)
```php
// config/correios.php
// config/total-express.php
// config/melhor-envio.php
// app/Http/Client/CorreiosClient.php
// app/Http/Client/TotalExpressClient.php
// app/Http/Client/MelhorEnvioClient.php
```

### Acceptance Criteria
- [ ] Tracks packages from all carriers
- [ ] Updates status automatically
- [ ] Stores movement history
- [ ] Handles invalid codes
- [ ] Handles API errors
- [ ] Jobs run in background
- [ ] Tests pass with 100% coverage

### Estimate: 3 days (accelerated)

---

## Phase 5: User Interface

### Objective
Implement all system screens with complete functionalities.

### Features
- [ ] Orders screen with all columns
- [ ] Kanban screen with drag & drop
- [ ] Labels screen with filters
- [ ] Dashboard with metrics
- [ ] Collapsible side menu
- [ ] Search and filter system
- [ ] Pagination and sorting

### Implementation

#### 5.1 Tests (Agent Tester)
```php
// tests/Feature/OrdersTest.php
public function test_admin_can_view_orders()
public function test_manager_can_view_orders()
public function test_sales_cannot_view_orders()
public function test_can_filter_orders_by_date()
public function test_can_search_orders()
public function test_can_update_order_status()

// tests/Feature/KanbanTest.php
public function test_can_view_kanban()
public function test_can_drag_order_between_columns()
public function test_updates_order_status_on_drag()

// tests/Feature/LabelsTest.php
public function test_can_view_labels()
public function test_can_filter_labels_by_date()
public function test_can_export_labels()
```

#### 5.2 Frontend (Agent Frontend)
```vue
<!-- resources/js/Pages/Orders/Index.vue -->
<!-- resources/js/Pages/Orders/Show.vue -->
<!-- resources/js/Pages/Kanban/Index.vue -->
<!-- resources/js/Pages/Labels/Index.vue -->
<!-- resources/js/Pages/Dashboard/Index.vue -->
<!-- resources/js/Components/OrdersTable.vue -->
<!-- resources/js/Components/KanbanBoard.vue -->
<!-- resources/js/Components/LabelsTable.vue -->
<!-- resources/js/Components/Sidebar.vue -->
```

#### 5.3 Backend (Agent Backend)
```php
// app/Http/Controllers/OrderController.php
// app/Http/Controllers/KanbanController.php
// app/Http/Controllers/LabelController.php
// app/Http/Controllers/DashboardController.php
// app/Http/Requests/OrderRequest.php
// app/Http/Requests/LabelRequest.php
```

### Acceptance Criteria
- [ ] All screens work correctly
- [ ] Filters and search work
- [ ] Pagination and sorting work
- [ ] Drag & drop in Kanban works
- [ ] Responsive for mobile
- [ ] Collapsible side menu
- [ ] Tests pass with 100% coverage

### Estimate: 4 days (accelerated)

---

## Phase 6: Reports and Exports

### Objective
Implement reporting and export system.

### Features
- [ ] Order reports
- [ ] Label reports
- [ ] XLS export
- [ ] PDF export
- [ ] Advanced filters
- [ ] Report scheduling

### Implementation

#### 6.1 Tests (Agent Tester)
```php
// tests/Feature/ReportsTest.php
public function test_can_generate_orders_report()
public function test_can_generate_labels_report()
public function test_can_export_to_xls()
public function test_can_export_to_pdf()
public function test_applies_filters_to_reports()

// tests/Unit/Services/ReportServiceTest.php
public function test_can_generate_orders_data()
public function test_can_generate_labels_data()
public function test_handles_empty_data()
```

#### 6.2 Backend (Agent Backend)
```php
// app/Services/ReportService.php
// app/Http/Controllers/ReportController.php
// app/Exports/OrdersExport.php
// app/Exports/LabelsExport.php
// app/Jobs/GenerateReportJob.php
```

#### 6.3 Frontend (Agent Frontend)
```vue
<!-- resources/js/Pages/Reports/Index.vue -->
<!-- resources/js/Pages/Reports/Orders.vue -->
<!-- resources/js/Pages/Reports/Labels.vue -->
<!-- resources/js/Components/ReportFilters.vue -->
<!-- resources/js/Components/ExportButtons.vue -->
```

### Acceptance Criteria
- [ ] Reports generate correct data
- [ ] XLS export works
- [ ] PDF export works
- [ ] Filters apply correctly
- [ ] Reports can be scheduled
- [ ] Tests pass with 100% coverage

### Estimate: 1 day (accelerated)

---

## Overall Schedule

| Phase | Duration | Dependencies | Deliverables |
|-------|----------|--------------|-------------|
| 1 | 2 days | - | Authentication and users |
| 2 | 3 days | Phase 1 | Order import |
| 3 | 2 days | Phase 2 | Bling integration |
| 4 | 3 days | Phase 2 | Tracking APIs |
| 5 | 4 days | Phases 1-4 | Complete interface |
| 6 | 1 day | Phase 5 | Reports and exports |

**Total estimated: 15 days**

## ⚠️ Critical Success Factors

1. **Parallel Development**: Phases 3 and 4 can run in parallel
2. **Simplified UI**: Focus on core functionality, minimal styling
3. **Mock APIs**: Use mocks for external APIs during development
4. **Daily Progress**: Each phase must be completed on schedule
5. **Testing**: Essential tests only, comprehensive testing after core features

## Quality Criteria per Phase

### 1. Test Coverage
- Minimum 60% coverage (reduced for timeline)
- Unit tests for critical classes
- Integration tests for main flows
- API tests with mocks

### 2. Code Quality
- Follow PSR-12
- Apply SOLID principles
- Readable and documented code
- No code duplication

### 3. Performance
- Optimized queries
- Adequate pagination
- Cache when necessary
- Jobs for heavy processing

### 4. Security
- Input validation
- Proper authorization
- SQL injection protection
- Data sanitization

## Delivery and Validation

### Per Phase
1. **Complete implementation** of functionality
2. **Tests passing** with adequate coverage
3. **Updated documentation**
4. **Validation** by acceptance criteria
5. **Approval** by Agent Master

### Final
1. **Complete and functional** system
2. **All tests** passing
3. **Complete documentation**
4. **Deploy** to test environment
5. **Validation** of PDF requirements

## Conclusion

This phase structure ensures development is:
- **Progressive**: Each phase delivers value
- **Testable**: Code always tested
- **Quality**: Following best practices
- **Focused**: Only what was requested
- **Maintainable**: Clean and documented code

## 🚨 URGENT: January 15 Deadline

**This accelerated timeline requires:**
- Maximum efficiency
- Parallel development where possible
- Focus on core functionality
- Essential features only
- Comprehensive testing after core completion