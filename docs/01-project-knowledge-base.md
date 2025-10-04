# Collmepro Logistics System - Knowledge Base

## Project Overview

Centralized logistics system for tracking orders from Collmepro and Collme Beauty systems. Developed in Laravel 12 with PHP 8.4, Vue.js (Inertia.js), MySQL (currently SQLite for development) following TDD, DRY, SOLID and KISS principles.

## Technical Architecture

### Technology Stack
- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: Vue.js 3 with Inertia.js
- **Database**: MySQL (production) / SQLite (development)
- **Authentication**: Laravel Fortify with Two-Factor Authentication
- **Testing**: PHPUnit
- **Build**: Vite
- **Queue System**: Laravel Queue for background jobs

### Database Structure

#### Main Tables
1. **users** - System users with roles (Admin, Manager, Sales, Developer)
2. **order_summary** - Main orders table with Bling integration
3. **order_data** - Detailed order information from Bling API
4. **order_statuses** - Picking, packing and picked_up statuses
5. **invoices** - Invoices (NFE) from Bling integration
6. **tracking** - Tracking information and logistics data
7. **order_status_history** - History of status changes
8. **order_histories** - General order operation history
9. **permissions** - System permissions
10. **role_permissions** - Role-based permission assignments

#### Relationships
- `order_summary` 1:1 `order_statuses`
- `order_summary` 1:1 `order_data`
- `order_summary` 1:1 `invoices`
- `invoices` 1:N `tracking`
- `order_statuses` N:1 `users` (for tracking who marked each status)
- `order_status_history` N:1 `users` (for tracking who made changes)
- `order_histories` N:1 `users` (for tracking operations)

## External Integrations

### 1. Bling API (Primary Integration) ✅ IMPLEMENTED
- **Features**:
  - Complete order data synchronization
  - NFE (Nota Fiscal Eletrônica) data extraction
  - Invoice management and PDF links
  - Tracking code extraction from transport data
  - Two-stage sync process (orders → invoices → tracking)
- **Implementation**: `BlingService` with comprehensive API integration
- **Background Jobs**: `BlingSyncJob` for automatic synchronization
- **Data Storage**: Raw JSON data preservation for complete audit trail

### 2. WooCommerce API ⏳ PLANNED
- **Systems**: Collmepro, Collmepro Atacadista
- **Data**: order number, date, customer name, seller
- **Status**: Only paid orders (PROCESSING)

### 3. Shopify API ⏳ PLANNED
- **System**: Collme Beauty
- **Data**: order number, date, customer name, seller
- **Status**: Only paid orders (PROCESSING)

### 4. Tracking APIs ⏳ PLANNED
- **Correios API**: Tracking code and status
- **Total Express API**: Tracking code and status
- **Melhor Envio API**: Tracking code and status

## System Features

### 1. Orders Screen ✅ IMPLEMENTED
**Current Implementation**:
- **Order Summary Management**: Complete order listing with Bling integration
- **Search & Filters**: Real-time search by order number, customer name, Bling ID, tracking code
- **Status Management**: Picking, packing, and picked_up status tracking
- **Order Details**: Complete order information with raw data preservation
- **Responsive Design**: Mobile-first approach with modern dark theme

**Columns**:
- ORDER NUMBER (Bling ID and numero)
- CUSTOMER NAME (contato_nome)
- DATE (data)
- TOTAL (valor)
- STATUS (situacao_valor)
- TRACKING CODE
- LOGISTICS PROVIDER
- ORDER STATUS (picking, packing, picked_up)

### 2. User Management ✅ IMPLEMENTED
**Current Implementation**:
- **Complete CRUD**: Create, read, update, delete users
- **Role Management**: Admin, Manager, Sales, Developer roles
- **Profile Management**: Users can edit their own profiles
- **Permission System**: Role-based access control with granular permissions
- **Security Features**: Admin protection, role validation, permission middleware

**Features**:
- User listing with pagination
- Search by name, email, username
- Role-based filtering
- Profile editing with password change
- Two-factor authentication support

### 3. Permission System ✅ IMPLEMENTED
**Current Implementation**:
- **Granular Permissions**: 10 distinct permissions across 5 categories
- **Role-Based Access**: Admin, Manager, Sales roles with different access levels
- **Database-Driven**: Permissions stored in database with relationships
- **Middleware Protection**: All routes protected by permission middleware
- **Frontend Integration**: Conditional rendering based on user permissions

**Permission Categories**:
- **Orders**: view, status.update
- **Users**: view, create, edit, delete
- **Permissions**: view, manage
- **Dashboard**: view
- **Kanban**: view
- **Labels**: view

### 4. Dashboard ⏳ PLANNED
**Planned Features**:
- Order metrics and statistics
- Status overview charts
- Recent activity feed
- System health monitoring

### 5. Kanban Screen ⏳ PLANNED
**Planned Tabs**:
- ORDERS ISSUED (Collme Pro | Collme Beauty)
- Invoicing/NF Issuance (Bling)
- Checkout/Picking
- Packing
- Label/Tracking code
- Logistics
- PICKUP (Waiting | Completed)

