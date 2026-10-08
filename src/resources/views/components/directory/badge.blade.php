@props(['muted'=>false])
<span {{ $attributes->class(['inline-flex max-w-full items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium','bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'=>$muted,'bg-teal-50 text-teal-800 dark:bg-teal-950 dark:text-teal-300'=>!$muted]) }}><span class="size-1.5 shrink-0 rounded-full bg-current"></span>{{ $slot }}</span>
