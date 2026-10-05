<?php

namespace App\Http\Controllers;

use App\Modules\Rentals\Money;
use App\Modules\Reporting\Expenses;
use App\Modules\Reporting\Report;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function expenses(Request $r, Expenses $a)
    {
        return $a->query($r->user(), $r->all())->paginate(20);
    }

    public function create(Request $r, Expenses $a)
    {
        return response()->json(['data' => $a->post($r->user(), $r->all())], 201);
    }

    public function reverse(Request $r, Expenses $a, int $id)
    {
        return response()->json(['data' => $a->reverse($r->user(), $id, $r->all())], 201);
    }

    public function upload(Request $r, Expenses $a, int $id)
    {
        return response()->json(['data' => $a->upload($r->user(), $id, $r->all())], 201);
    }

    public function download(Request $r, Expenses $a, int $id)
    {
        return $a->download($r->user(), $id);
    }

    public function report(Request $r, Report $a)
    {
        return ['data' => $a->read($r->user(), $r->all())];
    }

    public function export(Request $r, Report $a)
    {
        $data = $a->read($r->user(), $r->all());

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $keys = ['registration', 'rental_count', 'rented_hours', 'eligible_hours', 'utilization', 'utilization_status', 'revenue', 'collections', 'expenses', 'maintenance_cost', 'contribution'];
            fputcsv($out, ['from', 'to', ...$keys], ',', '"', '');
            foreach ($data['vehicles'] as $row) {
                $cells = [$data['from'], $data['to']];
                foreach ($keys as $k) {
                    $value = $row[$k];
                    if (in_array($k, ['revenue', 'collections', 'expenses', 'maintenance_cost', 'contribution'])) {
                        $value = Money::decimal($value);
                    }if (is_string($value) && preg_match('/^[\s]*[=+@-]/u', $value) && ! is_numeric($value)) {
                        $value = "'".$value;
                    }$cells[] = $value;
                }fputcsv($out, $cells, ',', '"', '');
            }fputcsv($out, [$data['from'], $data['to'], 'UNALLOCATED', 0, '', '', '', '', Money::decimal($data['unallocated']['revenue']), Money::decimal($data['unallocated']['collections']), '0.00', '0.00', Money::decimal($data['unallocated']['revenue'])], ',', '"', '');
            fclose($out);
        }, 'vehicle-report-'.$data['from'].'-'.$data['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
