# Import System Refactoring Guide

## Step 1: Install Maatwebsite Excel

Run this command in your terminal:
```bash
composer require maatwebsite/excel:^3.1
```

## Step 2: Publish Excel Config (Optional)
```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

## Step 3: Create Queue Tables

Run these commands:
```bash
php artisan queue:table
php artisan migrate
```

## Step 4: Update .env File

Add or update these lines in your `.env` file:
```env
QUEUE_CONNECTION=database
```

For production, you might want to use Redis:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Step 5: Run Queue Worker

You need to run a queue worker to process jobs. Add this to your server startup or run it manually:

```bash
php artisan queue:work --queue=imports --tries=3 --timeout=600
```

Or for development/testing:
```bash
php artisan queue:listen --queue=imports
```

## Step 6: Files Created

The following files have been created:
1. `app/Imports/SalesImport.php` - Import class with chunking
2. `app/Jobs/ProcessSalesImportJob.php` - Job for processing grouped receipts
3. Updated `app/Http/Controllers/PenjualanController.php` - New import method

## Step 7: Update Controller Import Method

The controller method has been updated to:
- Use Maatwebsite Excel with chunking (500 rows per chunk)
- Dispatch job immediately after file reading
- Return JSON response immediately
- Process in background queue

## How It Works Now

1. **File Upload**: User uploads Excel file
2. **Chunk Reading**: File is read in chunks of 500 rows (doesn't load entire file)
3. **Grouping**: Rows are grouped by Receipt No and stored in cache
4. **Job Dispatch**: Job is dispatched to queue for processing
5. **Immediate Response**: JSON response returned to user
6. **Background Processing**: Job processes receipts in batches of 500
7. **Progress Tracking**: Progress updated via cache, frontend polls for updates

## Benefits

✅ No memory exhaustion - chunks of 500 rows only
✅ No timeout - processing happens in background
✅ Scalable - handles 10k+ rows easily
✅ Non-blocking - user gets immediate response
✅ Batch inserts - optimized database operations
✅ Production safe - proper error handling and transactions

## Testing

1. Install package: `composer require maatwebsite/excel:^3.1`
2. Run migrations: `php artisan migrate`
3. Start queue worker: `php artisan queue:work --queue=imports`
4. Test import with large file
5. Monitor progress via the progress endpoint

## Troubleshooting

**Queue not processing?**
- Make sure queue worker is running
- Check `QUEUE_CONNECTION` in .env
- Check `jobs` table exists

**Memory still high?**
- Reduce chunk size in SalesImport.php (currently 500)
- Reduce batch size in ProcessSalesImportJob.php (currently 500)

**Jobs failing?**
- Check `failed_jobs` table
- Check Laravel logs
- Verify database connection



