<?php

use Phalcon\Mvc\Model;

class User extends Model
{
    public ?int $user_id = null;
    public ?string $email = null;
    public ?int $profile_id = null;
    public ?string $password = null;
    public ?string $api_token = null;
    public ?string $last_login = null;
    public ?string $date_activated = null;
    public ?int $status = null;
    public ?int $role_id = null;
    public ?string $created = null;
    public ?string $updated = null;
    public ?string $deleted = null;

    /**
     * Initialize the model and map the source table
     */
    public function initialize(): void
    {
        $this->setSource('user');

        // Example relationships (uncomment if needed)
        // $this->belongsTo('profile_id', Profile::class, 'profile_id', ['alias' => 'Profile']);
        // $this->belongsTo('role_id', Role::class, 'role_id', ['alias' => 'Role']);
    }
}
