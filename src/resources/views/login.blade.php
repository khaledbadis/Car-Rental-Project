<x-layouts.app><div class="mx-auto mt-8 max-w-md sm:mt-20"><p class="mb-10 text-sm font-semibold uppercase tracking-widest text-teal-700 dark:text-teal-300">{{ __('ui.brand') }}</p><h1 class="text-3xl font-semibold">{{ __('ui.login') }}</h1><p class="mt-3 text-zinc-600 dark:text-zinc-400">{{ __('ui.login_hint') }}</p>
<form method="post" action="/login" class="mt-8 space-y-5">@csrf
<flux:input name="email" type="email" :label="__('ui.email')" :value="old('email')" autocomplete="username" required />
<flux:input name="password" type="password" :label="__('ui.password')" autocomplete="current-password" required />
<flux:button type="submit" variant="primary" class="w-full">{{ __('ui.login') }}</flux:button>
</form><p class="mt-6 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.recovery_hint') }}</p>
<form method="post" action="/locale" class="mt-8 flex flex-wrap gap-3">@csrf @foreach(['fr'=>'Français','ar'=>'العربية','en'=>'English'] as $key=>$label)<button name="locale" value="{{ $key }}" lang="{{ $key }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700" aria-pressed="{{ app()->getLocale()===$key?'true':'false' }}">{{ $label }}</button>@endforeach</form></div></x-layouts.app>
