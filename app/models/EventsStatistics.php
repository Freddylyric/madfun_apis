<?php

use Phalcon\Mvc\Model;

class EventsStatistics extends Model
{
    public ?int $event_stats_id = null;
    public ?int $eventID = null;
    public ?int $total_tickets = null;
    public ?int $total_tickets_collection = null;
    public ?float $serivces_fee = null; // Typo likely: should be `services_fee`?
    public ?float $ticket_refund = null;
    public ?float $ticket_purchased = null;
    public ?float $ticket_withdraw = null;
    public ?int $ticket_redeemed = null;
    public ?int $total_complimentary = null;
    public ?int $issued_complimentary = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize model and set table name
     */
    public function initialize(): void
    {
        $this->setSource('event_statistics');

        // You can define relationships here if needed:
        // $this->belongsTo('eventID', Events::class, 'eventID', ['alias' => 'Event']);
    }
}
