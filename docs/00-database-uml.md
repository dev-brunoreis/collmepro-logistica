# Database UML – Collmepro Logistics System

```mermaid
erDiagram
    USERS {
        int id PK
        string name
        string email
        string username
        string password
        string role
        datetime created_at
        datetime updated_at
    }

    ORDERS {
        int id PK
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
        datetime created_at
        datetime updated_at
    }

    ORDER_STATUSES {
        int id PK
        int order_id FK
        boolean picking
        int picking_marked_by FK
        datetime picking_marked_at
        boolean packing
        int packing_marked_by FK
        datetime packing_marked_at
        boolean picked_up
        int picked_up_marked_by FK
        datetime picked_up_marked_at
    }

    INVOICES {
        int id PK
        int order_id FK
        string invoice_number
        string invoice_pdf_url
        datetime issued_at
        datetime created_at
        datetime updated_at
    }

    LABELS {
        int id PK
        int order_id FK
        string label_number
        string status
        datetime issued_at
        datetime created_at
        datetime updated_at
    }

    USERS ||--o{ ORDER_STATUSES : "marks"
    ORDERS ||--o{ ORDER_STATUSES : "has"
    ORDERS ||--o{ INVOICES : "has"
    ORDERS ||--o{ LABELS : "has"
