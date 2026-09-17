<?php

return [
    'currency_code' => 'KES',
    'currency_symbol' => 'KSh',
    'timezone' => 'Africa/Nairobi',
    'date_format' => 'd/m/Y',
    'default_tax_rate' => 16,
    'default_tax_name' => 'VAT',

    'document_prefixes' => [
        'invoice' => 'INV-',
        'receipt' => 'RCP-',
        'quotation' => 'QT-',
        'purchase_order' => 'PO-',
        'sale' => 'SL-',
        'sale_return' => 'SR-',
        'purchase_return' => 'PR-',
        'goods_receipt' => 'GRN-',
        'payment' => 'PAY-',
        'expense' => 'EXP-',
        'journal' => 'JE-',
        'credit_note' => 'CN-',
        'stock_transfer' => 'ST-',
        'payroll' => 'PRN-',
        'hr_payment' => 'HRP-',
    ],

    'number_padding' => 5,

    'receipt_paper_sizes' => ['80mm', '58mm', 'a4'],

    'purchase_statuses' => [
        'pending' => 'Pending',
        'ordered' => 'Ordered',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'payment_methods' => [
        'cash' => 'Cash',
        'mpesa' => 'M-Pesa',
        'card' => 'Card',
        'bank_transfer' => 'Bank Transfer',
        'cheque' => 'Cheque',
        'complementary' => 'Complementary',
        'advance' => 'Advance',
        'other' => 'Other',
    ],

    'cheque_types' => [
        'incoming' => 'Incoming',
        'outgoing' => 'Outgoing',
    ],

    'control_accounts' => [
        'migration_control' => 'Migration Control',
        'inventory_adjustment' => 'Inventory Adjustment',
        'stock_variance' => 'Stock Variance',
    ],

    'kenya_counties' => [
        'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo-Marakwet', 'Embu', 'Garissa',
        'Homa Bay', 'Isiolo', 'Kajiado', 'Kakamega', 'Kericho', 'Kiambu', 'Kilifi',
        'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui', 'Kwale', 'Laikipia', 'Lamu', 'Machakos',
        'Makueni', 'Mandera', 'Marsabit', 'Meru', 'Migori', 'Mombasa', 'Murang\'a',
        'Nairobi', 'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua', 'Nyeri',
        'Samburu', 'Siaya', 'Taita-Taveta', 'Tana River', 'Tharaka-Nithi', 'Trans Nzoia',
        'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot',
    ],

    'stock_adjust_statuses' => [
        'addition' => 'Addition',
        'reduction' => 'Reduction',
        'damaged' => 'Damaged',
        'expired' => 'Expired',
        'issued' => 'Issued',
    ],
];
