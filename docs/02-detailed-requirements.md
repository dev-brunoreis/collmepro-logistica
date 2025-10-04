# Detailed System Requirements

## 1. External Integrations

### 1.1 Bling API ✅ IMPLEMENTED
**Objective**: Primary integration for order management, invoices, and tracking

**Current Implementation**:
- **BlingService**: Complete API integration service
- **BlingSyncJob**: Background synchronization job
- **Two-Stage Sync**: Orders → Invoices → Tracking
- **Raw Data Preservation**: Complete JSON audit trail
- **Error Handling**: Comprehensive error management and retry logic

**Data Managed**:
- Complete order information (order_summary table)
- Detailed order data (order_data table)
- Invoice/NFE data (invoices table)
- Tracking information (tracking table)
- Order status tracking (order_statuses table)
- Operation history (order_histories table)

**Features Implemented**:
- ✅ Complete order synchronization
- ✅ NFE data extraction and PDF links
- ✅ Tracking code extraction from transport data
- ✅ Automatic status updates
- ✅ Background job processing
- ✅ Data validation and error handling
- ✅ Sync logging and monitoring

### 1.2 WooCommerce API ⏳ PLANNED
**Objective**: Capture orders from Collmepro and Collmepro Atacadista systems

**Planned Data to extract**:
- Order number
- Order date
- Customer name
- Seller name (seller field)
- Status: only paid orders (PROCESSING)

**Planned Implementation**:
- Service for API communication
- Job for background import
- Received data validation
- API error handling

### 1.3 Shopify API ⏳ PLANNED
**Objective**: Capture orders from Collme Beauty system

**Planned Data to extract**:
- Order number
- Order date
- Customer name
- Seller name
- Status: only paid orders (PROCESSING)

**Planned Implementation**:
- Service for API communication
- Job for background import
- Received data validation
- API error handling

### 1.4 Tracking APIs ⏳ PLANNED

#### 1.4.1 Correios API
- Tracking code
- Status query
- Movement history

#### 1.4.2 Total Express API
- Tracking code
- Status query
- Movement history

#### 1.4.3 Melhor Envio API
- Tracking code
- Status query
- Movement history

## 2. User System and Permissions

### 2.1 Role-Based Access Control (RBAC)

The system implements a comprehensive permission system with three distinct roles and 10 specific permissions:

#### Permission Categories
1. **Orders** (`orders.view`, `orders.status.update`)
2. **Users** (`users.view`, `users.create`, `users.edit`, `users.delete`)
3. **Permissions** (`permissions.view`)
4. **Dashboard** (`dashboard.view`)
5. **Kanban** (`kanban.view`)
6. **Labels** (`labels.view`)

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

### 2.2 Permission Implementation

#### Backend Security
- **Middleware**: `CheckPermission` class protects all routes
- **Database**: `permissions` and `role_permissions` tables with proper relationships
- **Models**: `User`, `Permission`, `RolePermission` with relationship methods
- **Controllers**: Permission checks in all sensitive operations

#### Frontend Security
- **Conditional Rendering**: UI elements only visible to authorized users
- **Route Protection**: Inertia.js routes protected by backend middleware
- **Component Logic**: Vue components check user permissions before showing actions

#### Security Features
- **Route Protection**: All sensitive routes protected by permission middleware
- **UI Security**: Buttons and menu items only visible to authorized users
- **Data Security**: Users can only access data they have permission to view
- **Action Security**: Users cannot perform actions they don't have permission for

### 2.3 User Management ✅ IMPLEMENTED
**Current Implementation**:
- **UserController**: Complete CRUD operations
- **User Model**: Full user management with relationships
- **Authentication**: Laravel Fortify with Two-Factor Authentication
- **Profile Management**: Users can edit their own profiles
- **Role Management**: Admin, Manager, Sales, Developer roles

**Required fields**:
- Full name
- Email (unique)
- Username (unique)
- Password (hash)
- Profile/Role

