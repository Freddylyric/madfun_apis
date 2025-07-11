<?php

use Phalcon\Mvc\Model;

class ProfileAttribute extends Model
{
    public ?int $id = null;
    public ?int $profile_id = null;
    public ?int $year_of_birth = null;
    public ?string $first_name = null;
    public ?string $surname = null;
    public ?string $last_name = null;
    public ?string $age_bracket = null;
    public ?string $gender = null;
    public ?string $idNumber = null;
    public ?string $network = null;
    public ?string $pin = null;
    public ?string $country = null;
    public ?string $city = null;
    public ?string $token = null;
    public ?int $status = null;
    public ?int $frequency = null;
    public ?string $last_dial_date = null;
    public ?string $created_by = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize model relationships and table
     */
    public function initialize(): void
    {
        $this->setSource('profile_attribute');
        $this->belongsTo('profile_id', Profile::class, 'profile_id', ['alias' => 'Profile']);
    }
}
