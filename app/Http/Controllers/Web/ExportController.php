<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateQrCodesExport;
use App\Models\Event;
use App\Models\Export;
use App\Models\Pass;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /**
     * Liste des pass en Excel ou CSV (respecte les filtres de la liste).
     */
    public function passes(Request $request, Event $event): BinaryFileResponse
    {
        $format = $request->format === 'csv' ? 'csv' : 'xlsx';
        $path = tempnam(sys_get_temp_dir(), 'pass').'.'.$format;
        $writer = SimpleExcelWriter::create($path);

        PassController::filtered($request, $event)
            ->with(['type', 'vehicle'])
            ->orderBy('pass_type_id')
            ->orderBy('sequence')
            ->lazy(1000)
            ->each(fn (Pass $pass) => $writer->addRow($this->row($pass)));

        $writer->close();
        app(AuditLogger::class)->record('export.passes', 'Export de la liste des pass ('.strtoupper($format).')', $event, ['format' => $format, 'filtres' => array_filter($request->only('q', 'type', 'status', 'presence'))]);

        return response()
            ->download($path, sprintf('pass-%s-%s.%s', Str::slug($event->code), now()->format('Ymd-His'), $format))
            ->deleteFileAfterSend();
    }

    /**
     * Lance la préparation du ZIP des QR codes en arrière-plan (un fichier par pass, nommé
     * par son numéro, plus un CSV de fusion « @qr » compatible InDesign).
     */
    public function startQrCodes(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:svg,png'],
            'type' => ['nullable', 'integer', Rule::exists('pass_types', 'id')->where('event_id', $event->id)],
        ]);

        $export = Export::create([
            'user_id' => $request->user()->id,
            'event_id' => $event->id,
            'pass_type_id' => $data['type'] ?? null,
            'format' => $data['format'],
            'total' => $event->passes()->when($data['type'] ?? null, fn ($query, $type) => $query->where('pass_type_id', $type))->count(),
        ]);

        app(AuditLogger::class)->record('export.qrcodes', 'Export des QR codes ('.strtoupper($export->format).')', $event, [
            'format' => $export->format,
            'type_id' => $export->pass_type_id,
            'nombre' => $export->total,
        ]);

        GenerateQrCodesExport::dispatch($export);

        return response()->json($export->fresh()->toProgress(), 202);
    }

    public function show(Request $request, Export $export): JsonResponse
    {
        $this->ensureOwner($request, $export);

        return response()->json($export->toProgress());
    }

    public function download(Request $request, Export $export): BinaryFileResponse
    {
        $this->ensureOwner($request, $export);
        abort_unless($export->status === 'done' && Storage::disk('local')->exists($export->file_path), 404, 'Ce fichier a expiré : relancez l\'export.');

        return response()->download(Storage::disk('local')->path($export->file_path), $export->file_name);
    }

    private function ensureOwner(Request $request, Export $export): void
    {
        abort_unless($export->user_id === $request->user()->id || $request->user()->isOwner(), 403);
    }

    private function row(Pass $pass): array
    {
        return [
            'Numéro' => $pass->number,
            'Type' => $pass->type->name,
            'Lien' => $pass->url(),
            'Statut' => $pass->status->label(),
            'Immatriculation' => $pass->vehicle?->plate,
            'Marque' => $pass->vehicle?->brand,
            'Couleur' => $pass->vehicle?->color,
            'Téléphone' => $pass->vehicle?->phone,
            'Enregistré le' => $pass->registered_at?->format('d/m/Y H:i'),
            'Position' => $pass->presence->value === 'in' ? 'Dans le parking' : 'Dehors',
            'Dernier passage' => $pass->last_scanned_at?->format('d/m/Y H:i'),
        ];
    }
}
