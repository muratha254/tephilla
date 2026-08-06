<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentItemResource extends JsonResource
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
            'payment_id'=>$this->payment_id,
            'document'=>$this->document,
            'document_id'=>$this->document_id,
            'narrative'=>$this->narrative,
            'amount'=>$this->amount,
            'createdBy'=>$this->user
        ];
    }
}
