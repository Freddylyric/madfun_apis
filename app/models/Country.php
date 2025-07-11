<?php

use Phalcon\Mvc\Model;

class Country extends Model
{
    public ?int $country_id = null;
    public ?string $country_name = null;
    public ?string $country_flag = null;
    public ?string $isoCode = null;
    public ?string $isoCode2 = null;
    public ?string $currency = null;
    public ?int $status = null;
    public ?string $mobile_prefix = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Initialize the model: table name, relationships
     */
    public function initialize(): void
    {
        $this->setSource('country');

        // You can define relationships here if needed, for example:
        // $this->hasMany('country_id', Clients::class, 'country_id', ['alias' => 'Clients']);
    }
}
