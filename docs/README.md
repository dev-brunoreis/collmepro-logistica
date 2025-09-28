# Collmepro Logistics System Project Documentation

## Overview

This directory contains all documentation for the centralized logistics system for Collmepro and Collme Beauty. The documentation has been structured to facilitate development with AI agents.

## ⚠️ URGENT TIMELINE: January 15 Deadline

**Total available time: ~15 days**
**Accelerated development required**

## Estrutura da Documentação

### 📋 [00-database-uml.md](00-database-uml.md)
Database UML diagram with table relationships.

### 📚 [01-project-knowledge-base.md](01-project-knowledge-base.md)
Complete project knowledge base, including:
- System overview
- Technical architecture
- Database structure
- External integrations
- System features
- User profiles
- Development principles

### 📝 [02-detailed-requirements.md](02-detailed-requirements.md)
Detailed system requirements, including:
- Integration specifications
- Features per screen
- Acceptance criteria
- System configurations

### 🏗️ [03-agent-architecture.md](03-agent-architecture.md)
Agent architecture for development, including:
- Agent types and responsibilities
- Workflow
- Implementation patterns
- Quality criteria

### 🚀 [04-development-phases.md](04-development-phases.md)
Progressive development phases, including:
- 6 implementation phases
- Estimated schedule
- Acceptance criteria per phase
- Deliverables

### 🤖 [05-agent-prompts.md](05-agent-prompts.md)
Specific prompts for each agent type, including:
- Agent Master (Coordinator)
- Agent Tester (Testing Specialist)
- Agent Backend (Laravel Specialist)
- Agent Frontend (Vue.js Specialist)
- Agent Integration (API Specialist)

### 🔒 [09-permission-system.md](09-permission-system.md)
Comprehensive permission system documentation, including:
- Role-Based Access Control (RBAC) architecture
- Permission definitions and categories
- Role assignments (Admin, Manager, Sales)
- Backend and frontend security implementation
- Testing and maintenance procedures

## How to Use This Documentation

### For Developers
1. Start by reading the [knowledge base](01-project-knowledge-base.md)
2. Consult the [detailed requirements](02-detailed-requirements.md) to understand scope
3. Follow the [development phases](04-development-phases.md) for progressive implementation

### For AI Agents
1. Use the [specific prompts](05-agent-prompts.md) for each agent type
2. Follow the [agent architecture](03-agent-architecture.md) for coordination
3. Consult the [knowledge base](01-project-knowledge-base.md) for context

### For Project Managers
1. Use the [phase schedule](04-development-phases.md) for planning
2. Consult the [acceptance criteria](02-detailed-requirements.md) for validation
3. Monitor progress through the [agent architecture](03-agent-architecture.md)

## Project Principles

### Development
- **TDD**: Test-First Development
- **DRY**: Don't Repeat Yourself
- **SOLID**: Object-oriented design principles
- **KISS**: Keep It Simple, Stupid

### Quality
- Clean and readable code
- Comprehensive tests
- Updated documentation
- Optimized performance

### Focus
- Implement only what was requested
- Progressive development
- Testable functionalities
- Continuous value delivery

## Technologies Used

- **Backend**: Laravel 12 (PHP 8.4)
- **Frontend**: Vue.js with Inertia.js
- **Database**: MySQL (production) / SQLite (development)
- **Authentication**: Laravel Fortify
- **Testing**: PHPUnit
- **Build**: Vite

## External Integrations

- **WooCommerce API**: Order import
- **Shopify API**: Order import
- **Bling API**: Invoices and labels
- **Correios API**: Tracking
- **Total Express API**: Tracking
- **Melhor Envio API**: Tracking

## System Structure

### User Profiles & Permissions
- **Admin**: Full system access with all 10 permissions
- **Manager**: Operational access with 6 permissions (orders, dashboard, kanban, labels)
- **Sales**: Read-only access with 4 permissions (orders view, dashboard, kanban, labels)
- **Security**: Role-Based Access Control (RBAC) with database-driven permissions

### Main Screens
- **Orders**: Complete order management
- **Kanban**: Visual flow tracking
- **Labels**: Shipping label control
- **Dashboard**: Metrics and overview

## Documentation Maintenance

This documentation should be updated whenever:
- New functionalities are implemented
- Architecture changes are made
- New requirements are identified
- Problems or improvements are discovered

## Contact

For questions about the documentation or project, consult the knowledge base or specific agent prompts.

## 🚨 URGENT: January 15 Deadline

**This accelerated timeline requires:**
- Maximum efficiency
- Parallel development where possible
- Focus on core functionality
- Essential features only
- Comprehensive testing after core completion
