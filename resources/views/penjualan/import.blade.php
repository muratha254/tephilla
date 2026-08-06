@extends('layouts.master')

@section('title')
    Import Sales
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Import Sales</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">Import Sales from Excel</h3>
            </div>
            <div class="box-body">
                @if(!empty($sessionSuccess))
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h4><i class="icon fa fa-check"></i> Success!</h4>
                        <p>{{ $sessionSuccess }}</p>
                    </div>
                @endif

                @if(!empty($sessionErrors) && is_array($sessionErrors) && count($sessionErrors) > 0)
                    <div class="alert alert-warning alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h4><i class="icon fa fa-warning"></i> Import Errors ({{ count($sessionErrors) }})</h4>
                        <ul style="max-height: 300px; overflow-y: auto;">
                            @foreach($sessionErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(!empty($sessionWarnings) && is_array($sessionWarnings) && count($sessionWarnings) > 0)
                    <div class="alert alert-info alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h4><i class="icon fa fa-info-circle"></i> Import Warnings ({{ count($sessionWarnings) }})</h4>
                        <ul style="max-height: 200px; overflow-y: auto;">
                            @foreach($sessionWarnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if(isset($errors) && $errors->any())
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h4><i class="icon fa fa-ban"></i> Error!</h4>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="alert alert-info">
                    <h4><i class="icon fa fa-info-circle"></i> Instructions</h4>
                    <p>Upload an Excel file (.xlsx or .xls) with the following columns:</p>
                    <ul>
                        <li><strong>Supplier name</strong> - Name of the supplier</li>
                        <li><strong>StockOut</strong> - Quantity sold</li>
                        <li><strong>Commodity</strong> - Product name</li>
                        <li><strong>SalesDate</strong> - Date of sale</li>
                        <li><strong>Means of Payment</strong> - CONSIGNMENT or CASH</li>
                        <li><strong>ConfirmPrice</strong> - Selling price per unit</li>
                        <li><strong>Shops</strong> - Shop name</li>
                        <li><strong>Discount</strong> - Discount amount</li>
                        <li><strong>Receipt No</strong> - Receipt number (rows with same receipt number will be grouped as one sale)</li>
                        <li><strong>Total Amount</strong> - Total amount for the line item</li>
                        <li><strong>Buying price</strong> - Purchase price per unit</li>
                        <li><strong>Selling Price1</strong> - Selling price per unit</li>
                    </ul>
                    <p><strong>Note:</strong> Sales are grouped by <strong>Receipt No + Sales Date</strong>. The same receipt number in different years (e.g. A44576 in 2013 and 2024) creates <strong>separate</strong> sales.</p>
                    <p><strong>Maximum file size: 200MB</strong> (large historical exports).</p>
                    <div class="alert alert-warning" style="margin-top: 12px;">
                        <strong><i class="fa fa-exclamation-triangle"></i> Required for import to run:</strong> After uploading, the import runs in the background. You must have the queue worker running in a separate terminal, otherwise nothing will be imported. Run: <code>php -d max_execution_time=0 -d memory_limit=2048M artisan queue:work --queue=imports --timeout=7200</code> (standard full imports may take 30–90 minutes; <strong>reimport</strong> skips receipts already in the database and usually finishes in a few minutes when little is missing). If you see an Imagick version warning, it is harmless.
                    </div>
                    <div class="alert alert-default" style="margin-top: 12px; background: #f9f9f9;">
                        <strong><i class="fa fa-server"></i> XAMPP upload limit (200MB files):</strong> In <code>C:\xampp\php\php.ini</code>, set at least:
                        <code>upload_max_filesize=250M</code>, <code>post_max_size=250M</code>, <code>memory_limit=1024M</code>, then restart Apache.
                    </div>
                </div>

                <!-- Progress Bar (Hidden initially) -->
                <div id="progress-container" style="display: none; margin: 20px 0;">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-refresh fa-spin"></i> Importing Sales...</h3>
                        </div>
                        <div class="box-body">
                            <div class="progress progress-lg active" style="height: 35px;">
                                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-info" 
                                     role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" 
                                     style="width: 0%; line-height: 35px; font-size: 16px;">
                                    <span id="progress-text" style="font-weight: bold;">0%</span>
                                </div>
                            </div>
                            <p id="progress-message" class="text-center" style="margin-top: 15px; font-size: 15px; font-weight: bold;">Preparing import...</p>
                            <p id="progress-details" class="text-center text-muted" style="margin-top: 8px; font-size: 13px;"></p>
                        </div>
                    </div>
                </div>

                <form id="import-form" action="{{ route('penjualan.import.process') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="import_mode" id="import_mode" value="standard">

                    <div class="form-group">
                        <label for="file">Select Excel File</label>
                        <input type="file" name="file" id="file" class="form-control" accept=".xlsx,.xls" required>
                        <small class="help-block">Maximum file size: <strong>200MB</strong>. If upload fails, increase <code>upload_max_filesize</code> and <code>post_max_size</code> in XAMPP PHP settings (see note below).</small>
                    </div>

                    <div class="box box-warning" style="margin-top: 20px;">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-history"></i> Reimport missing historical sales</h3>
                        </div>
                        <div class="box-body">
                            <p>Use this when a previous import skipped receipts because the <strong>same receipt number was used in another year</strong> (e.g. A44576 in 2013 blocked A44576 in 2024).</p>
                            <p>Reimport reads your Excel again and imports sales that are still missing, matching <strong>receipt number + sales date</strong>. Existing lines for the same receipt and date are not duplicated.</p>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="reimport_year_from">From year</label>
                                        <input type="number" name="reimport_year_from" id="reimport_year_from" class="form-control" value="2007" min="1990" max="2100">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="reimport_year_to">To year</label>
                                        <input type="number" name="reimport_year_to" id="reimport_year_to" class="form-control" value="{{ date('Y') }}" min="1990" max="2100">
                                    </div>
                                </div>
                            </div>
                            <p class="text-muted" style="margin-bottom: 12px;">Only rows with a sales date in this year range are processed. Upload the same Excel file you used before (or a combined 2007–{{ date('Y') }} export). If everything is already imported, reimport completes quickly with <strong>0 imported</strong> and a message that nothing was missing.</p>
                            <button type="button" id="reimport-btn" class="btn btn-warning btn-lg">
                                <i class="fa fa-refresh"></i> Reimport missing sales
                            </button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <button type="button" id="submit-btn" class="btn btn-primary btn-lg">
                            <i class="fa fa-upload"></i> Import Sales (standard)
                        </button>
                        <a href="{{ route('penjualan.index') }}" class="btn btn-default btn-lg">
                            <i class="fa fa-arrow-left"></i> Back to Sales List
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    'use strict';
    
    jQuery(document).ready(function($) {
        var progressInterval = null;
        var currentProgressKey = null;
        var $form = $('#import-form');
        var $progressContainer = $('#progress-container');
        var $progressBar = $('#progress-bar');
        var $progressText = $('#progress-text');
        var $progressMessage = $('#progress-message');
        var $progressDetails = $('#progress-details');
        var $submitBtn = $('#submit-btn');
        var $reimportBtn = $('#reimport-btn');
        var $fileInput = $('#file');
        var $importMode = $('#import_mode');

        function setImportMode(mode) {
            $importMode.val(mode === 'reimport' ? 'reimport' : 'standard');
        }

        $reimportBtn.on('click', function(e) {
            e.preventDefault();
            if (!$fileInput[0].files.length) {
                alert('Please select the Excel file to reimport.');
                return;
            }
            var yFrom = parseInt($('#reimport_year_from').val(), 10) || 2007;
            var yTo = parseInt($('#reimport_year_to').val(), 10) || new Date().getFullYear();
            if (!confirm('Reimport missing sales for years ' + yFrom + ' to ' + yTo + '?\n\nReceipts are matched by receipt number AND sales date. Lines already in the system for the same date will be skipped.')) {
                return;
            }
            setImportMode('reimport');
            $form.trigger('submit');
        });

        $submitBtn.on('click', function(e) {
            e.preventDefault();
            setImportMode('standard');
            $form.trigger('submit');
        });
        
        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!$fileInput[0].files.length) {
                alert('Please select a file to import.');
                return false;
            }
            
            // Show progress bar
            $progressContainer.show();
            var isReimport = $importMode.val() === 'reimport';
            $submitBtn.prop('disabled', true);
            $reimportBtn.prop('disabled', true);
            if (isReimport) {
                $reimportBtn.html('<i class="fa fa-spinner fa-spin"></i> Uploading reimport...');
            } else {
                $submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
            }
            $progressBar.css('width', '0%').attr('aria-valuenow', 0);
            $progressText.text('0%');
            $progressMessage.text('Uploading file...');
            $progressDetails.text('');
            
            // Create FormData
            var formData = new FormData($form[0]);
            
            // Create XMLHttpRequest
            var xhr = new XMLHttpRequest();
            
            var uploadDone = false;

            // Upload progress
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    $progressBar.css('width', percent + '%').attr('aria-valuenow', percent);
                    $progressText.text(percent + '%');
                    if (percent >= 100 && !uploadDone) {
                        uploadDone = true;
                        $progressMessage.text('Upload complete. Starting import on server...');
                    } else if (!uploadDone) {
                        $progressMessage.text('Uploading file... ' + percent + '%');
                    }
                }
            });

            xhr.addEventListener('loadend', function() {
                resetSubmitButtons();
            });
            
            // Response handler
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            currentProgressKey = response.progress_key;
                            $progressBar.css('width', '5%').attr('aria-valuenow', 5);
                            $progressText.text('5%');
                            $progressMessage.text(response.message || 'Import queued...');
                            if (response.needs_worker) {
                                $progressDetails.html('<strong style="color:#d9534f;">Start the import worker in a terminal:</strong><br><code style="background:#f5f5f5;padding:4px 8px;">php artisan queue:work --queue=imports --timeout=7200</code>');
                            }
                            if (currentProgressKey) {
                                startProgressPolling();
                            } else {
                                showError('No progress key returned');
                            }
                        } else {
                            showError(response.error || response.message || 'Import failed');
                        }
                    } catch (e) {
                        if (xhr.responseText.includes('<!DOCTYPE') || xhr.responseText.includes('<html')) {
                            showError('Server returned HTML error page. Please check the server logs.');
                        } else {
                            showError('Failed to parse server response: ' + xhr.responseText.substring(0, 200));
                        }
                    }
                } else {
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        showError(errorResponse.error || errorResponse.message || 'Server error (Status: ' + xhr.status + ')');
                    } catch (e) {
                        showError('Server error occurred. Status: ' + xhr.status);
                    }
                }
            });
            
            xhr.addEventListener('error', function() {
                showError('Network error occurred. Please check your connection.');
            });

            xhr.addEventListener('timeout', function() {
                showError('Upload timed out. For very large files, try again or check server upload limits.');
            });

            xhr.timeout = 600000; // 10 minutes for upload only (processing runs in background)
            
            // Send request
            xhr.open('POST', $form.attr('action'));
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
            
            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }
            
            xhr.send(formData);
            
            return false;
        });
        
        function resetSubmitButtons() {
            $submitBtn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import Sales (standard)');
            $reimportBtn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Reimport missing sales');
        }

        function startProgressPolling() {
            if (!currentProgressKey) {
                return;
            }

            var pollStuckCount = 0;
            
            progressInterval = setInterval(function() {
                $.ajax({
                    url: '{{ route('penjualan.import.progress') }}',
                    method: 'GET',
                    data: { progress_key: currentProgressKey },
                    dataType: 'json',
                    success: function(data) {
                        if (data.status === 'processing' || data.status === 'completed') {
                            var percent = data.percentage || 0;
                            $progressBar.css('width', percent + '%').attr('aria-valuenow', percent);
                            $progressText.text(percent.toFixed(1) + '%');
                            $progressMessage.text(data.message || 'Processing...');
                            
                            var msg = (data.message || '').toLowerCase();
                            var activelyProcessing = (data.processing_import_jobs && data.processing_import_jobs > 0)
                                || msg.indexOf('reading excel') !== -1
                                || msg.indexOf('grouped') !== -1
                                || msg.indexOf('importing sales') !== -1
                                || msg.indexOf('processing receipt') !== -1;
                            var needsWorker = data.needs_worker === true;
                            var isWaitingForWorker = needsWorker && !activelyProcessing;
                            if (data.phase === 'reading') {
                                var rows = data.rows_read || data.current || 0;
                                var groups = data.receipt_groups || 0;
                                $progressDetails.text(rows + ' Excel rows scanned'
                                    + (groups > 0 ? ' · ' + groups + ' receipt groups grouped' : '')
                                    + ' — still reading file, please wait');
                            } else if (data.phase === 'prefiltering') {
                                $progressDetails.text('Matching Excel receipts to sales already in the database — not re-reading the file.');
                            } else if (data.current && data.total) {
                                $progressDetails.text(data.current + ' / ' + data.total + ' receipt groups processed');
                            } else {
                                $progressDetails.text('');
                            }
                            if (isWaitingForWorker) {
                                pollStuckCount++;
                                var workerHtml = '<div class="alert alert-danger" style="margin-top:10px;text-align:left;"><strong>Import worker not running or busy.</strong> Open Command Prompt in the project folder and run:<br><code style="display:block;margin-top:8px;padding:8px;background:#f5f5f5;">php artisan queue:work --queue=imports --timeout=7200</code>';
                                if (data.pending_import_jobs) {
                                    workerHtml += '<br><small>' + data.pending_import_jobs + ' job(s) waiting in queue.</small>';
                                }
                                workerHtml += '</div>';
                                if ($('#import-worker-hint').length === 0) {
                                    $progressDetails.after('<div id="import-worker-hint">' + workerHtml + '</div>');
                                }
                            } else {
                                pollStuckCount = 0;
                                $('#import-worker-hint').remove();
                            }
                            
                            if (data.status === 'completed') {
                                clearInterval(progressInterval);
                                $progressBar.removeClass('progress-bar-info').addClass(data.errors && data.errors.length ? 'progress-bar-warning' : 'progress-bar-success');
                                $progressMessage.text(data.message || 'Import completed.');
                                if (data.phase === 'reading') {
                                    $progressDetails.text((data.rows_read || data.current || 0) + ' Excel rows scanned');
                                } else if (data.current && data.total) {
                                    $progressDetails.text(data.current + ' / ' + data.total + ' receipt groups imported');
                                }
                                if (data.skipped_excel_rows && data.skipped_excel_rows > 0) {
                                    var skipHtml = '<div class="alert alert-danger" style="margin-top:12px;text-align:left;"><strong>' + data.skipped_excel_rows + ' Excel row(s) had no readable receipt number</strong> and were not imported. Check receipt column formatting (no spaces in shop names mistaken for receipts).</div>';
                                    $progressDetails.after(skipHtml);
                                }
                                if (data.warnings && data.warnings.length > 0) {
                                    var warnHtml = '<div class="alert alert-info" style="margin-top:12px;text-align:left;"><strong>' + data.warnings.length + ' warning(s):</strong><ul style="max-height:200px;overflow-y:auto;margin:8px 0 0 0;">';
                                    data.warnings.slice(0, 30).forEach(function(w) { warnHtml += '<li>' + (w || '').replace(/</g, '&lt;') + '</li>'; });
                                    if (data.warnings.length > 30) { warnHtml += '<li>... and ' + (data.warnings.length - 30) + ' more.</li>'; }
                                    warnHtml += '</ul></div>';
                                    $progressDetails.after(warnHtml);
                                }
                                if (data.errors && data.errors.length > 0) {
                                    var errHtml = '<div class="alert alert-warning" style="margin-top:12px;text-align:left;"><strong>' + data.errors.length + ' error(s):</strong><ul style="max-height:200px;overflow-y:auto;margin:8px 0 0 0;">';
                                    data.errors.slice(0, 20).forEach(function(e) { errHtml += '<li>' + (e || '').replace(/</g, '&lt;') + '</li>'; });
                                    if (data.errors.length > 20) { errHtml += '<li>... and ' + (data.errors.length - 20) + ' more. Check storage/app/import_reports/ for full list.</li>'; }
                                    errHtml += '</ul></div>';
                                    $progressDetails.after(errHtml);
                                }
                                setTimeout(function() {
                                    window.location.reload();
                                }, data.errors && data.errors.length ? 8000 : 2000);
                            }
                        } else if (data.status === 'error') {
                            clearInterval(progressInterval);
                            showError(data.message || 'Import failed');
                        }
                    },
                    error: function() {
                        // Continue polling on error
                    }
                });
            }, 1000);
        }
        
        function showError(message) {
            if (progressInterval) {
                clearInterval(progressInterval);
            }
            $progressBar.removeClass('progress-bar-info').addClass('progress-bar-danger');
            $progressMessage.text('Error: ' + message);
            resetSubmitButtons();
            alert('Error: ' + message);
        }
    });
})();
</script>
@endpush
