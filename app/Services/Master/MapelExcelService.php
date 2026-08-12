<?php

namespace App\Services\Master;

use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Services\Master\Concerns\ResolvesTingkat;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / Export Mata Pelajaran (Excel).
 *
 * Kolom (baris 1):
 *   kode_mapel | nama_mapel | kelompok | tingkat | kode_jurusan | deskripsi | is_aktif
 *
 * - kode_mapel   : wajib. Kunci unik (upsert berdasarkan kolom ini).
 * - nama_mapel   : wajib.
 * - kelompok     : opsional, mis. Umum / Kejuruan / Muatan Lokal.
 * - tingkat      : opsional. Boleh angka (10), kode / romawi (X), atau nama tingkat
 *                  ("Kelas X (10)"). Kosong -> mapel berlaku untuk semua tingkat.
 * - kode_jurusan : opsional, kode jurusan (harus sudah terdaftar kalau diisi).
 * - deskripsi    : opsional.
 * - is_aktif     : opsional. Ya/Tidak, 1/0, Aktif/Non-aktif. Kosong -> Ya.
 */
class MapelExcelService
{
    use ResolvesTingkat;

    public const HEADERS = [
        'kode_mapel', 'nama_mapel', 'kelompok', 'tingkat', 'kode_jurusan', 'deskripsi', 'is_aktif',
    ];

    public function import(UploadedFile $file): ImportResult
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);
        $result = new ImportResult();

        if (count($data) < 2) return $result;

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));

        // Cache lookup biar cepat (natural key -> id)
        $jurusanCache = Jurusan::pluck('id', 'kode_jurusan');
        $tingkatCache = $this->tingkatLookup();

        foreach ($data as $i => $row) {
            $line = $i + 2;
            try {
                $assoc = [];
                foreach ($headers as $idx => $h) {
                    $assoc[$h] = $row[$idx] ?? null;
                }

                $kodeMapel   = trim((string) ($assoc['kode_mapel'] ?? ''));
                $namaMapel   = trim((string) ($assoc['nama_mapel'] ?? ''));
                $kelompok    = trim((string) ($assoc['kelompok'] ?? ''));
                $tingkat     = trim((string) ($assoc['tingkat'] ?? ''));
                $kodeJurusan = trim((string) ($assoc['kode_jurusan'] ?? ''));
                $deskripsi   = trim((string) ($assoc['deskripsi'] ?? ''));
                $isAktif     = trim((string) ($assoc['is_aktif'] ?? ''));

                if ($kodeMapel === '' || $namaMapel === '') {
                    throw new \RuntimeException('kode_mapel & nama_mapel wajib diisi');
                }

                // tingkat opsional: mapel bisa berlaku untuk semua tingkat
                $nomorTingkat = $tingkat !== '' ? $this->resolveTingkat($tingkat, $tingkatCache) : null;

                $jurusanId = null;
                if ($kodeJurusan !== '') {
                    $jurusanId = $jurusanCache[$kodeJurusan] ?? null;
                    if (! $jurusanId) throw new \RuntimeException("Jurusan kode '{$kodeJurusan}' tidak ditemukan");
                }

                MataPelajaran::updateOrCreate(
                    ['kode_mapel' => $kodeMapel],
                    [
                        'nama_mapel' => $namaMapel,
                        'kelompok'   => $kelompok !== '' ? $kelompok : null,
                        'tingkat'    => $nomorTingkat,
                        'jurusan_id' => $jurusanId,
                        'deskripsi'  => $deskripsi !== '' ? $deskripsi : null,
                        'is_aktif'   => $this->parseBool($isAktif, true),
                    ]
                );

                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = "Baris {$line}: ".$e->getMessage();
            }
        }

        return $result;
    }

    public function export(?\Illuminate\Database\Eloquent\Collection $items = null): StreamedResponse
    {
        $items ??= MataPelajaran::with('jurusan')
            ->orderBy('tingkat')->orderBy('nama_mapel')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mata Pelajaran');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $rows = $items->map(fn ($m) => [
            $m->kode_mapel,
            $m->nama_mapel,
            $m->kelompok,
            $m->tingkat,
            optional($m->jurusan)->kode_jurusan,
            $m->deskripsi,
            $m->is_aktif ? 'Ya' : 'Tidak',
        ])->toArray();

        $this->writeRowsAsText($sheet, $rows, 2);

        foreach (range('A', chr(64 + count(self::HEADERS))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->stream($spreadsheet, 'data-mapel-'.date('Ymd-His').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Mapel');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $this->writeRowsAsText($sheet, [
            ['MTK',    'Matematika',           'Umum',        '',   '',     '',                          'Ya'],
            ['BIND',   'Bahasa Indonesia',     'Umum',        '',   '',     '',                          'Ya'],
            ['PBO-XI', 'Pemrograman Berorientasi Objek', 'Kejuruan', 'XI', 'RPL', 'Mapel produktif RPL', 'Ya'],
        ], 2);

        foreach (range('A', chr(64 + count(self::HEADERS))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->stream($spreadsheet, 'template-import-mapel.xlsx');
    }

    /* ---------- helpers (sama seperti service Excel lainnya) ---------- */

    /** "Ya/Tidak", "Aktif/Non-aktif", "1/0", "true/false" -> boolean. */
    protected function parseBool(string $value, bool $default): bool
    {
        $v = strtolower(trim($value));
        if ($v === '') return $default;

        if (in_array($v, ['ya', 'y', 'yes', '1', 'true', 'aktif', 'a'], true)) return true;
        if (in_array($v, ['tidak', 't', 'no', 'n', '0', 'false', 'non-aktif', 'nonaktif', 'non aktif'], true)) return false;

        return $default;
    }

    protected function styleHeader($sheet, int $colCount): void
    {
        $range = 'A1:'.chr(64 + $colCount).'1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = chr(64 + $colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        for ($i = 1; $i <= $colCount; $i++) {
            $col = chr(64 + $i);
            $sheet->getStyle($col)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    protected function writeRowsAsText($sheet, array $rows, int $startRow = 2): void
    {
        foreach ($rows as $rIdx => $row) {
            $r = $startRow + $rIdx;
            $cIdx = 0;
            foreach ($row as $value) {
                $col = chr(65 + $cIdx);
                $sheet->setCellValueExplicit("{$col}{$r}", (string) ($value ?? ''), DataType::TYPE_STRING);
                $cIdx++;
            }
        }
    }

    protected function stream(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
