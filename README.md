# Collmepro Logistics System

<div align="center">

![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel&logoColor=white)
![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=flat&logo=vue.js&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-red.svg)

**Centralized logistics management system for Collmepro and Collme Beauty e-commerce platforms**

[Features](#features) • [Tech Stack](#tech-stack) • [Quick Start](#quick-start) • [Documentation](#documentation)

</div>

---

## Overview

Collmepro Logistics System is a centralized platform designed to streamline logistics operations across multiple e-commerce stores. It automatically synchronizes orders, invoices, and tracking information from different platforms, providing a unified interface for managing the entire logistics workflow.

### Key Capabilities

- **Multi-Store Integration**: Manage orders from Collmepro, Collmepro Atacadista, and Collme Beauty in one place
- **Automated Synchronization**: Real-time order import and status updates via API integrations
- **Visual Workflow**: Kanban board for tracking orders through each logistics stage
- **Role-Based Access**: Granular permissions for Admin, Manager, and Sales users
- **Complete Traceability**: Full history tracking and audit logs for all operations

---

## Features

### 📦 Order Management
- Import orders from multiple WooCommerce and Shopify stores
- Advanced search and filtering capabilities
- Real-time status updates via WebSockets
- Complete order lifecycle tracking
- Bulk operations and batch processing

### 🏭 Logistics Workflow
- **Picking**: Order separation tracking
- **Packing**: Packaging control
- **Labeling**: Shipping label management
- **Pickup**: Collection coordination
- Kanban view for visual progress monitoring

### 📄 Invoice Integration
- Automatic NFE (Brazilian tax invoice) synchronization from Bling
- PDF invoice linking and storage
- Sequential sync flow: Order → Invoice → Tracking
- Automatic reprocessing for pending invoices

### 📍 Tracking Management
- Automatic tracking code extraction from invoices
- Multi-carrier support (Correios, Total Express, Melhor Envio)
- Real-time tracking status updates
- Historical tracking data retention

### 👥 User Management
- Role-based access control (Admin, Manager, Sales)
- User CRUD operations (Admin only)
- Two-factor authentication (2FA) support
- Complete audit trail

### 📊 Dashboard & Reports
- Real-time metrics and KPIs
- Order status distribution
- Performance analytics
- Export capabilities (Excel, PDF)

---

## Tech Stack

### Backend
- **Framework**: Laravel 12.x
- **Language**: PHP 8.2+
- **Authentication**: Laravel Fortify (with 2FA)
- **ORM**: Eloquent
- **Jobs/Queues**: Laravel Queue (multi-queue support)
- **WebSockets**: Laravel Reverb
- **PDF Generation**: DomPDF
- **Excel Export**: Maatwebsite Excel

### Frontend
- **Framework**: Vue.js 3.5
- **Adapter**: Inertia.js 2.0
- **Routing**: Laravel Wayfinder
- **UI Components**: Reka UI
- **Styling**: Tailwind CSS 4.0
- **Icons**: Lucide Vue Next, Heroicons
- **Build Tool**: Vite 7.0
- **Language**: TypeScript

### Database
- **Production**: MySQL 8.0+
- **Development**: SQLite 3.35+
- **Migrations**: Laravel Migrations

### DevOps
- **Code Quality**: Laravel Pint, ESLint, Prettier
- **Testing**: PHPUnit 11.5
- **Process Management**: Supervisor (production)
- **Task Scheduling**: Laravel Scheduler

---

## Quick Start

### Prerequisites

- PHP 8.2 or higher
- Composer 2.0+
- Node.js 18+ or 20+
- MySQL 8.0+ (production) or SQLite (development)
- Redis 6.0+ (optional, recommended for production)

### Installation

```bash
# Clone the repository
git clone git@github.com:Willian-Assis/Collmepro-logistica.git collmepro-logistica
cd collmepro-logistica

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Setup database (SQLite for development)
touch database/database.sqlite
php artisan migrate

# Start development environment
composer run dev
```

The `composer run dev` command starts all necessary services:
- Laravel Server (http://localhost:8000)
- Queue Workers (invoices, tracking, sellers)
- Laravel Reverb (WebSockets)
- Vite Dev Server (Hot Module Replacement)
- Laravel Pail (Real-time logs)

For detailed installation instructions, see [docs/02-instalacao.md](docs/02-instalacao.md).

---

## Development

### Available Commands

```bash
# Full development environment
composer run dev

# Run tests
composer run test

# Build for production
npm run build

# Run with SSR (Server-Side Rendering)
composer run dev:ssr
npm run build:ssr

# Individual services
php artisan serve                                    # Laravel server
php artisan queue:work --queue=invoices --tries=3   # Invoice queue
php artisan reverb:start                             # WebSockets server
npm run dev                                          # Vite dev server
php artisan pail --timeout=0                         # Log viewer
```

### Testing

```bash
# Run all tests
composer run test

# Run specific test
php artisan test --filter=TestName

# Run with coverage
php artisan test --coverage
```

---

## Project Structure

```
collmepro-logistica/
├── app/
│   ├── Http/Controllers/     # HTTP controllers
│   ├── Models/               # Eloquent models
│   ├── Services/             # Business logic services
│   ├── Jobs/                 # Background jobs
│   └── Events/               # Event classes
├── resources/
│   ├── js/
│   │   ├── Pages/           # Vue.js page components
│   │   └── Components/      # Reusable Vue components
│   └── views/               # Blade templates
├── database/
│   ├── migrations/          # Database migrations
│   └── seeders/             # Database seeders
├── tests/
│   ├── Unit/                # Unit tests
│   └── Feature/             # Feature/Integration tests
├── docs/                    # Comprehensive documentation
└── routes/                  # Application routes
```

---

## External Integrations

### Bling API v3
- **Purpose**: Order synchronization, invoices (NFE), tracking data
- **Authentication**: OAuth 2.0 (Client Credentials)
- **Rate Limit**: 3 requests/second
- **Stores**: Collmepro Pro (204303730), Collmepro Atacadista (204651922)

### Tracking APIs
- **Correios**: Brazilian postal service tracking
- **Total Express**: Express delivery tracking
- **Melhor Envio**: Multi-carrier shipping platform

### Configuration

All API credentials are configured via environment variables. See [docs/06-integracoes.md](docs/06-integracoes.md) for detailed setup instructions.

---

## Documentation

Comprehensive documentation is available in the `docs/` directory:

- [01-visao-geral.md](docs/01-visao-geral.md) - System Overview
- [02-instalacao.md](docs/02-instalacao.md) - Installation & Configuration
- [03-arquitetura.md](docs/03-arquitetura.md) - Architecture & Design
- [04-banco-de-dados.md](docs/04-banco-de-dados.md) - Database Structure
- [05-autenticacao.md](docs/05-autenticacao.md) - Authentication & Authorization
- [06-integracoes.md](docs/06-integracoes.md) - External Integrations
- [07-modulos.md](docs/07-modulos.md) - System Modules
- [08-jobs.md](docs/08-jobs.md) - Background Jobs
- [09-frontend.md](docs/09-frontend.md) - Frontend Development
- [10-rotas-api.md](docs/10-rotas-api.md) - API Routes
- [11-services.md](docs/11-services.md) - Service Layer
- [12-testes.md](docs/12-testes.md) - Testing Guide
- [13-deploy.md](docs/13-deploy.md) - Deployment Guide
- [14-manutencao.md](docs/14-manutencao.md) - Maintenance
- [15-comandos.md](docs/15-comandos.md) - Command Reference

---

## Roadmap

### ✅ Completed

#### Setup
- [x] Setup Laravel + Vue project  
- [x] Configure MySQL database  
- [x] Setup PHPUnit for testing

#### Users & Authentication
- [x] Write tests for authentication (login/logout, roles)  
- [x] Write tests for users CRUD (admin only)  
- [x] Implement authentication with multi-user roles (Admin, Manager, Sales)  
- [x] Implement users module (CRUD)  

#### Orders
- [x] Write tests for WooCommerce API mock (import paid orders)  
- [x] Write tests for Shopify API mock (import paid orders)
- [x] Implement WooCommerce orders import (Collmepro, Atacadista)  
- [x] Implement Shopify orders import (Collme Beauty)
- [x] Implement Orders screen with all required columns
- [x] Implement filters and search on Orders screen  

#### Invoices (Bling)
- [x] Write tests for Bling API mock (fetch invoices, link PDF)  
- [x] Implement Bling integration (invoices/NFE link)  
- [x] Implement sequential sync flow (Order → NFE → Tracking)
- [x] Implement reprocessing for pending invoices
- [x] Automatic tracking extraction from NFE transport data  

#### Logistics
- [x] Tracking extraction from Bling NFE transport data
- [x] Support for Correios, Total Express, Melhor Envio
- [x] Automatic tracking fetch for orders with DANFE

#### UI & Navigation
- [x] Implement side menu (collapsible) with logo and navigation
- [x] My Account (edit name/email/password)
- [x] Orders screen
- [x] Users screen (Admin only)

#### Kanban
- [ ] Write tests for order progression through statuses  
- [x] Implement Kanban screen with steps:  
  - Orders Issued  
  - Invoicing (Bling)  
  - Picking  
  - Packing  
  - Label (tracking code)  
  - Logistics  
  - Pickup (Pending | Done)  

#### Dashboard
- [x] Implement Dashboard with metrics and overview

---

## Development Principles

This project follows best practices and modern development principles:

- **TDD** (Test-Driven Development): Tests written before implementation
- **DRY** (Don't Repeat Yourself): Reusable, modular code
- **SOLID**: Object-oriented design principles
- **KISS** (Keep It Simple): Simple, maintainable solutions
- **Event-Driven**: Event-based architecture for decoupling

---

## License

This project is proprietary software owned by CollMe!. All rights reserved. See the [LICENSE](LICENSE) file for details.

---

## Credits

<div align="center">
  <sub>2025 - Built by <a href="https://brunoreis.dev">Bruno Reis</a></sub>
</div>
