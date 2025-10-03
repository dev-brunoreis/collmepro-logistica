# Detailed System Requirements

## 1. External Integrations

### 1.1 WooCommerce API
**Objective**: Capture orders from Collmepro and Collmepro Atacadista systems

**Data to extract**:
- Order number
- Order date
- Customer name
- Seller name (seller field)
- Status: only paid orders (PROCESSING)

**Implementation**:
- Service for API communication
- Job for background import
- Received data validation
- API error handling

### 1.2 Shopify API
**Objective**: Capture orders from Collme Beauty system

**Data to extract**:
- Order number
- Order date
- Customer name
- Seller name
- Status: only paid orders (PROCESSING)

**Implementation**:
- Service for API communication
- Job for background import
- Received data validation
- API error handling

### 1.3 Bling API
**Objective**: Manage invoices and labels

**Features**:
- Capture invoice number
- Get link to download NFE PDF
- Manage shipping labels
- Label status (issued, posted)

**Implementation**:
- Service for API communication
- Model for invoices
- Model for labels
- Data validation

### 1.4 Tracking APIs

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

### 2.3 User Management
**Required fields**:
- Full name
- Email (unique)
- Username (unique)
- Password (hash)
- Profile/Role

**Features**:
- Create user
- Edit user
- Change profile
- Reset password
- Deactivate/Activate user

## 3. Orders Screen

### 3.1 Table Columns

#### SYSTEM
- Order origin
- Values: Collmepro, Atacadista (WooCommerce), Collme Beauty (Shopify)
- Filter by system

#### ORDER NUMBER
- Unique order number
- Search by number
- Sorting

#### CUSTOMER NAME
- Full customer name
- Search by name
- Alphabetical sorting

#### DATE
- Order issue date
- Format: DD/MM/YYYY HH:MM
- Period filter
- Chronological sorting

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