**Features Implemented**:
- ✅ Create user (Admin only)
- ✅ Edit user (Admin only)
- ✅ Edit own profile (All users)
- ✅ Change password with confirmation
- ✅ User listing with pagination
- ✅ Search by name, email, username
- ✅ Role-based filtering
- ✅ Admin protection (cannot delete self)
- ✅ Role validation (admin cannot change own role)
- ✅ Two-factor authentication support

## 3. Orders Screen ✅ IMPLEMENTED

### 3.1 Current Implementation
**OrderController**: Complete order management with Bling integration
**OrderSummary Model**: Main order data with relationships
**Search & Filters**: Real-time search and filtering capabilities
**Status Management**: Picking, packing, picked_up status tracking

### 3.2 Table Columns (Current)

#### ORDER NUMBER
- Bling ID and numero
- Search by number
- Sorting

#### CUSTOMER NAME
- Full customer name (contato_nome)
- Search by name
- Alphabetical sorting

#### DATE
- Order issue date (data)
- Format: DD/MM/YYYY HH:MM
- Period filter
- Chronological sorting

#### TOTAL
- Order total value (valor)
- Currency formatting
- Sorting

#### STATUS
- Order status (situacao_valor)
- Status filtering
- Visual indicators

#### TRACKING CODE
- Tracking code from logistics
- Search by tracking code
- Link to tracking details

#### LOGISTICS PROVIDER
- Carrier used
- Provider filtering
- Status indicators

#### ORDER STATUS
- Picking status (manual checkbox)
- Packing status (manual checkbox)
- Picked up status (manual checkbox)
- User tracking (who marked each status)

### 3.3 Planned Enhancements ⏳ PLANNED

#### SYSTEM
- Order origin
- Values: Collmepro, Atacadista (WooCommerce), Collme Beauty (Shopify)
- Filter by system

#### NFE
- Invoice number
- Link to download PDF (if available)
- Status: Issued, Pending, Not issued

#### TRACKING
- Tracking code
- Link to popup with history
- Current status
- Last update

#### LOGISTICS
- Carrier used
- Values: Correios PAC, Correios SEDEX, Total Express, Melhor Envio
- Filter by carrier

#### TRACKING STATUS
- Current tracking status
- Values: Label issued, Dispatched, In transit, Delivered
- Automatic update via API

#### SELLER
- Seller name
- Only for WooCommerce (Collmepro/Atacadista)
- Filter by seller

#### CHECKOUT PICKING
- Manual checkbox
- Store: selected, user, date/time
- Only Admin/Manager can mark

#### PACKING
- Manual checkbox
- Store: selected, user, date/time
- Only Admin/Manager can mark

#### ORDER PICKED UP
- Manual checkbox
- Store: selected, user, date/time
- Only Admin/Manager can mark

### 3.2 Features

#### Search
- Search field at top
- Search in all fields
- Real-time search
- Result highlighting

#### Filters
- By date (period)
- By system
- By tracking status
- By carrier
- By seller
- By picking/packing status

#### Totals
- Display totals below columns
- Update according to filters
- Counters by status

#### Pagination
- 50 items per page (default)
- Options: 25, 50, 100, 200
- Navigation: first, previous, next, last

## 4. Kanban Screen

### 4.1 Kanban Tabs

#### ORDERS ISSUED
- Collme Pro (WooCommerce)
- Collme Beauty (Shopify)
- Cards with basic information
- Drag & drop between columns

#### INVOICING/NF ISSUANCE
- Orders with issued NFE
- Link to download PDF
- Invoice status

#### CHECKOUT/PICKING
- Orders marked for picking
- Filter from orders screen
- Cards with order information

#### PACKING
- Orders marked for packing
- Filter from orders screen
- Cards with order information

#### LABEL/TRACKING CODE
- Orders with tracking code
- Label status
- Link to tracking

