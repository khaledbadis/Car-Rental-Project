@props(['kind','categories','drivers','customerType'=>'individual'])
<div class="grid gap-5 sm:grid-cols-2">
@foreach(config('catalog.'.$kind) as $field=>$type)
@continue($kind==='customer' && (($customerType==='company' && in_array($field,['birth_date','identity_type','identity_number','identity_issue_date','identity_expiry_date','licence_number','licence_issue_date','licence_expiry_date','licence_country'])) || ($customerType!=='company' && in_array($field,['contact_person','tax_identifier','driver_id']))))
<div class="{{ $type==='textarea'?'sm:col-span-2':'' }}">
@if($type==='textarea')<flux:textarea wire:model="form.{{ $field }}" :label="__('catalog.'.$field)" rows="3" />
@elseif(in_array($type,['category','driver']) || isset(config('catalog.options')[$type]))
<flux:select wire:model.live="form.{{ $field }}" :label="__('catalog.'.$field)"><option value="">—</option>
@if($type==='category')@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
@elseif($type==='driver')@foreach($drivers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
@else @foreach(config('catalog.options.'.$type) as $option)<option value="{{ $option }}">{{ __('catalog.'.$option) }}</option>@endforeach @endif</flux:select>
@else <flux:input wire:model.blur="form.{{ $field }}" type="{{ $type==='money'?'number':$type }}" :step="$type==='money'?'0.01':null" :label="__('catalog.'.$field)" />@endif
</div>@endforeach
</div>
