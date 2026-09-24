<?php

namespace App\Modules\Reservations;

class Conflict extends \RuntimeException
{
    public function __construct(public string $errorCode, public array $details = [])
    {
        parent::__construct(__('booking.'.$errorCode));
    }
}
