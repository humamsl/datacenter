<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modul master Status Kepegawaian:
 *  1. Buat tabel status_kepegawaian.
 *  2. Seed status baku (PNS, PPPK, GTT, Honorer) + deteksi nilai
 *     status_kepegawaian yang sudah terlanjur ada di tabel guru
 *     (hasil input manual / import lama) supaya langsung ter-registrasi.
 *  3. Daftarkan permission 'status-kepegawaian/*' ke role super-admin,
 *     admin, dan operator — mengikuti pola migration
 *     2026_07_02_000001_grant_periodikal_permission.php. Operator ikut
 *     diberi akses karena ini master data setara jurusan/tingkat kelas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_kepegawaian', function (Blueprint $table) {
            $table->id();
            $table->string('nama_status', 50)->unique();
            $table->string('keterangan')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });

        $this->seedStatus();
        $this->grantPermission();
    }

    public function down(): void
    {
        if ($this->tableExists('permissions')) {
            $permId = DB::table('permissions')->where('permission', 'status-kepegawaian/*')->value('id');
            if ($permId) {
                DB::table('role_permissions')->where('permission_id', $permId)->delete();
                DB::table('permissions')->where('id', $permId)->delete();
            }
        }

        Schema::dropIfExists('status_kepegawaian');
    }

    protected function seedStatus(): void
    {
        $baku = [
            'PNS'     => 'Pegawai Negeri Sipil',
            'PPPK'    => 'Pegawai Pemerintah dengan Perjanjian Kerja',
            'GTT'     => 'Guru Tidak Tetap',
            'Honorer' => 'Guru / pegawai honorer',
        ];
        foreach ($baku as $nama => $ket) {
            DB::table('status_kepegawaian')->insert([
                'nama_status' => $nama, 'keterangan' => $ket,
                'is_aktif' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! $this->tableExists('guru')) return;

        // Deteksi status yang sudah dipakai guru tapi belum ada di daftar baku
        // (perbandingan case-insensitive supaya "pns" tidak dobel dengan "PNS").
        $bakuLower = array_map('strtolower', array_keys($baku));
        $existing = DB::table('guru')
            ->whereNotNull('status_kepegawaian')->where('status_kepegawaian', '!=', '')
            ->distinct()->pluck('status_kepegawaian');

        foreach ($existing as $nama) {
            $nama = trim($nama);
            if ($nama === '' || in_array(strtolower($nama), $bakuLower, true)) continue;
            $bakuLower[] = strtolower($nama);
            DB::table('status_kepegawaian')->insert([
                'nama_status' => $nama,
                'keterangan' => 'Terdeteksi dari data guru yang sudah ada',
                'is_aktif' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function grantPermission(): void
    {
        if (! $this->tableExists('roles') || ! $this->tableExists('permissions') || ! $this->tableExists('role_permissions')) {
            return;
        }

        $permission = DB::table('permissions')->where('permission', 'status-kepegawaian/*')->first();
        $permId = $permission->id ?? DB::table('permissions')->insertGetId([
            'permission' => 'status-kepegawaian/*',
            'label' => 'Kelola Status Kepegawaian',
            'group' => 'datacenter',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'super-admin', 'operator'])
            ->pluck('id')->toArray();

        foreach ($roleIds as $roleId) {
            $already = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permId)
                ->exists();
            if (! $already) {
                DB::table('role_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $permId,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
};
