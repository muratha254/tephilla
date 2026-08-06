# Sales Import System Documentation

## Overview
The Sales Import System allows administrators to import sales data from Excel files (.xlsx or .xls) into the system. The system processes large files (up to 15MB) with real-time progress tracking and handles thousands of records efficiently.

## How the Import System Works

### 1. **Access Control & Page Loading**

**Route:** `GET /penjualan/import`
**Controller Method:** `PenjualanController@importForm()`

**Process:**
- User must be authenticated and have admin role
- Controller safely retrieves session data (errors, warnings, success messages)
- Session data is validated and passed to the view as variables
- View displays import form with instructions and file upload field

**Security:**
- Only administrators can access the import page
- Unauthorized users receive 403 error
- All errors are logged for debugging

---

### 2. **File Upload & Validation**

**Route:** `POST /penjualan/import`
**Controller Method:** `PenjualanController@import()`

**Frontend Process (JavaScript):**
1. User selects Excel file and clicks "Import Sales" button
2. JavaScript intercepts form submission (prevents default browser behavior)
3. Creates `FormData` object with file and CSRF token
4. Uses `XMLHttpRequest` for AJAX upload with progress tracking
5. Shows progress bar immediately (0-100% for file upload)
6. Sends request with headers:
   - `X-Requested-With: XMLHttpRequest`
   - `Accept: application/json`
   - `X-CSRF-TOKEN: [token]`

**Backend Process:**
1. **Initial Setup:**
   - Increases PHP memory limit to 512MB
   - Sets execution time limit to 600 seconds (10 minutes)
   - Validates user authentication and admin role

2. **Progress Tracking Initialization:**
   - Creates unique progress key: `import_progress_{user_id}_{timestamp}`
   - Stores initial progress in Laravel Cache (600 seconds expiry)
   - Progress structure:
     ```php
     [
         'status' => 'processing',
         'current' => 0,
         'total' => 0,
         'percentage' => 1,
         'message' => 'Validating file...'
     ]
     ```

3. **File Validation:**
   - Validates file exists and is valid Excel format (.xlsx, .xls)
   - Checks file size (max 15MB = 15360 KB)
   - Returns JSON error if validation fails

---

### 3. **Excel File Processing**

**Steps:**

1. **Load Excel File:**
   - Uses PHPSpreadsheet library to load file
   - Updates progress: "Loading Excel file..." (3%)

2. **Read Worksheet:**
   - Gets active worksheet
   - Updates progress: "Reading worksheet data..." (5%)
   - Converts worksheet to array (all rows loaded into memory)
   - Logs total row count

3. **Data Analysis:**
   - Removes header row
   - Updates progress: "File loaded, analyzing data..." (7%)
   - Counts total rows for processing

4. **Group by Receipt Number:**
   - Iterates through all rows
   - Extracts Receipt No from column 7 (0-indexed)
   - Groups rows with same Receipt No together
   - Updates progress every 100 rows (10-20%)
   - Skips empty rows
   - Logs skipped rows

**Receipt Number Detection Logic:**
- Primary: Column 7 (Receipt No column)
- If column 7 contains letters (e.g., "B54691"), it's a receipt number
- If column 7 is numeric and < 100000, it's a receipt number
- Fallback: Extract from Shops column (column 6) if pattern matches

---

### 4. **Database Import Process**

**Transaction Management:**
- Starts database transaction (all-or-nothing)
- If any error occurs, entire import is rolled back

**Processing Loop:**
For each unique Receipt No group:

1. **Progress Update (20-90%):**
   - Calculates percentage: `20 + (current / total) * 70`
   - Updates cache with:
     - Current receipt number
     - Current index / total receipts
     - Percentage complete
     - Status message

2. **Memory Management:**
   - Every 50 receipts, checks memory usage
   - If memory > 400MB, triggers garbage collection
   - Logs memory usage for monitoring

3. **Data Extraction:**
   - Maps Excel columns to database fields:
     - Column 0: Supplier name
     - Column 1: StockOut (quantity)
     - Column 2: Commodity (product name)
     - Column 3: SalesDate
     - Column 4: Means of Payment (CONSIGNMENT/CASH)
     - Column 5: ConfirmPrice (selling price)
     - Column 6: Shops (shop name)
     - Column 7: Receipt No
     - Column 8: Discount
     - Column 9: Total Amount
     - Column 10: Buying price
     - Column 11: Selling Price1

4. **Data Validation & Processing:**
   - Parses dates (handles Excel date format and text dates)
   - Finds or creates supplier
   - Finds or creates shop
   - Finds or creates product
   - Calculates totals for all items in receipt
   - Creates sale record with all items
   - Handles errors per receipt (continues on error)

5. **Error Handling:**
   - Each receipt processed in try-catch
   - Errors logged but don't stop import
   - Errors collected in array for display

---

### 5. **Progress Tracking System**

**How It Works:**

