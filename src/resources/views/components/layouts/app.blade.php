<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __('ui.brand') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
<script>const appearance=@json(auth()->user()?->theme ?? 'system'); const media=window.matchMedia('(prefers-color-scheme: dark)');function applyTheme(){document.documentElement.classList.toggle('dark',appearance==='dark'||(appearance==='system'&&media.matches));}applyTheme();media.addEventListener('change',applyTheme);</script>
@livewireStyles
</head>
<body class="min-h-screen bg-stone-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
<a href="#main" class="sr-only focus:not-sr-only">{{ __('ui.skip') }}</a>
<div x-data="{ menu:false }" @keydown.escape.window="menu=false" class="min-h-screen">
@auth
<header class="flex items-center justify-between border-b border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-900 lg:hidden"><span class="font-semibold">{{ __('ui.brand') }}</span><flux:button @click="menu=!menu" x-bind:aria-expanded="menu" aria-controls="navigation">{{ __('ui.menu') }}</flux:button></header>
<aside id="navigation" x-bind:class="menu ? 'block' : 'hidden'" class="fixed inset-y-0 start-0 z-30 w-64 border-e border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900 max-lg:top-18 max-lg:overflow-y-auto lg:block!">
<a href="/" class="block text-xl font-semibold tracking-tight">{{ __('ui.brand') }}<span class="mt-1 block text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ __('ui.workspace') }}</span></a>
<nav class="mt-12 space-y-2" aria-label="{{ __('ui.menu') }}">
@foreach(['dashboard','preferences','staff','settings','audit'] as $page)
@if(in_array($page,['dashboard','preferences']) || auth()->user()->can(['staff'=>'staff.manage','settings'=>'settings.manage','audit'=>'audit.view'][$page]))
<a href="{{ route($page) }}" @if(request()->routeIs($page)) aria-current="page" @endif class="block rounded-xl px-4 py-3 text-sm font-medium {{ request()->routeIs($page) ? 'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200' : 'hover:bg-zinc-100 dark:hover:bg-zinc-800' }}">{{ __('ui.'.$page) }}</a>
@endif
@endforeach
</nav>
<div class="mt-16 border-t border-zinc-200 pt-5 dark:border-zinc-700"><p class="break-words font-medium">{{ auth()->user()->name }}</p><p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('ui.'.auth()->user()->role) }}</p><form method="post" action="{{ route('logout') }}" class="mt-4">@csrf<flux:button type="submit" variant="ghost">{{ __('ui.logout') }}</flux:button></form></div>
</aside>
@endauth
<main id="main" class="mx-auto min-w-0 p-5 sm:p-8 lg:p-12 @auth lg:ms-64 @endauth">
@if(session('success'))<p role="status" class="mb-6 rounded-xl bg-teal-100 p-4 text-teal-900 dark:bg-teal-950 dark:text-teal-100">{{ session('success') }}</p>@endif
{{ $slot }}
</main>
</div>
@livewireScripts
@fluxScripts
</body></html>
