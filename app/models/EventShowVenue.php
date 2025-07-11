<?php

use Phalcon\Mvc\Model;

class EventShowVenue extends Model
{
    public ?int $event_show_venue_id = null;
    public ?int $event_show_id = null;
    public ?string $venue = null;
    public ?int $status = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize model: set table name and relationships
     */
    public function initialize(): void
    {
        $this->setSource('event_show_venue');

        // Define relationships here if needed, e.g.:
        // $this->belongsTo('event_show_id', EventShow::class, 'id', ['alias' => 'EventShow']);
    }
}
