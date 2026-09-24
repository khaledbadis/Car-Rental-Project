<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()==='ar' ? 'rtl' : 'ltr' }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ __('ui.brand') }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
<script>const appearance=@json(auth()->user()?->theme ?? 'system'); const media=window.matchMedia('(prefers-color-scheme: dark)');function applyTheme(){document.documentElement.classList.toggle('dark',appearance==='dark'||(appearance==='system'&&media.matches));}applyTheme();media.addEventListener('change',applyTheme);</script>
@livewireStyles
</head>

<body class="min-h-screen bg-stone-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
<a href="#main" class="sr-only focus:not-sr-only">{{ __('ui.skip') }}</a>
<div x-data="{mobileOpen:false, collapsed:localStorage.getItem('sidebar-collapsed')==='true', desktop:window.innerWidth>=1024, init(){this.$watch('collapsed',v=>localStorage.setItem('sidebar-collapsed',v));this.$watch('mobileOpen',v=>{document.body.style.overflow=v&&!this.desktop?'hidden':'';if(v&&!this.desktop)this.$nextTick(()=>document.getElementById('sidebar-close')?.focus());});}, close(){this.mobileOpen=false;$refs.sidebarToggle?.focus();}}" @resize.window="desktop=window.innerWidth>=1024;if(desktop){mobileOpen=false;document.body.style.overflow='';}" @keydown.escape.window="close()" :class="{'sidebar-collapsed':collapsed,'sidebar-open':mobileOpen}" class="app-shell min-h-screen">
@auth
<div x-show="mobileOpen && !desktop" x-cloak x-transition.opacity.duration.250ms @click="close()" class="fixed inset-0 z-40 bg-zinc-950/50 backdrop-blur-[2px] lg:hidden" aria-hidden="true"></div>
<aside id="navigation" :inert="!desktop && !mobileOpen" class="app-sidebar fixed inset-y-0 start-0 z-50 flex h-dvh flex-col border-e border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900" aria-label="{{ __('ui.menu') }}">
<div class="flex h-20 shrink-0 items-center gap-3 px-5"><a href="/" class="flex min-w-0 items-center gap-3" aria-label="{{ __('ui.brand') }}"><span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-teal-700 text-white"><flux:icon name="truck" class="size-6"/></span><span class="sidebar-label truncate font-semibold">{{ __('ui.brand') }}<span class="mt-1 block text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ __('ui.workspace') }}</span></span></a><button id="sidebar-close" type="button" @click="close()" class="ms-auto rounded-lg p-2 lg:hidden" aria-label="{{ __('catalog.close_menu') }}"><flux:icon name="x-mark" class="size-5"/></button></div>
<nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="{{ __('ui.menu') }}">
@foreach(['dashboard'=>['home',null],'vehicles'=>['truck','fleet.view'],'customers'=>['user-group','customers.view'],'reservations'=>['calendar-days','reservations.view'],'availability'=>['calendar-date-range','reservations.view'],'preferences'=>['adjustments-horizontal',null],'staff'=>['users','staff.manage'],'settings'=>['cog-6-tooth','settings.manage'],'audit'=>['clock','audit.view']] as $page=>[$icon,$permission])
@if(!$permission || auth()->user()->can($permission))
@php($target=in_array($page,['vehicles','customers'])?$page.'.index':$page)
@php($label=in_array($page,['vehicles','customers'])?__('catalog.'.$page):__('ui.'.$page))
<a href="{{ route($target) }}" title="{{ $label }}" aria-label="{{ $label }}" @if(request()->routeIs($page,$page.'.*')) aria-current="page" @endif class="sidebar-link flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium {{ request()->routeIs($page,$page.'.*')?'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-200':'hover:bg-zinc-100 dark:hover:bg-zinc-800' }}"><flux:icon :name="$icon" class="size-5 shrink-0"/><span class="sidebar-label whitespace-nowrap">{{ $label }}</span></a>
@endif @endforeach
</nav>
<div class="shrink-0 border-t border-zinc-200 p-4 dark:border-zinc-800"><a href="{{ route('preferences') }}" class="mb-3 flex items-center gap-3" aria-label="{{ __('catalog.profile') }}"><span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-teal-800 dark:bg-zinc-800 dark:text-teal-200"><flux:icon name="user" class="size-5"/></span><span class="sidebar-label min-w-0"><span class="block truncate text-sm font-semibold">{{ auth()->user()->name }}</span><span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('ui.'.auth()->user()->role) }}</span></span></a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800" title="{{ __('ui.logout') }}" aria-label="{{ __('ui.logout') }}"><flux:icon name="arrow-right-start-on-rectangle" class="size-5 shrink-0"/><span class="sidebar-label">{{ __('ui.logout') }}</span></button></form></div>
</aside>
<div class="app-content min-w-0" :inert="mobileOpen && !desktop">
<header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 px-4 py-4 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95 sm:px-7">
<div class="flex items-center gap-3"><button x-ref="sidebarToggle" type="button" @click="desktop ? collapsed=!collapsed : mobileOpen=!mobileOpen" :aria-expanded="desktop ? !collapsed : mobileOpen" aria-controls="navigation" title="{{ __('catalog.toggle_sidebar') }}" aria-label="{{ __('catalog.toggle_sidebar') }}" class="shrink-0 rounded-lg p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800"><flux:icon name="bars-3" class="size-6"/></button>
<nav aria-label="{{ __('catalog.breadcrumbs') }}" class="hidden min-w-0 flex-1 sm:block"><ol class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400"><li><a href="/" class="hover:text-teal-700">{{ __('ui.dashboard') }}</a></li>@if(!request()->routeIs('dashboard'))<li aria-hidden="true">/</li><li class="truncate text-zinc-900 dark:text-zinc-100">@php($section=explode('.',request()->route()->getName()??'')[0])@if(in_array($section,['vehicles','customers']))<a href="{{ route($section.'.index') }}">{{ __('catalog.'.$section) }}</a>@else{{ __('ui.'.$section) }}@endif</li>@if(request()->route('id'))<li aria-hidden="true">/</li><li aria-current="page">#{{ request()->route('id') }}</li>@endif @endif</ol></nav>
<div class="ms-auto flex min-w-0 flex-1 items-center justify-end gap-1 sm:flex-none sm:gap-3"><livewire:global-search/><livewire:notifications/><a href="{{ route('preferences') }}" title="{{ __('catalog.profile') }}" aria-label="{{ __('catalog.profile') }}" class="flex size-10 shrink-0 items-center justify-center rounded-full border border-zinc-200 bg-stone-50 dark:border-zinc-700 dark:bg-zinc-800"><flux:icon name="user-circle" class="size-6"/></a></div></div>
<nav aria-label="{{ __('catalog.breadcrumbs') }}" class="mt-3 flex gap-2 text-xs text-zinc-500 sm:hidden"><a href="/">{{ __('ui.dashboard') }}</a>@if(!request()->routeIs('dashboard'))<span>/</span><span>@php($section=explode('.',request()->route()->getName()??'')[0]){{ in_array($section,['vehicles','customers'])?__('catalog.'.$section):__('ui.'.$section) }}</span>@if(request()->route('id'))<span>/ #{{ request()->route('id') }}</span>@endif @endif</nav>
</header>
@endauth
<main id="main" class="mx-auto min-w-0 max-w-screen-2xl p-5 sm:p-8 lg:p-10">
@if(session('success'))<p role="status" class="mb-6 rounded-xl bg-teal-100 p-4 text-teal-900 dark:bg-teal-950 dark:text-teal-100">{{ session('success') }}</p>@endif
{{ $slot }}
</main>
@auth</div>@endauth
</div>
@livewireScripts
@fluxScripts
</body></html>
