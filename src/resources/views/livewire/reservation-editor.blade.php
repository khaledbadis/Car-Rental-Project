<div>
<a href="{{ route('reservations') }}" class="text-sm text-teal-700 dark:text-teal-300">← {{ __('booking.reservations') }}</a>
<div class="my-6 flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-semibold">{{ $id ? __('booking.reservation').' #'.$id : __('booking.new') }}</h1><a href="{{ route('availability') }}" class="text-teal-700 dark:text-teal-300">{{ __('booking.availability') }}</a></div>
@if($failure)<p role="alert" class="mb-4 rounded-xl bg-red-100 p-4 text-red-900 dark:bg-red-950 dark:text-red-100">{{ $failure }}</p>@endif
@if($pickup)<p class="mb-4 rounded-xl bg-amber-100 p-4 text-amber-900 dark:bg-amber-950 dark:text-amber-100">{{ __('booking.'.$pickup) }} · {{ __('booking.no_auto_release') }}</p>@endif
@if($conflicts->isNotEmpty())<div role="alert" class="mb-4 rounded-xl bg-red-100 p-4 text-red-900 dark:bg-red-950 dark:text-red-100"><strong>{{ __('booking.affected') }}</strong>@foreach($conflicts as $conflict)<p>{{ __('booking.'.$conflict->kind) }} #{{ $conflict->id }} · {{ $conflict->starts_at->setTimezone('Africa/Algiers')->format('d/m/Y H:i') }} — {{ $conflict->ends_at?->setTimezone('Africa/Algiers')->format('d/m/Y H:i') ?? __('booking.indefinite') }}</p>@endforeach</div>@endif
@if($record?->document_override_reason)<p class="mb-4 rounded-xl border border-amber-400 p-4">{{ __('booking.override_recorded') }}: {{ $record->document_override_reason }}</p>@endif
@if($record?->status==='cancelled')<p class="mb-4">{{ __('booking.cancelled') }}: {{ $record->cancellation_reason }}</p>@endif
<p class="mb-4 text-sm text-zinc-500">{{ __('booking.timezone') }} @if($record?->status==='confirmed') · {{ __('booking.buffer') }}: {{ $record->preparation_minutes }} {{ __('booking.minutes') }}@endif</p>
<form wire:submit="save" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-7">
<fieldset @disabled(!auth()->user()->can('reservations.manage') || in_array($record?->status,['cancelled','converted']))>
<div class="grid gap-5 md:grid-cols-2">
<flux:select wire:model="form.customer_id" :label="__('booking.customer')"><option value="">—</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}{{ $c->archived_at?' · '.__('catalog.archived'):'' }}</option>@endforeach</flux:select>
<flux:select wire:model="form.status" :label="__('booking.status')">@foreach(['tentative','confirmed'] as $s)<option value="{{ $s }}">{{ __('booking.'.$s) }}</option>@endforeach @if(in_array($record?->status,['cancelled','converted']))<option value="{{ $record->status }}">{{ __('booking.'.$record->status) }}</option>@endif</flux:select>
<flux:select wire:model="form.category_id" :label="__('booking.category')"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</flux:select>
<flux:select wire:model="form.vehicle_id" :label="__('booking.vehicle')"><option value="">—</option>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->registration }} · {{ $v->make }} {{ $v->model }}{{ $v->archived_at?' · '.__('catalog.archived'):'' }}</option>@endforeach</flux:select>
<flux:input type="datetime-local" wire:model="form.starts_at" :label="__('booking.starts_at')"/>
<flux:input type="datetime-local" wire:model="form.ends_at" :label="__('booking.ends_at')"/>
</div>
<div class="mt-5"><flux:textarea wire:model="form.notes" :label="__('booking.notes')" rows="3"/></div>
@if($id)<div class="mt-5"><flux:input wire:model="reason" :label="__('booking.reason')"/></div>@endif
@can('vehicle-documents.override')<div class="mt-5"><flux:input wire:model="form.document_override_reason" :label="__('booking.override_reason')"/><p class="mt-2 text-xs text-zinc-500">{{ __('booking.override_hint') }}</p></div>@endcan
@if(auth()->user()->can('reservations.manage')&&!in_array($record?->status,['cancelled','converted']))<div class="mt-6 flex flex-wrap gap-3"><flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled">{{ __('ui.save') }}</flux:button>@if($id)@can('reservations.cancel')<flux:button type="button" wire:click="cancel" wire:confirm="{{ __('booking.cancel_confirm') }}" variant="danger" icon="x-mark" wire:loading.attr="disabled">{{ __('booking.cancel') }}</flux:button>@endcan @endif</div>@endif
</fieldset></form>
<p class="mt-4 text-sm text-zinc-500">{{ __('booking.tentative_hint') }}</p>
@if($id)@can('rentals.view')<livewire:ledger-panel owner="reservation" :owner-id="$id" :key="'reservation-ledger-'.$id"/>@endcan
@if($record?->status==='confirmed')@can('rentals.manage')<div class="mt-6"><flux:modal.trigger name="convert-rental"><flux:button variant="primary" icon="key">{{ __('rental.convert') }}</flux:button></flux:modal.trigger><flux:modal name="convert-rental" class="w-full max-w-3xl"><livewire:rental-create :reservation-id="$id"/></flux:modal></div>@endcan @endif
@if($record?->status==='converted')@php($rentalId=\App\Models\Rental::where('reservation_id',$id)->value('id'))@if($rentalId)<a class="mt-5 block text-teal-700 dark:text-teal-300" href="{{ route('rentals.show',$rentalId) }}">{{ __('rental.rental') }} #{{ $rentalId }}</a>@endif @endif @endif
</div>
