<div class="max-w-2xl"><h1 class="text-3xl font-semibold">{{ __('ui.preferences') }}</h1>
@if(auth()->user()->must_change_password)<p role="alert" class="mt-5 rounded-xl bg-amber-100 p-4 text-amber-950">{{ __('ui.change_required') }}</p>@endif
<form wire:submit="save" class="mt-8 space-y-5 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
<flux:select wire:model="locale" :label="__('ui.locale')"><option value="fr">Français</option><option value="ar">العربية</option><option value="en">English</option></flux:select>
<flux:select wire:model="theme" :label="__('ui.theme')">@foreach(['system','light','dark'] as $mode)<option value="{{ $mode }}">{{ __('ui.'.$mode) }}</option>@endforeach</flux:select>
<flux:button type="submit" variant="primary">{{ __('ui.save') }}</flux:button></form>
<form wire:submit="changePassword" class="mt-8 space-y-5 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="text-xl font-semibold">{{ __('ui.change_password') }}</h2>
@foreach(['current_password','password','password_confirmation'] as $field)<flux:input type="password" wire:model="{{ $field }}" :label="__('ui.'.$field)" autocomplete="{{ $field==='current_password'?'current-password':'new-password' }}" />@endforeach
<flux:button type="submit" variant="primary">{{ __('ui.change_password') }}</flux:button></form></div>
