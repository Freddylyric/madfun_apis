<?php

use Phalcon\Mvc\Model;

class TicketTypes extends Model
{
    public ?int $typeId = null;
    public ?string $ticket_type = null;
    public ?int $status = null;
    public ?string $caption = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize the model and map the table
     */
    public function initialize(): void
    {
        $this->setSource('ticket_types');

        // Example: Define relationships here if needed
        // $this->hasMany('typeId', EventTicketsType::class, 'typeId', ['alias' => 'EventTickets']);
    }
}
