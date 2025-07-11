<?php

use Phalcon\Mvc\Model;

class Profile extends Model
{
    public ?int $profile_id = null;
    public ?string $msisdn = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize the model and define the database table
     */
    public function initialize(): void
    {
        $this->setSource('profile');

        // Define relationships if needed
        // $this->hasMany('profile_id', GreenJobClient::class, 'profile_id', ['alias' => 'GreenJobClients']);
    }
}
