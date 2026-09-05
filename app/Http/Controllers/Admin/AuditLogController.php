<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SRS 6.18 - the audit trail. The controller is read-only: FR-171 requires that
 * audit records cannot be edited, and the model refuses updates and deletes.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when($request->string('action')->toString(), fn ($q, $a) => $q->where('action', 'like', "%{$a}%"))
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->date('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'users' => User::query()->orderBy('name')->get(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
