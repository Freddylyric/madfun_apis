<?php

use Phalcon\Mvc\Model;

class StreamProfileRequest extends Model
{
    public ?int $id = null;
    public ?int $profile_id = null;
    public ?string $order_key = null;
    public ?string $currency = null;
    public ?int $amount = null;
    public ?string $reference_id = null;
    public ?string $item_name = null;
    public ?string $returnURL = null;
    public ?string $cancelURL = null;
    public ?int $status = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize model and define relationships
     */
    public function initialize(): void
    {
        $this->setSource('stream_profile_request');

        // Optional: Define relationship if Profile exists
        // $this->belongsTo('profile_id', Profile::class, 'profile_id', ['alias' => 'Profile']);
    }
}