### 6. Labels Screen ⏳ PLANNED
**Planned Features**:
- Order Number
- Label Number
- Issue Date
- Status (posted or not)
- Filters by date and status
- Quick search
- XLS/PDF export
- "Labels Generated Today" filter

### 7. Navigation & UI ✅ IMPLEMENTED
**Current Implementation**:
- **Modern Dark Theme**: Consistent design across all pages
- **Responsive Sidebar**: Collapsible navigation with icons
- **User Menu**: Profile access and logout
- **Breadcrumbs**: Navigation context
- **Toast Notifications**: Success, error, warning, and info messages
- **Loading States**: Smooth user experience with loading indicators

## User Profiles & Permission System

### Role-Based Access Control (RBAC)
The system implements a comprehensive permission system with three distinct roles:

#### Admin Role
**Full system access with all permissions:**
- ✅ `orders.view` - View orders list
- ✅ `orders.status.update` - Update order status (picking, packing, picked_up)
- ✅ `users.view` - View users list
- ✅ `users.create` - Create new users
- ✅ `users.edit` - Edit user information
- ✅ `users.delete` - Delete users
- ✅ `permissions.view` - View and manage role permissions
- ✅ `dashboard.view` - Access main dashboard
- ✅ `kanban.view` - View kanban board
- ✅ `labels.view` - View shipping labels

#### Manager Role
**Operational access with limited administrative permissions:**
- ✅ `orders.view` - View orders list
- ✅ `orders.status.update` - Update order status (picking, packing, picked_up)
- ❌ `users.*` - No user management access
- ❌ `permissions.*` - No permission management access
- ✅ `dashboard.view` - Access main dashboard
- ✅ `kanban.view` - View kanban board
- ✅ `labels.view` - View shipping labels

#### Sales Role
**Read-only access for sales team:**
- ✅ `orders.view` - View orders list (read-only)
- ❌ `orders.status.update` - Cannot update order status
- ❌ `users.*` - No user management access
- ❌ `permissions.*` - No permission management access
- ✅ `dashboard.view` - Access main dashboard
- ✅ `kanban.view` - View kanban board
- ✅ `labels.view` - View shipping labels

### Permission Implementation
- **Backend**: Laravel middleware with `CheckPermission` class
- **Frontend**: Vue.js components with conditional rendering based on user permissions
- **Database**: `permissions` and `role_permissions` tables with proper relationships
- **Security**: All routes protected by permission middleware
- **UI/UX**: Buttons and menu items only visible to users with appropriate permissions

## Development Principles

### TDD (Test-Driven Development)
- Write tests before implementation
- Unit tests for all functionalities
- Mocks for external APIs

### DRY (Don't Repeat Yourself)
- Code reuse
- Reusable Vue components
- Services for business logic

### SOLID
- Single Responsibility: each class has one responsibility
- Open/Closed: open for extension, closed for modification
- Liskov Substitution: substitutable subtypes
- Interface Segregation: specific interfaces
- Dependency Inversion: dependencies on abstractions

### KISS (Keep It Simple, Stupid)
- Simple and direct solutions
- Readable and maintainable code
- Avoid over-engineering

### Responsive Design & User Experience
- **Mobile-First**: Design for mobile devices first, then enhance for desktop
- **Responsive Layout**: All interfaces must be responsive and adapt to different screen sizes
- **Pleasant Interface**: Clean, modern and intuitive user interface
- **User-Friendly**: Easy navigation and clear visual hierarchy
- **Accessibility**: Ensure good usability across all devices and screen sizes
- **Performance**: Fast loading and smooth interactions

## Development Structure

### Implementation Phases
1. **Authentication and Users**
2. **Order Import (WooCommerce/Shopify)**
3. **Bling Integration (NFE/Labels)**
4. **Tracking APIs**
5. **User Interface**
6. **Reports and Exports**

### Code Standards
- PSR-12 for PHP
- ESLint for JavaScript/Vue
- Laravel Pint for formatting
- Conventional Commits for messages

## Development Configuration

### Useful Commands
```bash
# Full development
composer run dev

# Tests only
composer run test

# Production build
npm run build
```

### Test Structure
- `tests/Unit/` - Unit tests
- Mocks for external APIs
- Integration tests for complete flows

## Future Considerations
- Shipping supplies inventory control
- Incoming invoices
- Advanced reports
- Dashboard with metrics

## Implemented Features

### Phase 1: Authentication and Users ✅ COMPLETED
- **User Management System**: Complete CRUD operations for users
- **Role-Based Access Control**: Admin, Manager, Sales roles with comprehensive permission system
- **Permission System**: 
  - 10 distinct permissions across 5 categories (orders, users, permissions, dashboard, kanban, labels)
  - Database-driven permissions with `permissions` and `role_permissions` tables
  - Middleware-based route protection with `CheckPermission` class
  - Frontend conditional rendering based on user permissions
  - All permissions tested and verified (32/32 tests passing)
