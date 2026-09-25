@props(['prefix'=>'form'])
<div class="grid gap-4 sm:grid-cols-2">
<flux:select wire:model="{{ $prefix }}.basis" :label="__('rental.basis')">@foreach(['daily','weekly','monthly'] as $basis)<option value="{{ $basis }}">{{ __('rental.'.$basis) }}</option>@endforeach</flux:select>
<flux:select wire:model="{{ $prefix }}.discount_type" :label="__('rental.discount_type')">@foreach(['none','percent','fixed'] as $type)<option value="{{ $type }}">{{ __('rental.'.$type) }}</option>@endforeach</flux:select>
<flux:input wire:model="{{ $prefix }}.discount_value" type="number" min="0" step="0.01" :label="__('rental.discount_value')"/>
@can('pricing.override')<flux:input wire:model="{{ $prefix }}.negotiated_total" type="number" min="0" step="0.01" :label="__('rental.negotiated_total')"/>@endcan
<flux:input wire:model="{{ $prefix }}.pricing_reason" :label="__('rental.pricing_reason')"/>
</div><p class="mt-3 text-xs text-zinc-500">{{ __('rental.price_hint') }}</p>
