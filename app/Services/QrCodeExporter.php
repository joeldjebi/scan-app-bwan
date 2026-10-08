<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Pass;
use App\Models\PassType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Exporte les QR codes d'un événement par lots, générés à la demande : chaque lot est un ZIP
 * (un fichier par pass + passes.csv pour la fusion dans le gabarit) créé dans un fichier
 * temporaire, envoyé au navigateur puis supprimé. Aucun QR code n'est conservé sur le serveur.
 */
class QrCodeExporter
{
    /** Tailles de lot proposées (0 = un seul fichier). */
    public const BATCH_SIZES = [250, 500, 1000, 2000, 0];

    /**
     * Découpage en lots : numéros du premier et du dernier pass de chaque lot.
     *
     * @return list<array{lot: int, count: int, from: string, to: string, name: string}>
     */
    public function plan(Event $event, ?PassType $type, string $format, int $batchSize): array
    {
        $numbers = $this->passes($event, $type)->pluck('number');
        $chunks = $numbers->chunk($batchSize > 0 ? $batchSize : max(1, $numbers->count()));

        return $chunks->values()->map(fn ($chunk, $index) => [
            'lot' => $index + 1,
            'count' => $chunk->count(),
            'from' => $chunk->first(),
            'to' => $chunk->last(),
            'name' => $this->fileName($event, $type, $format, $batchSize, $index + 1, $chunk->count(), $numbers->count()),
        ])->all();
    }

    /**
     * Construit le ZIP d'un lot dans un fichier temporaire.
     *
     * @return array{path: string, name: string, count: int}|null null si le lot n'existe pas
     */
    public function build(Event $event, ?PassType $type, string $format, int $batchSize, int $lot): ?array
    {
        $total = $this->passes($event, $type)->count();
        $size = $batchSize > 0 ? $batchSize : max(1, $total);

        $passes = $this->passes($event, $type)->with('type')->skip(($lot - 1) * $size)->take($size)->get();
        if ($passes->isEmpty()) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'qr');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['numero', 'type', 'code_type', 'lien', '@qr']);

        foreach ($passes as $pass) {
            /** @var Pass $pass */
            $file = "{$pass->type->code}/{$pass->number}.{$format}";
            $zip->addFromString($file, $format === 'png' ? QrCode::png($pass->url()) : QrCode::svg($pass->url()));
            fputcsv($csv, [$pass->number, $pass->type->name, $pass->type->code, $pass->url(), $file]);
        }

        rewind($csv);
        $zip->addFromString('passes.csv', "\xEF\xBB\xBF".stream_get_contents($csv));
        fclose($csv);
        $zip->close();

        return [
            'path' => $path,
            'name' => $this->fileName($event, $type, $format, $batchSize, $lot, $passes->count(), $total),
            'count' => $passes->count(),
        ];
    }

    /**
     * @return Builder<Pass>
     */
    private function passes(Event $event, ?PassType $type): Builder
    {
        return Pass::query()
            ->where('event_id', $event->id)
            ->when($type, fn ($query) => $query->where('pass_type_id', $type->id))
            ->orderBy('pass_type_id')
            ->orderBy('sequence');
    }

    /**
     * Ex. qrcodes-asf26-vip-png-lot-02-0501-1000.zip (sans « lot » pour un seul fichier).
     */
    private function fileName(Event $event, ?PassType $type, string $format, int $batchSize, int $lot, int $count, int $total): string
    {
        $base = sprintf('qrcodes-%s-%s-%s', Str::slug($event->code), Str::slug($type?->code ?? 'tous'), $format);

        if ($batchSize === 0 || $total <= $batchSize) {
            return "{$base}.zip";
        }

        $width = strlen((string) $total);
        $first = ($lot - 1) * $batchSize + 1;

        return sprintf('%s-lot-%02d-%s-%s.zip', $base, $lot, str_pad($first, $width, '0', STR_PAD_LEFT), str_pad($first + $count - 1, $width, '0', STR_PAD_LEFT));
    }
}
