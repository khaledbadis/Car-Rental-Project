<div x-data="{directoryView:'list'}" class="space-y-7">
<div><h1 class="text-3xl font-semibold">{{ __('maintenance.title') }}</h1><p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('maintenance.intro') }}</p></div>
@if($failure)<p role="alert" class="rounded-xl bg-red-100 p-4 text-red-900 dark:bg-red-950 dark:text-red-100">{{ $failure }}</p>@endif

<section class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="text-xl font-semibold">{{ __('maintenance.reminders') }}</h2><p class="mt-2 text-sm">{{ __('maintenance.recorded_mileage') }}</p><div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">@forelse($reminders as $r)<a href="{{ route('maintenance',['vehicle_id'=>$r['vehicle_id']]) }}" class="rounded-xl border border-amber-300 p-4 dark:border-amber-800"><span class="font-semibold">{{ $r['registration'] }} · {{ __('maintenance.'.$r['service_type']) }}</span><p>{{ __('maintenance.'.$r['status']) }} · {{ $r['next_due_on'] ?? '—' }} · {{ $r['next_due_km'] ?? '—' }} km</p><p class="text-sm">{{ __('maintenance.current_mileage') }}: {{ $r['mileage_km'] }} km</p></a>@empty<p class="text-zinc-500">{{ __('maintenance.none_due') }}</p>@endforelse
</div></section>
@can('maintenance.manage')
<form wire:submit="save" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><h2 class="mb-5 text-xl font-semibold">{{ __('maintenance.record') }}</h2><div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
<flux:select wire:model="form.vehicle_id" :label="__('maintenance.vehicle')"><option value="">—</option>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->registration }}</option>@endforeach
</flux:select>
<flux:select wire:model="form.service_type" :label="__('maintenance.service_type')">@foreach(\App\Modules\Maintenance\Actions::TYPES as $type)<option value="{{ $type }}">{{ __('maintenance.'.$type) }}</option>@endforeach
</flux:select>
<flux:input wire:model="form.serviced_on" type="date" :label="__('maintenance.serviced_on')"/>
<flux:input wire:model="form.mileage_km" type="number" min="0" :label="__('maintenance.mileage_km')"/>
<flux:input wire:model="form.cost" inputmode="decimal" :label="__('maintenance.cost')"/>
<flux:select wire:model="form.vehicle_commitment_id" :label="__('maintenance.block')"><option value="">—</option>@foreach($blocks as $b)<option value="{{ $b->id }}">#{{ $b->id }} · {{ $b->vehicle->registration }} · {{ $b->starts_at->format('Y-m-d') }} · {{ $b->released_at ? __('maintenance.released') : __('maintenance.open') }}</option>@endforeach
</flux:select>
<flux:input wire:model="form.next_due_on" type="date" :label="__('maintenance.next_due_on')"/>
<flux:input wire:model="form.next_due_km" type="number" :label="__('maintenance.next_due_km')"/>
</div><div class="mt-5"><flux:textarea wire:model="form.notes" :label="__('maintenance.notes')" rows="2"/></div><p class="my-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('maintenance.append_only') }}</p><flux:button variant="primary" type="submit" icon="wrench-screwdriver">{{ __('ui.save') }}</flux:button> <flux:button href="{{ route('availability') }}">{{ __('maintenance.manage_blocks') }}</flux:button></form>
@endcan

<section><div class="mb-5 max-w-sm"><flux:select wire:model.live="vehicle_id" :label="__('maintenance.filter')"><option value="">{{ __('maintenance.all') }}</option>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->registration }}</option>@endforeach
</flux:select></div><div class="mb-5 flex flex-wrap items-center justify-between gap-4"><h2 class="text-xl font-semibold">{{ __('maintenance.history') }}</h2><x-directory.toggle/></div><div class="grid gap-5" :class="directoryView==='cards' ? 'lg:grid-cols-2' : 'grid-cols-1'">@forelse($records as $r)<article id="service-{{ $r->id }}" class="min-w-0 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900 sm:p-6"><div :class="directoryView==='list' ? 'lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:gap-x-8' : ''"><div class="min-w-0"><h3 class="flex flex-wrap items-center gap-2 font-semibold"><flux:icon name="wrench-screwdriver" class="size-5 text-zinc-400"/><a href="{{ route('vehicles.show',$r->vehicle_id) }}" class="text-teal-700 dark:text-teal-300">{{ $r->vehicle->registration }}</a> · {{ __('maintenance.'.$r->service_type) }} #{{ $r->id }}</h3></div><div class="min-w-0"><p class="mt-2">{{ $r->serviced_on->format('Y-m-d') }} · {{ $r->mileage_km }} km</p><p>{{ __('maintenance.next') }}: {{ $r->next_due_on?->format('Y-m-d') ?? '—' }} · {{ $r->next_due_km ?? '—' }} km</p>
@can('finance.manage')<p class="mt-2 font-medium">{{ __('maintenance.cost') }}: {{ \App\Modules\Rentals\Money::display($r->cost_cents) }}</p><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $r->notes }}</p><div class="mt-3 flex flex-wrap gap-3">@foreach($r->attachments as $attachment)<a class="text-sm underline" href="{{ route('maintenance-attachments.download',$attachment->id) }}">{{ __('maintenance.attachment') }} #{{ $attachment->id }}</a>@endforeach
</div>@endcan

@if($r->block)<p class="mt-3 text-sm">{{ __('maintenance.block') }} #{{ $r->block->id }} · {{ $r->block->released_at ? __('maintenance.released') : __('maintenance.open') }}</p>@can('maintenance.manage')@if(!$r->block->released_at)<flux:button class="mt-3" wire:click="release({{ $r->id }},{{ $r->block->version }})">{{ __('maintenance.release') }}</flux:button>@endif
@endcan
@endif
</div></div></article>@empty<p>{{ __('maintenance.no_records') }}</p>@endforelse
</div><div class="mt-5">{{ $records->links() }}</div></section>
@can('maintenance.manage')<div class="grid gap-5 lg:grid-cols-2"><form wire:submit="upload" class="space-y-4 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800"><h2 class="text-xl font-semibold">{{ __('maintenance.add_attachment') }}</h2><flux:select wire:model="attachmentRecord" :label="__('maintenance.record_id')"><option value="">—</option>@foreach($records as $record)<option value="{{ $record->id }}">{{ $record->vehicle->registration }} · {{ __('maintenance.'.$record->service_type) }} · {{ $record->serviced_on->format('Y-m-d') }} #{{ $record->id }}</option>@endforeach
</flux:select><flux:input wire:model="file" type="file" accept="application/pdf,image/jpeg,image/png" :label="__('maintenance.attachment')"/><flux:button type="submit">{{ __('ui.save') }}</flux:button></form><div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800"><flux:textarea wire:model="releaseReason" :label="__('maintenance.release_reason')" rows="2"/><p class="mt-3 text-sm">{{ __('maintenance.release_help') }}</p></div></div>@endcan

</div>
