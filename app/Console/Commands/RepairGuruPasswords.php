<?php

namespace App\Console\Commands;

use App\Models\Guru;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Diagnosa & perbaiki akun guru yang kena bug import Excel: kolom password
 * ter-hash dari STRING KOSONG, bukan dari kata "password" -- lihat perbaikan
 * di GuruExcelService::import() (baris 108, sebelumnya `$assoc['password']
 * ?? 'password'` tidak fallback saat selnya '' bukan null, karena file
 * Export/Template menulis kolom password sebagai string kosong eksplisit).
 *
 * Akun begini punya hash yang KELIHATAN valid (format bcrypt benar) tapi
 * tidak pernah cocok dengan "password" apa pun yang diketik -- beda dengan
 * password yang benar-benar belum di-hash sama sekali.
 *
 * Command ini HANYA menyentuh akun yang terbukti kena bug ini (dites dengan
 * Hash::check('', ...)) -- guru yang sudah pernah ganti password sendiri ke
 * nilai lain TIDAK disentuh sama sekali.
 */
class RepairGuruPasswords extends Command
{
    protected $signature = 'guru:repair-passwords {--fix : Terapkan perbaikan (tanpa flag ini hanya menampilkan daftar akun bermasalah)}';

    protected $description = 'Cari & perbaiki akun guru yang kena bug import (password ter-hash dari string kosong) sehingga tidak bisa login pakai password default';

    public function handle(): int
    {
        $apply = (bool) $this->option('fix');

        $broken = Guru::all()->filter(function (Guru $g) {
            $raw = $g->getRawOriginal('password');
            if (blank($raw)) return true; // password memang belum pernah diisi sama sekali
            if (! Hash::isHashed($raw)) return true; // tersimpan sebagai teks biasa (bukan hash)
            return Hash::check('', $raw); // kena bug: hash dari string kosong
        });

        if ($broken->isEmpty()) {
            $this->info('Tidak ada akun guru yang kena bug ini. Tidak ada yang perlu diperbaiki.');
            return self::SUCCESS;
        }

        $this->warn("{$broken->count()} akun guru ditemukan bermasalah (password default 'password' pasti gagal login):");
        $this->table(['NIP', 'Nama'], $broken->map(fn (Guru $g) => [$g->nip, $g->nama_ptk]));

        if (! $apply) {
            $this->line('');
            $this->line("Ini baru daftar diagnosa -- belum ada yang diubah. Jalankan lagi dengan --fix untuk mereset password akun-akun di atas ke 'password'.");
            return self::SUCCESS;
        }

        foreach ($broken as $g) {
            $g->forceFill(['password' => Hash::make('password')])->save();
        }

        $this->info("{$broken->count()} akun guru direset ke password default 'password'. Sampaikan ke guru terkait untuk login lalu segera ganti password mereka sendiri.");
        return self::SUCCESS;
    }
}
