@php
    $exportId = $exportId ?? ($tableId . '-export');
    $lengthId = $lengthId ?? ($tableId . '-length');
    $searchId = $searchId ?? ($tableId . '-search');
    $colvisId = $colvisId ?? ($tableId . '-colvis');
@endphp
<div class="sx-dt-row">
    <div id="{{ $lengthId }}"></div>
    <div class="sx-export-btns" id="{{ $exportId }}">
        <button type="button" class="btn" data-export="copy">Copy</button>
        <button type="button" class="btn" data-export="excel">Excel</button>
        <button type="button" class="btn" data-export="pdf">PDF</button>
        <button type="button" class="btn" data-export="print">Print</button>
        <button type="button" class="btn" data-export="csv">CSV</button>
        <div class="btn-group">
            <button type="button" class="btn dropdown-toggle" data-toggle="dropdown">Columns <span class="caret"></span></button>
            <ul class="dropdown-menu dropdown-menu-right sx-colvis" id="{{ $colvisId }}"></ul>
        </div>
    </div>
    <div id="{{ $searchId }}"></div>
</div>
