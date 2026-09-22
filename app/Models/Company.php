<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'owner_name',
        'slug',
        'logo_path',
        'address',
        'city',
        'state',
        'postcode',
        'country',
        'phone',
        'phone_alt',
        'email',
        'website',
        'tax_pin',
        'vat_number',
        'employer_code',
        'bank_details',
        'quotation_terms',
        'currency_code',
        'currency_symbol',
        'date_format',
        'timezone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function defaultBranch()
    {
        return $this->hasOne(Branch::class)->where('is_default', true);
    }

    public function walkInCustomer()
    {
        return $this->hasOne(Customer::class)->where('is_walk_in', true);
    }

    public function settings()
    {
        return $this->hasMany(Setting::class);
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function subscriptionHistory()
    {
        return $this->hasMany(SubscriptionHistory::class);
    }

    public function subscriptionInvoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function subscriptionPayments()
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