#### LOGISTICS
- Orders in transit
- Carrier used
- Tracking status

#### PICKUP
- Waiting: orders ready for pickup
- Completed: orders picked up
- Cards with order information

### 4.2 Kanban Features
- Drag & drop between columns
- Real-time updates
- Filters by date and system
- Search by order number
- Counters per column

## 5. Labels Screen

### 5.1 Table Columns

#### Order Number
- Link to order
- Search by number
- Sorting

#### Label Number
- Unique label code
- Search by number
- Sorting

#### Issue Date
- Issue date and time
- Format: DD/MM/YYYY HH:MM
- Period filter
- Chronological sorting

#### Status
- Values: Issued, Posted, Expired
- Filter by status
- Visual indicator

### 5.2 Features

#### Filters
- By date (period)
- By status
- Quick filter: "Labels Generated Today"

#### Search
- Search field at top
- Search in all fields
- Real-time search

#### Export
- Export to XLS
- Export to PDF
- Apply filters to export
- Include all fields

#### Pagination
- 50 items per page (default)
- Options: 25, 50, 100, 200
- Complete navigation

## 6. Menu and Navigation

### 6.1 Side Menu
- Position: left
- Collapsible: show only icons
- Logo: Protocoll
- Responsive for mobile

### 6.2 Menu Items

#### My Account
- Edit name
- Edit email
- Change password
- Cannot change username
- Cannot change profile

#### Orders
- Link to orders screen
- Only Admin/Manager
- Icon: list

#### Dashboard
- System overview
- Main metrics
- Charts and statistics
- All profiles

#### Users
- User management
- Only Admin
- Icon: users

### 6.3 Menu Features
- Active item highlight
- Notification badges
- Tooltips when collapsed
- Smooth animations

## 7. Dashboard

### 7.1 Main Metrics
- Total orders (today, week, month)
- Orders by status
- Orders by system
- Orders by carrier
- Labels issued (today, week, month)

### 7.2 Charts
- Orders per day (last 30 days)
- Orders by system (pie)
- Tracking status (bars)
- Carriers (bars)

### 7.3 Widgets
- Orders pending picking
- Orders pending packing
- Expired labels
- Tracking without updates

## 8. Reports and Exports

### 8.1 Available Reports
- Orders by period
- Labels by period
- Tracking status
- Performance by carrier
- Sellers (WooCommerce)

### 8.2 Export Formats
- XLS (Excel)
- PDF
- CSV

### 8.3 Export Filters
- Apply same filters from screen
- Field selection
- Custom formatting

## 9. Notifications and Alerts

### 9.1 Notification Types
- Expired labels
- Tracking without updates
- Pending orders
- Integration errors

### 9.2 Notification Channels
- In-app (menu badges)
- Email (optional)
- Dashboard (widgets)

## 10. System Settings

### 10.1 API Settings
- Access tokens
- Endpoint URLs
- Timeouts
- Retry policies

### 10.2 Import Settings
- Import frequency
- Automatic filters
- Validations
- Error logs

### 10.3 Interface Settings
- Items per page
- Theme (light/dark)
- Language
- Timezone

## 11. Test Optimization (January 2025)

### 11.1 Removed Tests
- **BlingIntegrationTest.php**: Redundant integration tests (covered by unit tests)
- **Network timeout tests**: Covered by general error handling tests
- **Invalid JSON response tests**: Redundant with error handling coverage
- **Duplicate bearer token validation**: Consolidated into single test

### 11.2 Maintained Tests
- **All permission and security tests**: Critical for system security
- **All model relationship tests**: Essential for data integrity
- **All service functionality tests**: Core business logic coverage
- **All job processing tests**: Background processing validation

### 11.3 Test Statistics
- **Before optimization**: 184 tests
- **After optimization**: 179 tests
- **Reduction**: 5 tests (2.7%)
- **Coverage maintained**: ~85%
- **Performance improvement**: Faster test execution