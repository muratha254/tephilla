<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DocumentShareMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public array $payload;

    public string $pdfBinary;

    public string $filename;

    public function __construct(array $payload, string $pdfBinary, string $filename)
    {
        $this->payload = $payload;
        $this->pdfBinary = $pdfBinary;
        $this->filename = $filename;
    }

    public function build()
    {
        return $this->subject($this->payload['title'])
            ->view('emails.document-share')
            ->attachData($this->pdfBinary, $this->filename, [
                'mime' => 'application/pdf',
            ]);
    }
}
