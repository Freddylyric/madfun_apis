<?php

use Phalcon\Mvc\Model;

class Events extends Model
{
    public ?int $eventID = null;
    public ?string $eventName = null;
    public ?string $company = null;
    public ?string $venue = null;
    public ?string $eventTag = null;
    public ?string $ussd_access_point = null;
    public ?int $ageLimit = null;
    public ?int $isFeeOnOrganizer = null;
    public ?int $target = null;
    public ?int $category_id = null;
    public ?int $isPublic = null;
    public ?int $soldOut = null;
    public ?int $isFeatured = null;
    public ?int $showOnSlide = null;
    public ?int $isFree = null;
    public ?string $aboutEvent = null;
    public ?string $eventType = null;
    public ?string $posterURL = null;
    public ?string $bannerURL = null;
    public ?float $revenueShare = null;
    public ?string $currency = null;
    public ?int $hasMultipleShow = null;
    public ?int $hasAffiliator = null;
    public ?int $min_price = null;
    public ?int $hasLinkingTag = null;
    public ?string $dateInfo = null;
    public ?int $status = null;
    public ?int $accept_mpesa_payment = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $created = null;
    public ?string $updated = null;

    /**
     * Initialize model: set table name and define relationships if needed
     */
    public function initialize(): void
    {
        $this->setSource('events');

        // Define relationships if applicable, e.g.:
        // $this->belongsTo('category_id', EventCategory::class, 'id', ['alias' => 'Category']);
    }
}
