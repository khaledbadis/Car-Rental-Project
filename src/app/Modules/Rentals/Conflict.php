<?php

namespace App\Modules\Rentals;

class Conflict extends \RuntimeException
{
    public function __construct(public string $errorCode, public array $details = [])
    {
        parent::__construct(__('rental.'.$errorCode));
    }
}
