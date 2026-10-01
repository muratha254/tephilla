<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
</head>
<body style="font-family: Arial, sans-serif; color:#333; line-height:1.5;">
    <p>Hello {{ $invoice->company->owner_name ?: $invoice->company->name }},</p>
    <p>Please find your TEPHILLA SYSTEM subscription invoice below.</p>
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
        <tr><td>Invoice</td><td><strong>{{ $invoice->invoice_number }}</strong></td></tr>
        <tr><td>Business</td><td>{{ $invoice->company->name }}</td></tr>
        <tr><td>Plan</td><td>{{ $invoice->plan_name ?: optional(optional($invoice->subscription)->plan)->name ?: '-' }}</td></tr>
        <tr><td>Amount</td><td><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->amount, 2) }}</strong></td></tr>
        <tr><td>Due date</td><td>{{ optional($invoice->due_date)->format('d M Y') }}</td></tr>
    </table>
    @if($invoice->notes)
        <p>{{ $invoice->notes }}</p>
    @endif
    <p>Sign in to TEPHILLA SYSTEM and open <strong>Billing</strong> to view this invoice and your payment history.</p>
    <p>Thank you,<br>TEPHILLA SYSTEM</p>
</body>
</html>
