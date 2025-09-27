# Collmepro Logistics System

Centralized logistics system for Collmepro and Collme Beauty.  

---

> **Note:** To view the database diagram, open the [`docs/00-database-uml.md`](docs/00-database-uml.md) file in a viewer that supports [Mermaid](https://mermaid-js.github.io/mermaid/#/).  
Tools like [VSCode with the Mermaid extension](https://marketplace.visualstudio.com/items?itemName=bierner.markdown-mermaid) or the [Mermaid Live Editor](https://mermaid.live/) can be used to render the diagram.

---

## Roadmap / TODOs

### Setup
- [x] Setup Laravel + Vue project  
- [x] Configure MySQL database  
- [x] Setup Pest/PHPUnit for testing  

### Users & Authentication
- [x] Write tests for authentication (login/logout, roles)  
- [x] Write tests for users CRUD (admin only)  
- [x] Implement authentication with multi-user roles (Admin, Manager, Sales)  
- [x] Implement users module (CRUD)  

### Orders
- [ ] Write tests for WooCommerce API mock (import paid orders)  
- [ ] Write tests for Shopify API mock (import paid orders)  
- [ ] Implement WooCommerce orders import (Collmepro, Wholesale)  
- [ ] Implement Shopify orders import (Collme Beauty)  
- [ ] Write tests for orders storage and search/filter  
- [ ] Implement Orders screen with columns:  
  - System  
  - Order Number  
  - Customer  
  - Date  
  - Invoice (NFE)  
  - Tracking (popup with history)  
  - Logistics  
  - Tracking Status  
  - Seller  
  - Picking (checkbox)  
  - Packing (checkbox)  
  - Picked-up (checkbox)  
- [ ] Implement filters and search on Orders screen  
- [ ] Display totals by filters  

### Invoices (Bling)
- [ ] Write tests for Bling API mock (fetch invoices, link PDF)  
- [ ] Implement Bling integration (invoices/NFE link)  

### Logistics
- [ ] Write tests for Correios API mock (tracking flow)  
- [ ] Write tests for Total Express API mock (tracking flow)  
- [ ] Write tests for Melhor Envio API mock (tracking flow)  
- [ ] Implement Correios integration (tracking)  
- [ ] Implement Total Express integration (tracking)  
- [ ] Implement Melhor Envio integration (tracking)  

### Kanban
- [ ] Write tests for order progression through statuses  
- [ ] Implement Kanban screen with steps:  
  - Orders Issued  
  - Invoicing (Bling)  
  - Picking  
  - Packing  
  - Label (tracking code)  
  - Logistics  
  - Pickup (Pending | Done)  

### Labels
- [ ] Write tests for Bling API mock (labels import)  
- [ ] Implement Labels screen:  
  - Columns: Order Number | Label Number | Date | Status  
  - Filters by date and status  
  - Quick filter for today  
  - Search by columns  
  - Export to XLS or PDF  

### UI & Navigation
- [ ] Implement side menu (collapsible) with:  
  - Logo  
  - My Account (edit name/email/password)  
  - Orders  
  - Dashboard  
  - Users (Admin only)  
---

<div align="center">
  <sub>2025 - Built by <a href="https://brunoreis.dev">Bruno Reis</a></sub>
</div>