1. **Server-Side (Laravel Cache):**
   - Progress stored in cache with unique key
   - Updated at key stages:
     - File validation (1%)
     - File loading (3%)
     - Reading worksheet (5%)
     - Data analysis (7%)
     - Grouping rows (10-20%)
     - Processing receipts (20-90%)
     - Completion (100%)

2. **Client-Side (JavaScript Polling):**
   - After file upload completes, receives `progress_key` from server
   - Starts polling every 1 second
   - Polls endpoint: `GET /penjualan/import/progress?progress_key={key}`
   - Updates progress bar, percentage, and message
   - Stops polling when status is 'completed' or 'error'

**Progress Endpoint:** `PenjualanController@importProgress()`
- Retrieves progress from cache using key
- Returns JSON with current status
- Returns 404 if key not found or expired

---

### 6. **Completion & Results**

**On Success:**
1. Database transaction is committed
2. Progress updated to 'completed' (100%)
3. Success message prepared with:
   - Number of receipts imported
   - Number of errors (if any)
   - Number of warnings (if any)
4. Response sent to frontend:
   ```json
   {
       "success": true,
       "message": "Successfully imported X receipts...",
       "errors": [...],
       "warnings": [...],
       "imported_count": X,
       "progress_key": "..."
   }
   ```
5. Frontend shows green progress bar
6. Page reloads after 2 seconds to show results

**On Error:**
1. Database transaction is rolled back
2. Progress updated to 'error' status
3. Error logged with full stack trace
4. Error message returned to frontend
5. Frontend shows red progress bar with error message

---

### 7. **Error Handling**

**Types of Errors Handled:**

1. **Validation Errors:**
   - Invalid file type
   - File too large
   - Missing file
   - Returns 422 status with error details

2. **File Processing Errors:**
   - Corrupted Excel file
   - Unreadable worksheet
   - Returns 500 status with error message

3. **Data Processing Errors:**
   - Missing required fields
   - Invalid data formats
   - Database errors
   - Individual receipt errors (logged, import continues)

4. **System Errors:**
   - Memory exhaustion
   - Timeout errors
   - Database connection errors
   - All caught and logged

**Error Response Format:**
```json
{
    "success": false,
    "error": "Error message here",
    "progress_key": "..."
}
```

---

## Technical Details

### Memory Management
- Initial memory limit: 512MB
- Garbage collection triggered when memory > 400MB
- Memory usage logged every 50 receipts

### Performance Optimizations
- Database transactions for atomicity
- Batch processing of receipts
- Progress updates every 100 rows during grouping
- Progress updates per receipt during processing

### Security Features
- CSRF token validation
- Admin role requirement
- File type validation
- File size limits
- SQL injection prevention (Eloquent ORM)

### Data Structure

**Excel File Format:**
- Row 1: Header row (skipped)
- Columns: Supplier, StockOut, Commodity, SalesDate, Means of Payment, ConfirmPrice, Shops, Discount, Receipt No, Total Amount, Buying price, Selling Price1

**Database Structure:**
- Sales grouped by Receipt No
- Each sale contains multiple items
- All items linked to sale record

---

## User Experience Flow

1. **User visits import page** → Sees form with instructions
2. **User selects file** → File name displayed
3. **User clicks "Import Sales"** → Progress bar appears
4. **File uploads** → Progress shows 0-100% (upload progress)
5. **Server processes** → Progress shows processing status with percentage
6. **Import completes** → Green bar, success message, page reloads
7. **Results displayed** → Success/error/warning messages shown

---

## Troubleshooting

**Common Issues:**

1. **500 Server Error:**
   - Check Laravel logs: `storage/logs/laravel.log`
   - Verify file permissions
   - Check PHP memory and execution time limits
   - Verify database connection

2. **Import Fails Midway:**
   - Check memory usage in logs
   - Verify Excel file format
   - Check for corrupted data in Excel
   - Review error messages in session

3. **Progress Bar Not Showing:**
   - Check browser console for JavaScript errors
   - Verify jQuery is loaded
   - Check network tab for AJAX requests
   - Verify progress endpoint is accessible

4. **Slow Import:**
   - Large files take time (expected)
   - Check server resources
   - Review database indexes
   - Consider splitting large files

---

## File Locations

- **Controller:** `app/Http/Controllers/PenjualanController.php`
  - `importForm()` - Display import page
  - `import()` - Process import
  - `importProgress()` - Get progress status

- **View:** `resources/views/penjualan/import.blade.php`

- **Routes:** `routes/web.php`
  - `GET /penjualan/import` → `penjualan.import`
  - `POST /penjualan/import` → `penjualan.import.process`
  - `GET /penjualan/import/progress` → `penjualan.import.progress`

---

## Summary

The import system is a robust, user-friendly solution for bulk importing sales data. It handles large files efficiently, provides real-time feedback, and ensures data integrity through transactions and comprehensive error handling. The system is designed to be resilient, logging all errors for debugging while providing a smooth user experience.



