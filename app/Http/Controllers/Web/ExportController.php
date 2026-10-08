<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Pass;
use App\Services\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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

        return response()
            ->download($path, sprintf('pass-%s-%s.%s', Str::slug($event->code), now()->format('Ymd-His'), $format))
            ->deleteFileAfterSend();
    }

    /**
     * Archive ZIP des QR codes pour l'équipe design : un fichier par pass, nommé
     * par son numéro, plus un CSV de fusion (colonne @qr compatible InDesign).
     */
    public function qrcodes(Request $request, Event $event): BinaryFileResponse
    {
        $request->validate([
            'format' => ['nullable', 'in:svg,png'],
            'type' => ['nullable', 'integer'],
        ]);

        @set_time_limit(0);

        $format = $request->format ?? 'svg';
        $path = tempnam(sys_get_temp_dir(), 'qr');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['numero', 'type', 'code_type', 'lien', '@qr']);

        $event->passes()
            ->with('type')
            ->when($request->type, fn ($query, $type) => $query->where('pass_type_id', $type))
            ->orderBy('pass_type_id')
            ->orderBy('sequence')
            ->lazy(500)
            ->each(function (Pass $pass) use ($zip, $csv, $format) {
                $file = "{$pass->type->code}/{$pass->number}.{$format}";
                $content = $format === 'png' ? QrCode::png($pass->url()) : QrCode::svg($pass->url());

                $zip->addFromString($file, $content);
                fputcsv($csv, [$pass->number, $pass->type->name, $pass->type->code, $pass->url(), $file]);
            });

        rewind($csv);
        $zip->addFromString('passes.csv', "\xEF\xBB\xBF".stream_get_contents($csv));
        fclose($csv);
        $zip->close();

        return response()
            ->download($path, sprintf('qrcodes-%s-%s.zip', Str::slug($event->code), $format))
            ->deleteFileAfterSend();
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
