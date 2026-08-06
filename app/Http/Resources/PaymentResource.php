<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'type'=>$this->type,
            'supplier_id'=>$this->supplier_id,
            'amount'=>$this->amount,
            'display_amount'=>number_format($this->amount,2),
            'display_total'=>number_format($this->total,2),
            'date'=>$this->date,
            'display_date'=>date_format(date_create($this->date),'D dS M Y'),
            'reference_number'=>$this->reference_number,
            'paymentItems'=> PaymentItemResource::collection($this->paymentItems),
            'supplier'=>$this->supplier,
        ];
    }
}
