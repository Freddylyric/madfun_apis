<?php

use Phalcon\Mvc\Model;

class Clients extends Model
{
    public ?int $client_id = null;
    public ?string $client_name = null;
    public ?string $address = null;
    public ?string $msisdn = null;
    public ?string $email_address = null;
    public ?string $description = null;
    public ?int $created_by = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Initialize the model and its relationships
     */
    public function initialize(): void
    {
        $this->setSource('clients');

        $this->hasMany(
            'client_id',
            UserClientMap::class,
            'client_id',
            [
                'alias' => 'UserClientMap',
            ]
        );
    }
}
