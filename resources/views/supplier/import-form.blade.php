<div class="modal fade" id="modal-import" tabindex="-1" role="dialog" aria-labelledby="modal-import">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('supplier.import') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Import Suppliers from Excel</h4>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <p><strong>Required columns in your Excel (first row = header):</strong></p>
                        <ul class="list-unstyled">
                            <li>• <strong>Supplier Name</strong> (or "Name")</li>
                            <li>• <strong>Means of Payment</strong> (or "Mode of Payment", "MOP")</li>
                        </ul>
                        <p><strong>Optional columns:</strong> Address, Telephone (or Phone)</p>
                        <p>Supported formats: .xlsx, .xls (max 10 MB)</p>
                    </div>
                    <div class="form-group">
                        <label for="import-file">Choose file</label>
                        <input type="file" name="file" id="import-file" class="form-control" accept=".xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default btn-flat" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-flat"><i class="fa fa-upload"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>






