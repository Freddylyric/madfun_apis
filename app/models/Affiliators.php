<?php

use Phalcon\Mvc\Model;

class Affiliators extends Model
{
    public ?int $affilator_id = null;
    public ?int $user_id = null;
    public ?int $status = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize model: set table and relationships
     */
    public function initialize(): void
    {
        $this->setSource('affiliators');

        // Example of possible relationship
        // $this->belongsTo('user_id', Users::class, 'id', ['alias' => 'User']);
    }
}
