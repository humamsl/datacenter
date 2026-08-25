<?php

namespace App\Services\Master;

use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / Export data siswa via Excel (.xlsx).
 *
 * Header kolom (urut, baris 1):
 *   nisn | nis | nama_siswa | jenis_kelamin | tempat_lahir | tanggal_lahir |
 *   agama | alamat | nomor_hp | email | nama_ayah | nama_ibu | nomor_hp_ortu |
 *   rombel | password | is_aktif
 *
 * - jenis_kelamin: L atau P
 * - tanggal_lahir: YYYY-MM-DD
 * - rombel: nama rombel pada Tahun Ajaran aktif (mis. "X IPA 1"). Akan di-cari & dipasang otomatis.
 * - password: opsional. Default untuk siswa baru = 123456.
 * - is_aktif: 1 / 0 / kosong (default 1)
 */
class SiswaExcelService
{
    public const HEADERS = [
        'nisn','nis','nama_siswa','jenis_kelamin','tempat_lahir','tanggal_lahir',
        'agama','alamat','nomor_hp','email','nama_ayah','nama_ibu','nomor_hp_ortu',
        'rombel','password','is_aktif',
    ];

    public function import(UploadedFile $file): ImportResult
    {
        $result = new ImportResult();

        try {
            $path = $file->getRealPath();
            // Baca nilai mentah saja — styling & number format tidak perlu untuk
            // import, dan mem-parse-nya adalah bagian termahal dari membuka xlsx
            // dengan ribuan baris.
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $data  = $sheet->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet, $reader);
        } catch (\Throwable $e) {
            $result->failed++;
            $result->errors[] = 'Gagal membaca file Excel: '.$e->getMessage();
            return $result;
        }

