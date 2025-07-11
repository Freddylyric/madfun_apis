<?php

use Phalcon\Mvc\Model;

class GreenJobClient extends Model
{
    public ?int $green_job_customer_id = null;
    public ?int $profile_id = null;
    public ?string $full_name = null;
    public ?string $email = null;
    public ?string $organisation = null;
    public ?string $title = null;
    public ?string $preferred_workstreams = null;
    public ?string $county = null;
    public ?string $age = null;
    public ?string $gender = null;
    public ?string $category = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Set table name and define relationships if necessary
     */
    public function initialize(): void
    {
        $this->setSource('green_job_client');

        // Define relationships here if needed
        // $this->belongsTo('profile_id', Profile::class, 'id', ['alias' => 'Profile']);
    }
}
