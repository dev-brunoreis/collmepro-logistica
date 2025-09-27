# Database UML Diagram

```mermaid
erDiagram
    users {
        bigint id PK
        string name
        string email UK
        string username UK
        timestamp email_verified_at
        string password
        string role
        string remember_token
        timestamp created_at
        timestamp updated_at
    }

    orders {
        bigint id PK
        string system
        string order_number
        string customer_name
        string seller_name
        datetime order_date
        string nfe_number
        string nfe_pdf_url
        string tracking_code
        string logistics_provider
        string tracking_status
        timestamp created_at
        timestamp updated_at
    }

    order_statuses {
        bigint id PK
        bigint order_id FK
        boolean picking
        bigint picking_marked_by FK
        datetime picking_marked_at
        boolean packing
        bigint packing_marked_by FK
        datetime packing_marked_at
        boolean picked_up
        bigint picked_up_marked_by FK
        datetime picked_up_marked_at
    }

    invoices {
        bigint id PK
        bigint order_id FK
        string invoice_number
        string invoice_pdf_url
        datetime issued_at
        timestamp created_at
        timestamp updated_at
    }

    labels {
        bigint id PK
        bigint order_id FK
        string label_number
        string status
        datetime issued_at
        timestamp created_at
        timestamp updated_at
    }

    users ||--o{ order_statuses : "picking_marked_by"
    users ||--o{ order_statuses : "packing_marked_by"
    users ||--o{ order_statuses : "picked_up_marked_by"
    
    orders ||--|| order_statuses : "has"
    orders ||--o{ invoices : "has"
    orders ||--o{ labels : "has"
```

## Relationships

- **users** 1:N **order_statuses** (picking_marked_by, packing_marked_by, picked_up_marked_by)
- **orders** 1:1 **order_statuses**
- **orders** 1:N **invoices**
- **orders** 1:N **labels**

## Notes

- `users.role` can be: 'admin', 'manager', 'sales'
- `orders.system` can be: 'woocommerce', 'shopify'
- `orders.logistics_provider` can be: 'correios_pac', 'correios_sedex', 'total_express', 'melhor_envio'
- `orders.tracking_status` can be: 'label_issued', 'dispatched', 'in_transit', 'delivered'
- `labels.status` can be: 'issued', 'posted', 'expired'