<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{

    protected $fillable=[
        'id_supplier', 
        'total'
    ];

    public function supplier(){
        return $this->hasOne('App\Models\Supplier', 'id_supplier', 'id_supplier');
    }

    public function items(){
        return $this->hasMany('App\Models\InvoiceItem', 'invoice_id', 'id');
    }

}