        if (count($data) < 2) return $result;

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));

        $ta = TahunAjaran::aktif();
        $rombelMap = $ta
            ? RombonganBelajar::where('tahun_ajaran_id', $ta->id)->pluck('id', 'nama_rombel')->toArray()
            : [];

        // Petakan dulu semua baris ke bentuk assoc supaya NISN-nya bisa dikumpulkan
        // dan siswa yang sudah ada diambil sekaligus di bawah — bukan satu SELECT
        // per baris seperti sebelumnya.
        $rows = [];
        foreach ($data as $i => $row) {
            $assoc = [];
            foreach ($headers as $idx => $h) {
                $assoc[$h] = $row[$idx] ?? null;
            }
            if (empty($assoc['nisn']) || empty($assoc['nama_siswa'])) continue;
            $assoc['nisn'] = trim((string) $assoc['nisn']);
            $rows[$i] = $assoc;
        }
        unset($data);

        // Array biasa, bukan Collection::merge() — merge() mengindeks ulang key
        // numerik, dan NISN hampir selalu numerik.
        $existing = [];
        foreach (array_chunk(array_values(array_unique(array_column($rows, 'nisn'))), 1000) as $chunk) {
            foreach (Siswa::whereIn('nisn', $chunk)->get() as $s) {
                $existing[$s->nisn] = $s;
            }
        }

        // Rombel yang sudah terpasang di TA aktif, supaya updateOrCreate hanya
        // dijalankan untuk siswa yang kelasnya benar-benar berubah.
        $rombelSiswaMap = $ta
            ? SiswaRombel::where('tahun_ajaran_id', $ta->id)->pluck('rombongan_belajar_id', 'siswa_id')->toArray()
            : [];

        foreach ($rows as $i => $assoc) {
            try {
                $payload = [
                    'nis'           => $assoc['nis'] ?: null,
                    'nama_siswa'    => trim((string) $assoc['nama_siswa']),
                    'jenis_kelamin' => in_array(strtoupper((string) $assoc['jenis_kelamin']), ['L','P'], true)
                                          ? strtoupper($assoc['jenis_kelamin']) : null,
                    'tempat_lahir'  => $assoc['tempat_lahir'] ?: null,
                    'tanggal_lahir' => $this->parseDate($assoc['tanggal_lahir'] ?? null),
                    'agama'         => $assoc['agama'] ?: null,
                    'alamat'        => $assoc['alamat'] ?: null,
                    'nomor_hp'      => $assoc['nomor_hp'] ?: null,
                    'email'         => $assoc['email'] ?: null,
                    'nama_ayah'     => $assoc['nama_ayah'] ?: null,
                    'nama_ibu'      => $assoc['nama_ibu'] ?: null,
                    'nomor_hp_ortu' => $assoc['nomor_hp_ortu'] ?: null,
                    'is_aktif'      => $this->parseBool($assoc['is_aktif'] ?? 1, true),
                ];

                // Password di-hash oleh cast 'hashed' pada model, jadi cukup diisi
                // apa adanya. Baris tanpa kolom password pada siswa yang sudah ada
                // tidak menyentuh hashing sama sekali.
                $pwd = trim((string) ($assoc['password'] ?? ''));
                if ($pwd !== '') {
                    $payload['password'] = $pwd;
                }

                // Nama rombel divalidasi sebelum menyentuh DB supaya baris yang
                // salah nama kelas tidak sempat menulis apa pun.
                $rombelName = trim((string) ($assoc['rombel'] ?? ''));
                $rombelId = null;
                if ($rombelName !== '' && $ta) {
                    $rombelId = $rombelMap[$rombelName] ?? null;
                    if (! $rombelId) {
                        throw new \RuntimeException("Rombel '{$rombelName}' tidak ditemukan di TA aktif.");
                    }
                }

                $siswa = $existing[$assoc['nisn']] ?? null;

                $siswa = DB::transaction(function () use ($siswa, $assoc, $payload, $rombelId, $ta, &$rombelSiswaMap) {
                    if ($siswa) {
                        $siswa->update($payload);
                    } else {
                        // Kolom password kosong pada siswa baru -> pakai password default (123456).
                        $payload['password'] = $payload['password'] ?? Siswa::DEFAULT_PASSWORD;
                        $payload['nisn'] = $assoc['nisn'];
                        $siswa = Siswa::create($payload);
                    }

                    if ($rombelId && ($rombelSiswaMap[$siswa->id] ?? null) !== $rombelId) {
                        SiswaRombel::updateOrCreate(
                            ['siswa_id' => $siswa->id, 'tahun_ajaran_id' => $ta->id],
                            ['rombongan_belajar_id' => $rombelId]
                        );
                        $rombelSiswaMap[$siswa->id] = $rombelId;
                    }

                    return $siswa;
                });

                // Kalau NISN yang sama muncul dua kali dalam satu file, kemunculan
                // kedua harus meng-update baris yang barusan dibuat, bukan bikin baru.
                $existing[$assoc['nisn']] = $siswa;

                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = 'Baris '.($i + 2).': '.$e->getMessage();
            }
        }
        return $result;
    }

    public function export(?\Illuminate\Database\Eloquent\Collection $siswa = null): StreamedResponse
    {
        $siswa ??= Siswa::with('rombelSekarang.rombel')->orderBy('nama_siswa')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Siswa');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $rows = $siswa->map(fn ($s) => [
            $s->nisn, $s->nis, $s->nama_siswa, $s->jenis_kelamin,
            $s->tempat_lahir, optional($s->tanggal_lahir)->format('Y-m-d'),
            $s->agama, $s->alamat, $s->nomor_hp, $s->email,
            $s->nama_ayah, $s->nama_ibu, $s->nomor_hp_ortu,
            optional($s->rombelSekarang?->rombel ?? null)->nama_rombel,
            '', // password kosong
            $s->is_aktif ? '1' : '0',
        ])->toArray();

        $this->writeRowsAsText($sheet, $rows, 2);

        $this->autoSize($sheet, count(self::HEADERS));
        return $this->stream($spreadsheet, 'data-siswa-'.date('Ymd-His').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Siswa');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $this->writeRowsAsText($sheet, [
            ['009900000001','NIS0001','Ahmad Fauzi','L','Jakarta','2009-05-12','Islam','Jl. Anggrek 1','081200000001','ahmad@test','Budi','Siti','081200000099','7-1','','1'],
            ['009900000002','NIS0002','Bunga Citra','P','Bekasi','2009-07-22','Islam','Jl. Mawar 2','081200000002','bunga@test','Hasan','Aminah','081200000098','X IPA 2','','1'],
        ], 2);

        $this->autoSize($sheet, count(self::HEADERS));
        return $this->stream($spreadsheet, 'template-import-siswa.xlsx');
    }

    /*    helpers    */
    protected function styleHeader($sheet, int $colCount): void
    {
        $lastCol = $this->colLetter($colCount);
        $range = "A1:{$lastCol}1";
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function autoSize($sheet, int $colCount): void
    {
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension($this->colLetter($i))->setAutoSize(true);
        }
    }

    /** Paksa kolom data jadi format TEXT supaya Excel tidak auto-cast (NIS, "7-1", nomor HP). */
    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = $this->colLetter($colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        for ($i = 1; $i <= $colCount; $i++) {
            $col = $this->colLetter($i);
            $sheet->getStyle($col)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    /** Tulis baris dengan tipe STRING eksplisit, anti auto-cast. */
    protected function writeRowsAsText($sheet, array $rows, int $startRow = 2): void
    {
        foreach ($rows as $rIdx => $row) {
            $r = $startRow + $rIdx;
            $cIdx = 0;
            foreach ($row as $value) {
                $col = $this->colLetter($cIdx + 1);
                $sheet->setCellValueExplicit("{$col}{$r}", (string) ($value ?? ''), DataType::TYPE_STRING);
                $cIdx++;
            }
        }
    }

    protected function colLetter(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $mod = ($n - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $n = intdiv($n - $mod, 26);
        }
        return $letter;
    }

    protected function stream(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function parseDate($value): ?string
    {
        if (! $value) return null;
        try {
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseBool($v, bool $default = true): bool
    {
        if ($v === null || $v === '') return $default;
        if (is_bool($v)) return $v;
        $v = strtolower((string) $v);
        return in_array($v, ['1','y','yes','true','aktif','active'], true);
    }
}
