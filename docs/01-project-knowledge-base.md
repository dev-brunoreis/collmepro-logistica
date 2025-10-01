# Collmepro Logistics System - Knowledge Base

## Project Overview

Centralized logistics system for tracking orders from Collmepro and Collme Beauty systems. Developed in Laravel 12 with PHP 8.4, Vue.js (Inertia.js), MySQL (currently SQLite for development) following TDD, DRY, SOLID and KISS principles.

## Technical Architecture

### Technology Stack
- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: Vue.js with Inertia.js
- **Database**: MySQL (production) / SQLite (development)
- **Authentication**: Laravel Fortify
- **Testing**: PHPUnit
- **Build**: Vite

### Database Structure

#### Main Tables
1. **users** - System users with roles (Admin, Manager, Sales)
2. **orders** - Orders imported from external systems
3. **order_statuses** - Picking, packing and picked_up statuses
4. **invoices** - Invoices (Bling integration)
5. **labels** - Shipping labels

#### Relationships
- `orders` 1:1 `order_statuses`
- `orders` 1:N `invoices`
- `orders` 1:N `labels`
- `order_statuses` N:1 `users` (for tracking who marked each status)

## External Integrations

### 1. WooCommerce API
- **Systems**: Collmepro, Collmepro Wholesale
- **Data**: order number, date, customer name, seller
- **Status**: Only paid orders (PROCESSING)

### 2. Shopify API
- **System**: Collme Beauty
- **Data**: order number, date, customer name, seller
- **Status**: Only paid orders (PROCESSING)

### 3. Bling API
- **Features**:
  - Extract NFE data
  - Get link to download NFE PDF
  - Manage shipping labels

### 4. Tracking APIs
- **Correios API**: Tracking code and status
- **Total Express API**: Tracking code and status
- **Melhor Envio API**: Tracking code and status

## System Features

### 1. Orders Screen (Admin/Manager)
**Columns**:
- SYSTEM (Collmepro, Wholesale, Collme Beauty)
- ORDER NUMBER
- CUSTOMER NAME
- DATE
- NFE (linkable to PDF)
- TRACKING (popup with history)
- LOGISTICS (Correios, Total Express, Melhor Envio)
- TRACKING STATUS
- SELLER
- CHECKOUT PICKING (manual checkbox)
- PACKING (manual checkbox)
- ORDER PICKED UP (manual checkbox)

**Features**:
- Search across all fields
- Filters by date and columns
- Totals by applied filters

### 2. Kanban Screen (Manager/Admin/Sales)
**Tabs**:
- ORDERS ISSUED (Collme Pro | Collme Beauty)
- Invoicing/NF Issuance (Bling)
- Checkout/Picking
- Packing
- Label/Tracking code
- Logistics
- PICKUP (Waiting | Completed)

### 3. Labels Screen
**Columns**:
- Order Number
- Label Number
- Issue Date
- Status (posted or not)

**Features**:
- Filters by date and status
- Quick search
- XLS/PDF export
- "Labels Generated Today" filter

### 4. User Management (Admin only)
- User CRUD
- Profile/role definition
- Fields: name, email, username, password

### 5. Side Menu
- Protocoll Logo
- My Account (edit name, email, password)
- Orders
- Dashboard
- Users (Admin only)
- Collapsible (icons only)

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
- **WooCommerce Integration**: Import orders from Collmepro and Collmepro Wholesale
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

### Phase 5: User Interface ⏳ PARTIALLY COMPLETED (80%)
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
- **Testing**: PHPUnit with 181 passing tests (706 assertions)
- **Build System**: Vite with hot reload
- **Icons**: Heroicons for consistent iconography

### Current Statistics (January 2025)
- **Total Tests**: 181 passing ✅
- **Test Assertions**: 706
- **Code Coverage**: ~85%
- **Overall Completion**: ~75%
- **Models**: 9 (User, Order, OrderStatus, OrderStatusHistory, Invoice, Label, SyncLog, Permission, RolePermission)
- **Services**: 5 (WooCommerceService, ShopifyService, BlingService, BlingLogisticsService, TrackingService)
- **Jobs**: 8 (Import, Sync, Tracking jobs)
- **Controllers**: 4 (User, Profile, Order, Permission)
- **Frontend Pages**: 5 (Orders, Users x3, Profile)

## Important Notes
- System focused only on what was requested
- Progressive and functional development
- Priority for core functionalities
- Maintain clean and testable code
- All user-facing text in Portuguese
- Responsive design for all screen sizes
