<?php

namespace App\Mail;

use App\Models\SubscriptionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionInvoiceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public SubscriptionInvoice $invoice;

    public function __construct(SubscriptionInvoice $invoice)
    {
        $this->invoice = $invoice->loadMissing(['company', 'subscription.plan']);
    }

    public function build()
    {
        return $this->subject('Subscription invoice '.$this->invoice->invoice_number)
            ->view('emails.subscription-invoice');
    }
}
