<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use App\Services\AuditLogger;
use App\Services\QrCodeExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Découpage en lots de l'export des QR codes (affiché avant le téléchargement).
     */
    public function qrCodesPlan(Request $request, Event $event, QrCodeExporter $exporter): JsonResponse
    {
        [$type, $format, $batchSize] = $this->qrOptions($request, $event);

        $lots = collect($exporter->plan($event, $type, $format, $batchSize))->map(fn (array $lot) => [
            ...$lot,
            'url' => route('events.export.qrcodes', [$event, 'format' => $format, 'type' => $type?->id, 'batch_size' => $batchSize, 'lot' => $lot['lot']]),
        ]);

        return response()->json(['total' => $lots->sum('count'), 'lots' => $lots->values()]);
    }

    /**
     * Génère un lot de QR codes à la demande et l'envoie directement : le ZIP n'existe que
     * le temps de l'envoi (fichier temporaire supprimé ensuite).
     */
    public function qrCodes(Request $request, Event $event, QrCodeExporter $exporter): BinaryFileResponse
    {
        [$type, $format, $batchSize] = $this->qrOptions($request, $event);
        $lot = max(1, $request->integer('lot', 1));

        @set_time_limit(0);
        $zip = $exporter->build($event, $type, $format, $batchSize, $lot);
        abort_unless($zip, 404, 'Ce lot n\'existe pas.');

        app(AuditLogger::class)->record('export.qrcodes', "Export des QR codes ({$zip['count']} · ".strtoupper($format).')', $event, [
            'format' => $format,
            'type' => $type?->name,
            'lot' => $lot,
            'fichier' => $zip['name'],
        ]);

        return response()->download($zip['path'], $zip['name'])->deleteFileAfterSend();
    }

    /**
     * @return array{0: ?PassType, 1: string, 2: int}
     */
    private function qrOptions(Request $request, Event $event): array
    {
        $data = $request->validate([
            'format' => ['required', 'in:svg,png'],
            'type' => ['nullable', 'integer', Rule::exists('pass_types', 'id')->where('event_id', $event->id)],
            'batch_size' => ['nullable', 'integer', Rule::in(QrCodeExporter::BATCH_SIZES)],
            'lot' => ['nullable', 'integer', 'min:1'],
        ]);

        return [
            isset($data['type']) ? PassType::find($data['type']) : null,
            $data['format'],
            (int) ($data['batch_size'] ?? 500),
        ];
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
