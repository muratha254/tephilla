<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
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
            'produk_id'=>$this->produk_id,
            'quantity'=>$this->quantity,
            'amount'=>round($this->amount,2),
            'display_amount'=>number_format($this->amount,2),
            'discount'=>round($this->discount,2),
            'display_discount'=>number_format($this->discount,2),
            'status'=>$this->status,
            'produk'=> $this->produk,
            'createdBy'=>$this->createdBy,
            'invoice'=>$this->invoice
        ];
    }
}
