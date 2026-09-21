<div class="max-w-2xl"><h1 class="text-3xl font-semibold">{{ __('ui.settings') }}</h1><form wire:submit="save" class="mt-8 space-y-5 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
@foreach(['preparation_minutes','late_grace_minutes','no_show_minutes','upload_limit_mb'] as $field)<flux:input wire:model="{{ $field }}" type="number" :label="__('ui.'.$field)" />@endforeach
<flux:textarea wire:model="reason" :label="__('ui.reason')" rows="2" /><flux:button type="submit" variant="primary">{{ __('ui.save') }}</flux:button></form></div>
