<?php

return [
    'vehicle' => ['registration' => 'text', 'make' => 'text', 'model' => 'text', 'year' => 'number', 'category_id' => 'category', 'fuel_type' => 'fuel', 'transmission' => 'transmission', 'mileage_km' => 'number', 'daily_rate' => 'money', 'weekly_rate' => 'money', 'monthly_rate' => 'money', 'entered_service_at' => 'date', 'notes' => 'textarea'],
    'customer' => ['type' => 'customer_type', 'name' => 'text', 'phone' => 'tel', 'address' => 'textarea', 'birth_date' => 'date', 'identity_type' => 'identity_type', 'identity_number' => 'text', 'identity_issue_date' => 'date', 'identity_expiry_date' => 'date', 'licence_number' => 'text', 'licence_issue_date' => 'date', 'licence_expiry_date' => 'date', 'licence_country' => 'text', 'contact_person' => 'text', 'tax_identifier' => 'text', 'driver_id' => 'driver'],
    'options' => ['fuel' => ['petrol', 'diesel', 'hybrid', 'electric', 'lpg'], 'transmission' => ['manual', 'automatic'], 'customer_type' => ['individual', 'company'], 'identity_type' => ['national_id', 'passport', 'residence_permit']],
    'documents' => ['vehicle' => ['insurance', 'inspection', 'registration', 'maintenance'], 'customer' => ['identity', 'licence']],
];
