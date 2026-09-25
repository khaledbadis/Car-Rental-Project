<div><h2 class="mb-5 text-xl font-semibold">{{ $reservationId?__('rental.convert'):__('rental.new') }}</h2>
@if($failure)<p role="alert" class="mb-4 rounded-lg bg-red-100 p-4 text-red-900 dark:bg-red-950 dark:text-red-100">{{ $failure }}</p>@endif
<form wire:submit="save" class="space-y-5">
@if(!$reservationId)<div class="grid gap-4 sm:grid-cols-2"><flux:select wire:model="form.customer_id" :label="__('booking.customer')"><option value="">—</option>@foreach($customers  as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</flux:select><flux:select wire:model="form.vehicle_id" :label="__('booking.vehicle')"><option value="">—</option>@foreach($vehicles  as $v)<option value="{{ $v->id }}">{{ $v->registration }} · {{ $v->make }}</option>@endforeach</flux:select><flux:input wire:model="form.starts_at" type="datetime-local" :label="__('booking.starts_at')"/><flux:input wire:model="form.ends_at" type="datetime-local" :label="__('booking.ends_at')"/></div>@else<p class="text-sm text-zinc-500">{{ __('rental.convert_hint') }} #{{ $reservationId }}</p>@endif
<flux:select wire:model="form.driver_id" :label="__('rental.driver')"><option value="">{{ __('rental.default_driver') }}</option>@foreach($drivers  as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</flux:select>
<x-rental-pricing/>
<flux:input wire:model="form.mileage_allowance" type="number" min="0" :label="__('rental.mileage_allowance')"/>
<flux:textarea wire:model="form.terms" :label="__('rental.terms')" rows="2"/>
@can('vehicle-documents.override')<flux:input wire:model="form.document_override_reason" :label="__('booking.override_reason')"/>@endcan
<flux:button type="submit" variant="primary" icon="plus" wire:loading.attr="disabled">{{ __('rental.create_draft') }}</flux:button>
</form></div>
