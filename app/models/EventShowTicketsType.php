<?php

use Phalcon\Mvc\Model;

class EventShowTicketsType extends Model
{
    public ?int $event_ticket_show_id = null;
    public ?string $color_code = null;
    public ?string $main_color_code = null;
    public ?int $typeId = null;
    public ?string $description = null;
    public ?int $event_show_venue_id = null;
    public ?int $amount = null;
    public ?int $total_tickets = null;
    public ?int $discount = null;
    public ?int $group_ticket_quantity = null;
    public ?int $total_complimentary = null;
    public ?int $total_ticket_code = null;
    public ?int $ticket_purchased = null;
    public ?int $maxCap = null;
    public ?int $perUserCap = null;
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
     * Initialize model: set table and relationships
     */
    public function initialize(): void
    {
        $this->setSource('event_show_tickets_type');

        // Add relationships here if applicable, e.g.:
        // $this->belongsTo('event_show_venue_id', EventShowVenue::class, 'id', ['alias' => 'Venue']);
    }
}
