<?php

return ['roles' => [
    'manager' => ['staff.manage', 'settings.manage', 'audit.view', 'fleet.manage', 'blocks.manage', 'customers.manage', 'reservations.manage', 'reservations.cancel', 'rentals.manage', 'payments.collect', 'finance.manage', 'discount.limited', 'pricing.override', 'vehicle-documents.override'],
    'agent' => ['blocks.manage', 'customers.manage', 'reservations.manage', 'reservations.cancel', 'rentals.manage', 'payments.collect', 'discount.limited'],
    'finance' => ['payments.collect', 'finance.manage'],
]];
