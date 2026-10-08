<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Journal d'audit, consultable uniquement par le propriétaire de la plateforme.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = $this->filtered($request)
            ->with(['user', 'event'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => User::whereIn('id', AuditLog::select('user_id'))->orderBy('name')->get(['id', 'name']),
            'events' => Event::orderByDesc('starts_at')->get(['id', 'name']),
            'securityAlerts' => AuditLog::whereIn('action', ['auth.login_failed', 'auth.blocked', 'access.denied', 'access.throttled'])
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'audit').'.csv';
        $writer = SimpleExcelWriter::create($path);

        $this->filtered($request)->latest('id')->lazy(1000)->each(fn (AuditLog $log) => $writer->addRow([
            'Date' => $log->created_at->format('d/m/Y H:i:s'),
            'Auteur' => $log->actor_name ?? '—',
            'Rôle' => $log->actor_role ?? '—',
            'Action' => $log->action,
            'Description' => $log->description,
            'Objet' => trim(($log->subject_type ?? '').' '.($log->subject_label ?? '')),
            'Canal' => AuditLog::CHANNELS[$log->channel] ?? $log->channel,
            'IP' => $log->ip_address,
            'Appareil' => $log->device,
            'Navigateur' => $log->user_agent,
            'URL' => $log->method ? $log->method.' '.$log->url : '',
            'Modifications' => $log->changes ? json_encode($log->changes, JSON_UNESCAPED_UNICODE) : '',
            'Détails' => $log->properties ? json_encode($log->properties, JSON_UNESCAPED_UNICODE) : '',
        ]));

        $writer->close();

        return response()->download($path, 'journal-'.now()->format('Ymd-His').'.csv')->deleteFileAfterSend();
    }

    /**
     * @return Builder<AuditLog>
     */
    private function filtered(Request $request): Builder
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user' => ['nullable', 'integer'],
            'event' => ['nullable', 'integer'],
        ]);

        return AuditLog::query()
            ->when($request->category, fn ($query, $category) => $query->where('action', 'like', $category.'.%'))
            ->when($request->channel, fn ($query, $channel) => $query->where('channel', $channel))
            ->when($request->user, fn ($query, $user) => $query->where('user_id', $user))
            ->when($request->event, fn ($query, $event) => $query->where('event_id', $event))
            ->when($request->boolean('security'), fn ($query) => $query->whereIn('action', ['auth.login_failed', 'auth.blocked', 'access.denied', 'access.throttled']))
            ->when($request->from, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->when($request->q, fn ($query, $q) => $query->where(fn ($sub) => $sub
                ->where('description', 'like', "%{$q}%")
                ->orWhere('subject_label', 'like', "%{$q}%")
                ->orWhere('actor_name', 'like', "%{$q}%")
                ->orWhere('ip_address', 'like', "%{$q}%")));
    }
}