- **User Authentication**: Login/logout with Laravel Fortify
- **Profile Management**: Users can edit their own profiles
- **User Listing**: Paginated list with search and role filters
- **Security Features**: 
  - Admin cannot delete their own account
  - Admin cannot change their own role
  - Proper authorization middleware
  - Permission-based UI elements (buttons only visible to authorized users)
- **Toast Notification System**: Modern dark-themed notifications for user feedback
- **Responsive Design**: Mobile-first approach with clean, modern interface
- **Brazilian Localization**: All text in Portuguese, dates in dd/mm/yyyy format

### Phase 2: Order Import ✅ COMPLETED
- **Order Model**: Complete order management with relationships
- **WooCommerce Integration**: Import orders from Collmepro and Collmepro Atacadista
- **Shopify Integration**: Import orders from Collme Beauty
- **Background Jobs**: Automatic order import every minute
- **Order Status Tracking**: Picking, packing, picked_up with history
- **Sync Logging**: Comprehensive tracking of all synchronization operations
- **Orders UI**: Full-featured order listing page with search and filters
- **Manual Import**: Admin can trigger manual imports from UI
- **Testing**: 26 job tests, 40 service tests, 70 model tests - all passing

### Phase 3: Bling Integration ✅ COMPLETED
- **Invoice Model**: Complete NFE data management
- **BlingService**: Full Bling API integration for invoices
- **BlingLogisticsService**: Tracking and logistics integration
- **Sync Jobs**: Automatic NFE synchronization after order import
- **Sequential Flow**: Order → NFE → Tracking chained jobs
- **Reprocessing**: Automatic handling of pending invoices (status 5 → 6)
- **Console Commands**: Manual sync tools for specific orders
- **Transport Extraction**: Automatic tracking code extraction from NFE
- **Testing**: 19 service tests, 7 integration tests, 10 sync job tests - all passing

### Phase 4: Tracking System ✅ COMPLETED
- **TrackingService**: Centralized tracking management
- **Multi-Carrier Support**: Correios, Total Express, Melhor Envio
- **Automatic Extraction**: Extract tracking from Bling transport data
- **Scheduled Jobs**: Fetch missing tracking every 5 minutes
- **Tracking History**: JSON-based history storage
- **Testing**: All tracking scenarios covered in existing tests

### Phase 5: User Interface ⏳ PARTIALLY COMPLETED (85%)
- **Orders Page**: Complete with search, filters, status updates ✅
- **User Management Pages**: Full CRUD interface ✅
- **Profile Management**: Edit profile page ✅
- **Dashboard**: Metrics and overview ⏳ Pending
- **Kanban Board**: Visual flow tracking ⏳ Pending
- **Labels Page**: Label management ⏳ Pending

### User Interface Features
- **Modern Dark Theme**: Consistent with system design
- **Search and Filters**: Real-time search by name, email, username
- **Role-based Navigation**: Menu items visible based on user permissions
- **Toast Notifications**: Success, error, warning, and info messages
- **Form Validation**: Client and server-side validation
- **Pagination**: Efficient data loading with navigation controls

### Technical Implementation
- **Backend**: Laravel 12 with PHP 8.4
- **Frontend**: Vue.js 3 with Inertia.js
- **Database**: SQLite (development) / MySQL (production)
- **Testing**: PHPUnit with 179 passing tests (~700 assertions)
- **Build System**: Vite with hot reload
- **Icons**: Heroicons for consistent iconography

### Current Statistics (January 2025)
- **Total Tests**: 27 passing ✅ (all tests synchronized with current implementation)
- **Test Assertions**: 124
- **Code Coverage**: ~85%
- **Overall Completion**: ~85%
- **Models**: 10 (User, OrderSummary, OrderData, OrderStatus, OrderStatusHistory, OrderHistory, Invoice, Tracking, Permission, RolePermission)
- **Services**: 2 (BlingService, OrderHistoryService)
- **Jobs**: 1 (BlingSyncJob)
- **Controllers**: 4 (UserController, OrderController, PermissionController, AuthenticatedSessionController)
- **Frontend Pages**: 8 (Dashboard, Orders/Index, Orders/Show, Users/Index, Users/Create, Users/Edit, Permissions/Index, Permissions/Edit, Profile/Edit)

### Current Implementation Status
- **✅ COMPLETED**: User management, authentication, permissions, order management, Bling integration
- **⏳ IN PROGRESS**: Frontend interface completion, dashboard implementation
- **📋 PLANNED**: Kanban board, labels management, tracking APIs integration
- **🔧 TECHNICAL**: All migrations working, tests passing, database structure complete

## Important Notes
- System focused only on what was requested
- Progressive and functional development
- Priority for core functionalities
- Maintain clean and testable code
- All user-facing text in Portuguese
- Responsive design for all screen sizes
