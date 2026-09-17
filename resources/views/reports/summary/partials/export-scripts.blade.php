@push('scripts')
<script>
(function () {
    var table = document.getElementById('{{ $tableId }}');
    if (!table) return;

    document.getElementById('{{ $printId }}') && document.getElementById('{{ $printId }}').addEventListener('click', function () {
        var win = window.open('', '_blank');
        win.document.write('<html><head><title>{{ $title }}</title>');
        win.document.write('<style>body{font-family:Arial,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px;text-align:left}th{background:#f5f5f5}</style>');
        win.document.write('</head><body>');
        win.document.write('<h2>{{ $title }}</h2>');
        win.document.write(table.outerHTML);
        win.document.write('</body></html>');
        win.document.close();
        win.focus();
        win.print();
    });

    document.getElementById('{{ $excelId }}') && document.getElementById('{{ $excelId }}').addEventListener('click', function () {
        var csv = [];
        table.querySelectorAll('tr').forEach(function (tr) {
            csv.push(Array.from(tr.querySelectorAll('th,td')).map(function (td) {
                return '"' + td.innerText.replace(/"/g, '""') + '"';
            }).join(','));
        });
        var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'application/vnd.ms-excel' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = '{{ $filename }}.xls';
        a.click();
    });
})();
</script>
@endpush
