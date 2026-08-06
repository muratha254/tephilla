@extends('layouts.master')

@section('title')
    Settings
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Settings</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <form action="{{ route('setting.update') }}" method="post" class="form-setting" enctype="multipart/form-data">
                @csrf
                <div class="box-body">
                    <div class="alert alert-success alert-dismissible" style="display: none;">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <i class="icon fa fa-check"></i> Changes saved successfully
                    </div>
                    <div class="form-group row">
                        <label for="nama_perusahaan" class="col-lg-2 control-label">Company name</label>
                        <div class="col-lg-6">
                            <input type="text" name="nama_perusahaan" class="form-control" id="nama_perusahaan" required autofocus>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="telepon" class="col-lg-2 control-label">Telephone</label>
                        <div class="col-lg-6">
                            <input type="text" name="telepon" class="form-control" id="telepon" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="alamat" class="col-lg-2 control-label">Address</label>
                        <div class="col-lg-6">
                            <textarea name="alamat" class="form-control" id="alamat" rows="3" required></textarea>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="path_logo" class="col-lg-2 control-label">Logo</label>
                        <div class="col-lg-4">
                            <input type="file" name="path_logo" class="form-control" id="path_logo"
                                onchange="preview('.tampil-logo', this.files[0])">
                            <span class="help-block with-errors"></span>
                            <br>
                            <div class="tampil-logo"></div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="path_kartu_member" class="col-lg-2 control-label">Membership Card</label>
                        <div class="col-lg-4">
                            <input type="file" name="path_kartu_member" class="form-control" id="path_kartu_member"
                                onchange="preview('.tampil-kartu-member', this.files[0], 300)">
                            <span class="help-block with-errors"></span>
                            <br>
                            <div class="tampil-kartu-member"></div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="diskon" class="col-lg-2 control-label">Discount</label>
                        <div class="col-lg-2">
                            <input type="number" name="diskon" class="form-control" id="diskon" required>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="driver_commission_rate" class="col-lg-2 control-label">Driver Commission Rate (%)</label>
                        <div class="col-lg-2">
                            <input type="number" name="driver_commission_rate" class="form-control" id="driver_commission_rate" step="0.01" min="0" max="100" value="0">
                            <span class="help-block with-errors">Percentage of subtotal (before VAT). Example: 10 for 10%</span>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="tipe_nota" class="col-lg-2 control-label">Note Type</label>
                        <div class="col-lg-2">
                            <select name="tipe_nota" class="form-control" id="tipe_nota" required>
                                <option value="1">Small Invoice</option>
                                <option value="2">PDF Invoice</option>
                            </select>
                            <span class="help-block with-errors"></span>
                        </div>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <button type="button" class="btn btn-sm btn-flat btn-primary" id="save-settings-btn"><i class="fa fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(function () {
        showData();

        // Handle form submission via button click instead of form submit
        $('#save-settings-btn').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Save button clicked');
            
            // Validate required fields manually
            let isValid = true;
            $('.form-setting [required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });
            
            if (!isValid) {
                alert('Please fill in all required fields');
                return false;
            }
            
            // Disable button to prevent double submission
            $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
            
            // Log form data before sending
            let formData = new FormData($('.form-setting')[0]);
            console.log('Form data being sent:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }
            
            $.ajax({
                url: $('.form-setting').attr('action'),
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
                }
            })
            .done(response => {
                console.log('Response received:', response);
                console.log('Saved driver_commission_rate:', response.driver_commission_rate);
                
                // Update the form field immediately with the saved value
                if (response.driver_commission_rate !== undefined) {
                    $('[name=driver_commission_rate]').val(response.driver_commission_rate);
                }
                
                // Reload all data from server
                showData();
                
                $('.alert').fadeIn();
                $('.alert').removeClass('alert-danger').addClass('alert-success');
                let message = 'Changes saved successfully';
                if (typeof response === 'object' && response.message) {
                    message = response.message;
                    if (response.driver_commission_rate !== undefined) {
                        message += ' (Commission Rate: ' + response.driver_commission_rate + '%)';
                    }
                } else if (typeof response === 'string') {
                    message = response;
                }
                $('.alert').html('<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-check"></i> ' + message);

                setTimeout(() => {
                    $('.alert').fadeOut();
                }, 5000);
            })
            .fail(xhr => {
                console.error('AJAX Error:', xhr);
                console.error('Status:', xhr.status);
                console.error('Response:', xhr.responseText);
                let errorMessage = 'Unable to save data';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    errorMessage = xhr.responseText;
                }
                $('.alert').fadeIn();
                $('.alert').removeClass('alert-success').addClass('alert-danger');
                $('.alert').html('<button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-warning"></i> ' + errorMessage);
                
                setTimeout(() => {
                    $('.alert').fadeOut();
                }, 5000);
            })
            .always(() => {
                // Re-enable button
                $('#save-settings-btn').prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
            });
            
            return false;
        });
        
        // Also prevent form submission if someone presses Enter
        $('.form-setting').on('submit', function (e) {
            e.preventDefault();
            $('#save-settings-btn').click();
            return false;
        });
    });

    function showData() {
        $.get('{{ route('setting.show') }}')
            .done(response => {
                $('[name=nama_perusahaan]').val(response.nama_perusahaan);
                $('[name=telepon]').val(response.telepon);
                $('[name=alamat]').val(response.alamat);
                $('[name=diskon]').val(response.diskon);
                $('[name=tipe_nota]').val(response.tipe_nota);
                
                // Always set driver_commission_rate, default to 0 if not set
                let commissionRate = response.driver_commission_rate !== undefined ? response.driver_commission_rate : 0;
                $('[name=driver_commission_rate]').val(commissionRate);
                console.log('Loaded driver_commission_rate:', commissionRate);
                $('title').text(response.nama_perusahaan + ' | Settings');
                
                let words = response.nama_perusahaan.split(' ');
                let word  = '';
                words.forEach(w => {
                    word += w.charAt(0);
                });
                $('.logo-mini').text(word);
                $('.logo-lg').text(response.nama_perusahaan);

                $('.tampil-logo').html(`<img src="{{ url('/') }}${response.path_logo}" width="200">`);
                $('.tampil-kartu-member').html(`<img src="{{ url('/') }}${response.path_kartu_member}" width="300">`);
                $('[rel=icon]').attr('href', `{{ url('/') }}/${response.path_logo}`);
            })
            .fail(errors => {
                alert('Unable to display data');
                return;
            });
    }
</script>
@endpush