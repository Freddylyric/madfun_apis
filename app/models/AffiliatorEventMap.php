<?php

use Phalcon\Mvc\Model;

class AffiliatorEventMap extends Model
{
    public ?int $id = null;
    public ?int $affilator_id = null;
    public ?int $eventId = null;
    public ?string $code = null;
    public ?int $discount = null;
    public ?int $status = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize model: set table and relationships
     */
    public function initialize(): void
    {
        $this->setSource('affiliator_event_map');

        // Example of potential relationship
        // $this->belongsTo('affilator_id', Affiliator::class, 'id', ['alias' => 'Affiliator']);
        // $this->belongsTo('eventId', Events::class, 'id', ['alias' => 'Event']);
    }
}
