<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Pass;
use App\Services\QrCode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

/**
 * Prépare le ZIP des QR codes d'un événement (un fichier par pass + passes.csv pour la
 * fusion dans le gabarit) en mettant à jour la progression au fil de l'eau.
 */
class GenerateQrCodesExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public Export $export) {}

    public function handle(): void
    {
        $this->pruneOldExports();

        $export = $this->export->fresh();
        $passes = $export->event->passes()
            ->with('type')
            ->when($export->pass_type_id, fn ($query, $type) => $query->where('pass_type_id', $type))
            ->orderBy('pass_type_id')
            ->orderBy('sequence');

        $export->update(['status' => 'processing', 'total' => $passes->count(), 'processed' => 0]);

        $disk = Storage::disk('local');
        $path = "exports/qrcodes-{$export->id}-".Str::random(8).'.zip';
        $disk->makeDirectory('exports');

        $zip = new ZipArchive;
        $zip->open($disk->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['numero', 'type', 'code_type', 'lien', '@qr']);

        $processed = 0;
        $passes->lazy(200)->each(function (Pass $pass) use ($zip, $csv, $export, &$processed) {
            $file = "{$pass->type->code}/{$pass->number}.{$export->format}";
            $zip->addFromString($file, $export->format === 'png' ? QrCode::png($pass->url()) : QrCode::svg($pass->url()));
            fputcsv($csv, [$pass->number, $pass->type->name, $pass->type->code, $pass->url(), $file]);

            if (++$processed % 50 === 0) {
                $export->update(['processed' => $processed]);
            }
        });

        rewind($csv);
        $zip->addFromString('passes.csv', "\xEF\xBB\xBF".stream_get_contents($csv));
        fclose($csv);
        $zip->close();

        $export->update([
            'status' => 'done',
            'processed' => $processed,
            'file_path' => $path,
            'file_name' => sprintf('qrcodes-%s-%s.zip', Str::slug($export->event->code), $export->format),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->export->update(['status' => 'failed', 'error' => 'La génération a échoué. Réessayez ou contactez l\'administrateur.']);
    }

    /**
     * Les fichiers ne sont conservés que 24 h.
     */
    private function pruneOldExports(): void
    {
        Export::where('created_at', '<', now()->subHours(Export::RETENTION_HOURS))->get()->each(function (Export $old) {
            if ($old->file_path) {
                Storage::disk('local')->delete($old->file_path);
            }
            $old->delete();
        });
    }
}
