<?php

use Phalcon\Mvc\Model;

class EventCategory extends Model
{
    public ?int $id = null;
    public ?string $category = null;
    public ?string $desciption = null;
    public ?string $status = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize the model and its relationships
     */
    public function initialize(): void
    {
        $this->setSource('event_category');

        // This line below looks incorrect in original model (client_id does not belong here)
        // Removed unless you confirm it's needed:
        // $this->hasMany('client_id', 'UserClientMap', 'client_id', ['alias' => 'UserClientMap']);
    }
}
