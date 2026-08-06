<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' =>$this->id,
            'uniqid'=>$this->uniqid,
            'number'=>$this->number,
            'supplier_id'=>$this->supplier_id,
            'date'=>$this->date,
            'display_date'=>date_format(date_create($this->date),'D dS M Y'),
            'due_date'=>$this->due_date,
            'display_due_date'=>date_format(date_create($this->due_date),'D dS M Y'),
            'terms'=>$this->terms,
            'subtotal'=>round($this->subtotal,2),
            'discount'=>round($this->discount,2),
            'tax'=>round($this->tax,2),
            'total'=>round($this->total,2),
            'display_subtotal'=>number_format($this->subtotal,2),
            'display_discount'=>number_format($this->discount,2),
            'display_tax'=>number_format($this->tax,2),
            'display_total'=>number_format($this->total,2),
            'status'=>$this->status,
            'invoiceItems'=> InvoiceItemsResource::collection($this->invoiceItems),
            'paymentItems'=> PaymentItemResource::collection($this->paymentItems),
            'supplier'=>$this->supplier,
            'createdBy'=>$this->createdBy,
            'balance' => $this->balance,
            'original_balance' => $this->balance
        ];
    }
}
