<?php

use Phalcon\Mvc\Model;

class EventShows extends Model
{
    public ?int $event_show_id = null;
    public ?int $eventID = null;
    public ?string $show = null;
    public ?string $venue = null;
    public ?int $show_status = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?string $created = null;
    public ?string $updated = null;

    public function initialize(): void
    {
        $this->setSource('event_shows');
    }
}
