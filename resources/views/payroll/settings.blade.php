@extends('layouts.master')

@section('title')
    Payroll Tax Settings
@endsection

@section('breadcrumb')
    @parent
    <li class="active">Payroll Tax Settings</li>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-cog"></i> Configure Tax Rates</h3>
            </div>
            <form id="payroll-settings-form" action="{{ route('payroll.settings.update') }}" method="post">
                @csrf
                <div class="box-body">
                    <div class="alert alert-success alert-dismissible" id="success-alert" style="display: none;">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <i class="icon fa fa-check"></i> Settings saved successfully
                    </div>

                    <!-- PAYE Settings -->
                    <h4><i class="fa fa-file-text"></i> PAYE Tax Bands</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Band 1 - Minimum (Annual)</label>
                                <input type="number" name="paye_band1_min" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Band 1 - Maximum (Annual)</label>
                                <input type="number" name="paye_band1_max" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Band 1 - Rate (%)</label>
                                <input type="number" name="paye_band1_rate" class="form-control" step="0.01" min="0" max="100" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Band 2 - Minimum (Annual)</label>
                                <input type="number" name="paye_band2_min" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Band 2 - Maximum (Annual)</label>
                                <input type="number" name="paye_band2_max" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Band 2 - Rate (%)</label>
                                <input type="number" name="paye_band2_rate" class="form-control" step="0.01" min="0" max="100" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Band 3 - Minimum (Annual)</label>
                                <input type="number" name="paye_band3_min" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Band 3 - Rate (%)</label>
                                <input type="number" name="paye_band3_rate" class="form-control" step="0.01" min="0" max="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Personal Relief (Annual)</label>
                                <input type="number" name="personal_relief" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- NSSF Settings -->
                    <h4><i class="fa fa-bank"></i> NSSF Settings</h4>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tier 1 Limit (KES)</label>
                                <input type="number" name="nssf_tier1_limit" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tier 2 Limit (KES)</label>
                                <input type="number" name="nssf_tier2_limit" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>NSSF Rate (%)</label>
                                <input type="number" name="nssf_rate" class="form-control" step="0.01" min="0" max="100" required>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- SHA Settings -->
                    <h4><i class="fa fa-heart"></i> SHA (Social Health Authority) Contribution Bands</h4>
                    <div class="form-group">
                        <button type="button" class="btn btn-sm btn-success" id="add-band-btn">
                            <i class="fa fa-plus"></i> Add Band
                        </button>
                    </div>
                    <div id="nhif-bands-container">
                        <!-- SHA bands will be loaded here -->
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Settings
                    </button>
                    <a href="{{ route('payroll.dashboard') }}" class="btn btn-default">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Make functions globally accessible
    window.addNHIFBand = function(min = '', max = '', amount = '', index = null) {
        const bandIndex = index !== null ? index : $('.nhif-band-row').length;
        const html = `
            <div class="row nhif-band-row" data-index="${bandIndex}">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Min Salary</label>
                        <input type="number" class="form-control nhif-min" step="0.01" min="0" value="${min}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Max Salary</label>
                        <input type="number" class="form-control nhif-max" step="0.01" min="0" value="${max}" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Contribution (KES)</label>
                        <input type="number" class="form-control nhif-amount" step="0.01" min="0" value="${amount}" required>
                    </div>
                </div>
                <div class="col-md-1">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="btn btn-danger btn-block remove-band-btn">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#nhif-bands-container').append(html);
    };

    window.removeNHIFBand = function(button) {
        $(button).closest('.nhif-band-row').remove();
    };

    function collectNHIFBands() {
        const bands = [];
        $('.nhif-band-row').each(function() {
            bands.push({
                min: parseFloat($(this).find('.nhif-min').val()) || 0,
                max: parseFloat($(this).find('.nhif-max').val()) || 999999999,
                amount: parseFloat($(this).find('.nhif-amount').val()) || 0,
            });
        });
        return bands;
    }

    $(function () {
        loadSettings();

        // Add band button click handler
        $(document).on('click', '#add-band-btn', function() {
            addNHIFBand();
        });

        // Remove band button click handler
        $(document).on('click', '.remove-band-btn', function() {
            removeNHIFBand(this);
        });

        $('#payroll-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                paye_band1_min: $('[name=paye_band1_min]').val(),
                paye_band1_max: $('[name=paye_band1_max]').val(),
                paye_band1_rate: $('[name=paye_band1_rate]').val(),
                paye_band2_min: $('[name=paye_band2_min]').val(),
                paye_band2_max: $('[name=paye_band2_max]').val(),
                paye_band2_rate: $('[name=paye_band2_rate]').val(),
                paye_band3_min: $('[name=paye_band3_min]').val(),
                paye_band3_rate: $('[name=paye_band3_rate]').val(),
                personal_relief: $('[name=personal_relief]').val(),
                nssf_tier1_limit: $('[name=nssf_tier1_limit]').val(),
                nssf_tier2_limit: $('[name=nssf_tier2_limit]').val(),
                nssf_rate: $('[name=nssf_rate]').val(),
                nhif_bands: collectNHIFBands(),
            };

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('#success-alert').fadeIn();
                    setTimeout(() => {
                        $('#success-alert').fadeOut();
                    }, 3000);
                },
                error: function(xhr) {
                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                        alert('Validation errors: ' + JSON.stringify(xhr.responseJSON.errors));
                    } else {
                        alert('Error saving settings');
                    }
                }
            });
        });
    });

    function loadSettings() {
        const settings = @json($settings);
        
        $('[name=paye_band1_min]').val(settings.paye_band1_min);
        $('[name=paye_band1_max]').val(settings.paye_band1_max);
        $('[name=paye_band1_rate]').val(settings.paye_band1_rate);
        $('[name=paye_band2_min]').val(settings.paye_band2_min);
        $('[name=paye_band2_max]').val(settings.paye_band2_max);
        $('[name=paye_band2_rate]').val(settings.paye_band2_rate);
        $('[name=paye_band3_min]').val(settings.paye_band3_min);
        $('[name=paye_band3_rate]').val(settings.paye_band3_rate);
        $('[name=personal_relief]').val(settings.personal_relief);
        $('[name=nssf_tier1_limit]').val(settings.nssf_tier1_limit);
        $('[name=nssf_tier2_limit]').val(settings.nssf_tier2_limit);
        $('[name=nssf_rate]').val(settings.nssf_rate);

        // Load NHIF bands
        const nhifBands = settings.nhif_bands || [];
        nhifBands.forEach((band, index) => {
            addNHIFBand(band.min, band.max, band.amount, index);
        });
    }

</script>
@endpush

