<?php

use Phalcon\Mvc\Model;

class EventTicketsType extends Model
{
    public ?int $event_ticket_id = null;
    public ?int $typeId = null;
    public ?string $color_code = null;
    public ?int $maxCap = null;
    public ?int $perUserCap = null;
    public ?string $main_color_code = null;
    public ?string $description = null;
    public ?int $eventId = null;
    public ?int $amount = null;
    public ?int $total_tickets = null;
    public ?int $discount = null;
    public ?int $group_ticket_quantity = null;
    public ?int $total_complimentary = null;
    public ?int $total_ticket_code = null;
    public ?int $ticket_purchased = null;
    public ?int $issued_complimentary = null;
    public ?int $issued_ticket_code = null;
    public ?int $ticket_redeemed = null;
    public ?int $isPartialPay = null;
    public ?int $isPublic = null;
    public ?int $hasOption = null;
    public ?int $status = null;
    public ?string $created = null;
    public ?string $updated = null;
    
    

    /**
     * Initialize model: define table name and relationships
     */
    public function initialize(): void
    {
        $this->setSource('event_tickets_type');

        // Define relationships if applicable:
        // $this->belongsTo('eventId', Event::class, 'id', ['alias' => 'Event']);
    }
}
