@extends('print.layout')
@section('title',$receipt->number)
@section('document')
@php($s=$receipt->snapshot)
@php($e=$s['entry'])
@if($s['sample'])<p class="sample">MODÈLE / <span lang="ar" dir="rtl">نموذج</span></p>@endif
<header><h1><bdi>{{ $receipt->number }}</bdi></h1><strong>{{ $s['agency']['name'] }}</strong><p class="muted">{{ $s['agency']['address'] }} · {{ $s['agency']['phone'] }}<br>{{ $s['agency']['identifier'] }}</p></header>
<h2>{{ __('rental.'.$e['kind'],[], 'fr') }} / <span dir="rtl" lang="ar">{{ __('rental.'.$e['kind'],[], 'ar') }}</span></h2>
@if($e['kind']==='reversal')<p class="sample">RECTIFICATION — PAS UN REMBOURSEMENT<br><span lang="ar" dir="rtl">تصحيح قيد — ليس استرداد أموال</span></p><p>Écriture corrigée / القيد المصحح: <bdi>{{ $s['corrects']??'#'.$e['reverses_id'] }}</bdi></p>@endif
<table><tbody>
<tr><th>Client / العميل</th><td>{{ $s['customer']['name'] }}</td></tr>
<tr><th>Montant / المبلغ</th><td><bdi>{{ \App\Modules\Rentals\Money::display($e['amount_cents']) }}</bdi></td></tr>
<tr><th>Date effective / تاريخ العملية</th><td><bdi>{{ \Carbon\CarbonImmutable::parse($e['effective_at'])->setTimezone('Africa/Algiers')->format('d/m/Y H:i') }}</bdi></td></tr>
<tr><th>Mode / الطريقة</th><td>{{ $e['method']?__('rental.'.$e['method'],[],'fr').' / '.__('rental.'.$e['method'],[],'ar'):'—' }}</td></tr>
<tr><th>Référence / المرجع</th><td>{{ $e['reference']??'—' }}</td></tr>
<tr><th>Enregistré par / سجل بواسطة</th><td><bdi>{{ $s['actor_username'] }}</bdi></td></tr>
<tr><th>Location / Réservation — التأجير / الحجز</th><td><bdi>{{ $s['rental_id']?'L'.$s['rental_id']:'R'.$s['reservation_id'] }}</bdi></td></tr>
</tbody></table><p class="terms">{{ $e['reason'] }}</p><p class="muted">Émis / تاريخ الإصدار: <bdi>{{ $receipt->created_at->setTimezone('Africa/Algiers')->format('d/m/Y H:i') }}</bdi><br>Réimpression : même numéro et mêmes données / إعادة الطباعة بنفس الرقم والبيانات</p>
@endsection
