@page {
    margin: 0;
    size: A4 portrait;
}
html, body {
    margin: 0;
    padding: 0;
    width: 210mm;
    font-family: 'DejaVu Sans', Arial, sans-serif;
}
.pdf-page-body {
    padding-left: 12mm;
    padding-right: 12mm;
    box-sizing: border-box;
}
.document-header-image {
    width: 210mm;
    height: auto;
    display: block;
    margin: 0;
    padding: 0;
    border: 0;
}
.pdf-page-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 210mm;
    margin: 0;
    padding: 0;
    line-height: 0;
}
.pdf-page-footer img {
    width: 210mm;
    display: block;
    margin: 0;
    padding: 0;
    border: 0;
}
.document-edge-footer-text {
    width: 210mm;
    margin: 0;
    padding: 3mm 0;
    font-size: 8pt;
    color: #888;
    text-align: center;
    line-height: 1.4;
    background: #fff;
}
.document-header-text {
    margin-bottom: 6px;
    padding: 6px 8px;
    background: #f8fafc;
    border: 1px solid #e3e8ef;
    font-size: 8.5pt;
    color: #555;
    line-height: 1.4;
}
.avoid-break {
    page-break-inside: avoid;
}
