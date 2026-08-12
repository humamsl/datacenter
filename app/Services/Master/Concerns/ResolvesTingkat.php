<?php

namespace App\Services\Master\Concerns;

use App\Models\TingkatKelas;

/**
 * Penerjemah kolom "tingkat" pada file Excel → nomor tingkat (angka).
 *
 * Kolom tingkat di DB disimpan sebagai angka (mengikuti tingkat_kelas.nomor),
 * sementara operator sering mengisi file dengan romawi ("X") atau nama tingkat
 * ("Kelas X / 10"). Tanpa penerjemahan, cast langsung ke int menghasilkan 0.
 */
trait ResolvesTingkat
{
    /**
     * Peta pencarian tingkat: kode / nama / nomor (ternormalisasi) => nomor.
     */
    protected function tingkatLookup(): array
    {
        $map = [];
        foreach (TingkatKelas::orderBy('urutan')->orderBy('nomor')->get() as $t) {
            foreach ([$t->kode, $t->nama, (string) $t->nomor] as $key) {
                $k = $this->normalizeTingkat((string) $key);
                if ($k !== '' && ! isset($map[$k])) {
                    $map[$k] = (int) $t->nomor;
                }
            }
        }

        return $map;
    }

    protected function normalizeTingkat(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);

        // Sel angka kadang terbaca "10.0" / "10,0"
        if (preg_match('/^(\d+)[.,]0+$/', $value, $m)) {
            $value = $m[1];
        }

        return $value;
    }

    /** Ubah isi kolom "tingkat" jadi nomor tingkat. Lempar error kalau tak dikenali. */
    protected function resolveTingkat(string $raw, array $map): int
    {
        $key = $this->normalizeTingkat($raw);
        if (isset($map[$key])) {
            return $map[$key];
        }

        // "Kelas X" / "Kelas 10"
        $key = preg_replace('/^kelas\s+/', '', $key);
        if (isset($map[$key])) {
            return $map[$key];
        }

        if (($nomor = $this->tingkatDariToken($key, $map)) !== null) {
            return $nomor;
        }

        // Nama tingkat yang lebih panjang: "Kelas X (10)", "Kelas X / 10", "Tingkat XI".
        // Ambil potongan pertama yang bisa diterjemahkan.
        foreach (preg_split('/[^a-z0-9]+/', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (in_array($token, ['kelas', 'tingkat'], true)) {
                continue;
            }
            if (($nomor = $this->tingkatDariToken($token, $map)) !== null) {
                return $nomor;
            }
        }

        throw new \RuntimeException("Tingkat '{$raw}' tidak dikenali — isi dengan angka (mis. 10) atau kode tingkat (mis. X)");
    }

    /** Terjemahkan satu potongan kata: lewat master, angka polos, atau romawi. */
    protected function tingkatDariToken(string $token, array $map): ?int
    {
        if (isset($map[$token])) {
            return $map[$token];
        }

        // Fallback kalau master Tingkat Kelas belum lengkap
        if (preg_match('/^\d+$/', $token)) {
            $nomor = (int) $token;
            return ($nomor >= 1 && $nomor <= 13) ? $nomor : null;
        }

        $romawi = [
            'i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7,
            'viii' => 8, 'ix' => 9, 'x' => 10, 'xi' => 11, 'xii' => 12, 'xiii' => 13,
        ];

        return $romawi[$token] ?? null;
    }
}
