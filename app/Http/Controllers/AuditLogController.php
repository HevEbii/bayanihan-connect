<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->string('filter')->toString() === 'denied', fn ($q) => $q->where('action', 'access.denied'))
            ->when($request->string('filter')->toString() === 'changes', fn ($q) => $q->whereNotIn('action', ['access.denied', 'auth.login', 'auth.logout']))
            ->latest('created_at')->latest('id')
            ->paginate(25)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
