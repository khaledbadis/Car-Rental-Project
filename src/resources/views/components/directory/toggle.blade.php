<div class="inline-flex shrink-0 rounded-lg border border-zinc-200 p-1 dark:border-zinc-700" role="group" aria-label="{{ __('catalog.display') }}">
@foreach(['list'=>'list-bullet','cards'=>'squares-2x2'] as $view=>$icon)
<button type="button" @click="directoryView='{{ $view }}'" :aria-pressed="directoryView==='{{ $view }}'" aria-label="{{ __('catalog.view_'.$view) }}" :class="directoryView==='{{ $view }}' ? 'bg-zinc-200 text-zinc-950 dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800'" class="rounded-md p-2 transition focus-visible:outline-2 focus-visible:outline-teal-600"><flux:icon :name="$icon" class="size-5"/></button>
@endforeach
</div>
