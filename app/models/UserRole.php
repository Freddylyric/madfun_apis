<?php

use Phalcon\Mvc\Model;

class UserRole extends Model
{
    public ?int $user_role_id = null;
    public ?string $role_name = null;
    public ?string $role_description = null;
    public ?string $created = null;
    public ?string $modified = null;

    public function initialize(): void
    {
        $this->setSource('user_role');
    }
}
