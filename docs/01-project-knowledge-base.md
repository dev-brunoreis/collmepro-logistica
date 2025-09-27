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

## User Profiles

### Admin
- Full system access
- User management
- All screens

### Manager
- Orders screen
- Kanban screen
- Labels screen
- Dashboard

### Sales
- Kanban screen
- Labels screen
- Dashboard

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

## Important Notes
- System focused only on what was requested
- Progressive and functional development
- Priority for core functionalities
- Maintain clean and testable code
