<?php

return ['roles' => [
    'manager' => ['fleet.view', 'fleet.archive', 'customers.view', 'customers.archive', 'customer-documents.view', 'documents.remove', 'staff.manage', 'settings.manage', 'audit.view', 'fleet.manage', 'blocks.manage', 'customers.manage', 'reservations.manage', 'reservations.cancel', 'rentals.manage', 'payments.collect', 'finance.manage', 'discount.limited', 'pricing.override', 'vehicle-documents.override'],
    'agent' => ['fleet.view', 'customers.view', 'customer-documents.view', 'blocks.manage', 'customers.manage', 'reservations.manage', 'reservations.cancel', 'rentals.manage', 'payments.collect', 'discount.limited'],
    'finance' => ['fleet.view', 'customers.view', 'payments.collect', 'finance.manage'],
]];
