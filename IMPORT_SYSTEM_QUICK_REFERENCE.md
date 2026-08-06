# Sales Import System - Quick Reference

## How It Works (Simple Flow)

1. **User uploads Excel file** → JavaScript sends via AJAX
2. **Server validates file** → Checks format, size (max 15MB)
3. **Server loads Excel** → Reads all rows into memory
4. **Server groups by Receipt No** → Groups rows with same receipt number
5. **Server processes each receipt** → Creates sale records in database
6. **Progress tracked in real-time** → Client polls server every 1 second
7. **Completion** → Page reloads showing results

## Key Components

### Frontend (JavaScript)
- Intercepts form submission
- Uploads file via XMLHttpRequest
- Shows upload progress (0-100%)
- Polls progress endpoint every 1 second
- Updates progress bar and messages

### Backend (PHP/Laravel)
- Validates file and user permissions
- Loads Excel using PHPSpreadsheet
- Groups rows by Receipt No
- Processes in database transaction
- Updates progress in Cache
- Returns JSON responses

### Progress Tracking
- Unique key: `import_progress_{user_id}_{timestamp}`
- Stored in Laravel Cache (10 min expiry)
- Updated at each stage:
  - File validation (1%)
  - File loading (3%)
  - Reading data (5-7%)
  - Grouping rows (10-20%)
  - Processing receipts (20-90%)
  - Complete (100%)

## Excel File Format

**Required Columns (in order):**
1. Supplier name
2. StockOut (quantity)
3. Commodity (product name)
4. SalesDate
5. Means of Payment (CONSIGNMENT/CASH)
6. ConfirmPrice (selling price)
7. Shops (shop name)
8. Discount
9. Receipt No (used for grouping)
10. Total Amount
11. Buying price
12. Selling Price1

**Note:** Rows with same Receipt No are grouped into one sale.

## Routes

- `GET /penjualan/import` - Display import form
- `POST /penjualan/import` - Process import
- `GET /penjualan/import/progress` - Get progress status

## Error Handling

- All errors caught and logged
- Database transaction ensures all-or-nothing
- Individual receipt errors don't stop import
- Errors displayed to user after completion

## Performance

- Memory limit: 512MB
- Execution time: 10 minutes max
- Garbage collection when memory > 400MB
- Progress updates every 100 rows (grouping) and per receipt (processing)

## Security

- Admin role required
- CSRF token validation
- File type and size validation
- SQL injection prevention (Eloquent ORM)



