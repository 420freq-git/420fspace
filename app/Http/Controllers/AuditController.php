<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->latest('id');

        if ($request->filled('tipe')) {
            $query->where('auditable_type', $request->input('tipe'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }
        if ($cari = trim((string) $request->input('cari'))) {
            $query->where(function ($w) use ($cari) {
                $w->where('label', 'like', "%{$cari}%")->orWhere('user_name', 'like', "%{$cari}%");
            });
        }

        return view('audit.index', [
            'logs' => $query->paginate(40)->withQueryString(),
            'types' => AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
            'filters' => $request->only('tipe', 'event', 'cari'),
        ]);
    }
}
