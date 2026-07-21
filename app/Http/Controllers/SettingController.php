<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const DEFAULTS = [
        'monitor_hari' => 12,
        'cek_ulang_hari' => 2,
        'mepet_hari' => 3,
    ];

    public function index()
    {
        return view('settings.index', [
            'monitor_hari' => Setting::intVal('monitor_hari', self::DEFAULTS['monitor_hari']),
            'cek_ulang_hari' => Setting::intVal('cek_ulang_hari', self::DEFAULTS['cek_ulang_hari']),
            'mepet_hari' => Setting::intVal('mepet_hari', self::DEFAULTS['mepet_hari']),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'monitor_hari' => ['required', 'integer', 'min:1', 'max:90'],
            'cek_ulang_hari' => ['required', 'integer', 'min:1', 'max:30'],
            'mepet_hari' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        foreach ($data as $key => $value) {
            Setting::put($key, $value);
        }

        return back()->with('success', 'Pengaturan monitoring disimpan.');
    }
}
