# Bling API Configuration

## Environment Variables

Add the following environment variables to your `.env` file:

```env
# Bling API Configuration
BLING_BEARER_TOKEN=your_bling_bearer_token_here
BLING_BASE_URL=https://bling.com.br/Api/v3
BLING_TIMEOUT=30
BLING_RATE_LIMIT=1
BLING_LOGGING_ENABLED=true
BLING_LOG_REQUESTS=false
BLING_LOG_RESPONSES=false
```

## Configuration Details

- **BLING_BEARER_TOKEN**: Your Bling API bearer token (required)
- **BLING_BASE_URL**: Base URL for Bling API (default: https://bling.com.br/Api/v3)
- **BLING_TIMEOUT**: Request timeout in seconds (default: 30)
- **BLING_RATE_LIMIT**: Requests per second (default: 1)
- **BLING_LOGGING_ENABLED**: Enable logging (default: true)
- **BLING_LOG_REQUESTS**: Log requests (default: false)
- **BLING_LOG_RESPONSES**: Log responses (default: false)

## Usage

The BlingService is configured to use only the Bearer Token for authentication. Make sure to set the `BLING_BEARER_TOKEN` environment variable with your valid token from the Bling platform.

## API Endpoints

The service provides methods to:
- Fetch invoices with various filters
- Get invoice by order number
- Fetch invoices by date range
- Fetch invoices by status
- Get PDF URLs for invoices
- Validate and extract invoice data

All requests are made using Bearer Token authentication in the Authorization header.
