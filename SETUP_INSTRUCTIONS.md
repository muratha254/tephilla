# Import System Refactoring - Setup Instructions

## ✅ Files Created/Updated

1. **`app/Imports/SalesImport.php`** - Import class with chunking (500 rows per chunk)
2. **`app/Jobs/ProcessSalesImportJob.php`** - Background job for processing receipts (500 per batch)
3. **`app/Http/Controllers/PenjualanController.php`** - Updated import method
4. **`composer.json`** - Added maatwebsite/excel package

## 📋 Step-by-Step Setup

### Step 1: Install Maatwebsite Excel Package

```bash
composer require maatwebsite/excel:^3.1
```

### Step 2: Publish Excel Config (Optional)

```bash
php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config
```

### Step 3: Create Queue Tables

```bash
php artisan queue:table
php artisan migrate
```

This creates the `jobs` and `failed_jobs` tables needed for queue processing.

### Step 4: Update .env File

Add or update these lines in your `.env` file:

```env
QUEUE_CONNECTION=database
```

**For Production (Recommended):**
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Step 5: Start Queue Worker

**For Development/Testing:**
```bash
php artisan queue:work --queue=imports --tries=3 --timeout=600
```

**For Production (as a service):**
- Windows: Create a scheduled task or run as a service
- Linux: Use Supervisor or systemd

**Supervisor Configuration Example:**
```ini
[program:laravel-queue-imports]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=imports --tries=3 --timeout=600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/queue-worker.log
```

## 🔧 Configuration Summary

### Chunk & Batch Sizes

- **Chunk Size**: 500 rows (in `SalesImport.php`)
- **Batch Size**: 500 receipts (in `ProcessSalesImportJob.php`)
- **Memory Limit**: 1024M
- **Time Limit**: 600 seconds (10 minutes)

### How It Works

1. **File Upload** → User uploads Excel file
2. **Chunk Reading** → File read in chunks of 500 rows (doesn't load entire file)
3. **Grouping** → Rows grouped by Receipt No and stored in cache
4. **Job Dispatch** → Job dispatched to queue immediately
5. **Immediate Response** → JSON response returned to user
6. **Background Processing** → Job processes receipts in batches of 500
7. **Progress Tracking** → Frontend polls progress endpoint

## 🧪 Testing

1. **Install package:**
   ```bash
   composer require maatwebsite/excel:^3.1
   ```

2. **Run migrations:**
   ```bash
   php artisan migrate
   ```

3. **Start queue worker** (in a separate terminal):
   ```bash
   php artisan queue:work --queue=imports
   ```

4. **Test import:**
   - Go to `/penjualan/import`
   - Upload a large Excel file (10k+ rows)
   - Watch progress bar update
   - Check queue worker terminal for processing logs

## ⚠️ Important Notes

1. **Queue Worker Must Be Running**: The import will fail if the queue worker is not running. The job will be queued but not processed.

2. **Cache Storage**: Grouped data is stored in cache. Make sure your cache driver has enough memory/storage.

3. **Database Transactions**: Each batch of 500 receipts is processed in a transaction. If one batch fails, only that batch is rolled back.

4. **Progress Tracking**: Progress is stored in cache with 10-minute expiry. Large imports should complete within this time.

## 🐛 Troubleshooting

### Queue Not Processing

**Check:**
- Queue worker is running: `php artisan queue:work --queue=imports`
- `QUEUE_CONNECTION` in `.env` is set correctly
- `jobs` table exists: `php artisan migrate`

### Jobs Failing

**Check:**
- `failed_jobs` table for error details
- Laravel logs: `storage/logs/laravel.log`
- Verify database connection
- Check memory limits

### Memory Still High

**Solutions:**
- Reduce chunk size in `SalesImport.php` (currently 500)
- Reduce batch size in `ProcessSalesImportJob.php` (currently 500)
- Increase PHP memory limit further if needed

### Import Not Grouping Correctly

**Check:**
- Excel file format matches expected structure
- Receipt No is in column 7 (0-indexed)
- Receipt No extraction logic in `SalesImport::extractReceiptNo()`

## 📊 Performance Expectations

- **10,000 rows**: ~2-5 minutes
- **50,000 rows**: ~10-20 minutes
- **100,000+ rows**: ~20-40 minutes

*Times vary based on server resources and database performance*

## ✅ Verification Checklist

- [ ] Maatwebsite Excel installed
- [ ] Queue tables created (`jobs`, `failed_jobs`)
- [ ] `.env` updated with `QUEUE_CONNECTION`
- [ ] Queue worker running
- [ ] Test import with small file (100 rows)
- [ ] Test import with large file (10k+ rows)
- [ ] Progress bar updating correctly
- [ ] No 500 errors
- [ ] No memory crashes
- [ ] No timeouts

## 🎯 Success Criteria

✅ No 500 errors  
✅ No memory crashes  
✅ No timeouts  
✅ Works for 10k+ rows  
✅ Progress bar shows real-time updates  
✅ Receipts grouped correctly  
✅ All data imported successfully  



