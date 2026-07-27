<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder role/izin RBAC + akun panel admin.
 *
 * AMAN dijalankan di PRODUKSI tanpa menyentuh data sekolah/guru/siswa asli:
 *     php artisan db:seed --class=RbacSeeder --force
 *
 * Semua idempotent (firstOrCreate / updateOrCreate). Password awal "password"
 * — WAJIB diganti setelah login pertama di produksi.
 */
class RbacSeeder extends Seeder
{
    public function run(): void
    {
        // --- Roles ---
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin'],
            ['label' => 'Super Administrator', 'is_system' => true]);
        $admin      = Role::firstOrCreate(['name' => 'admin'],
            ['label' => 'Administrator', 'is_system' => true]);
        $operator   = Role::firstOrCreate(['name' => 'operator'],
            ['label' => 'Operator Data', 'is_system' => false]);

        // --- Permissions ---
        $permList = [
            ['dashboard/index', 'Lihat Dashboard', 'umum'],
            ['profil/index', 'Lihat Profil', 'umum'],
            ['profil/password', 'Ubah Password', 'umum'],
            ['sekolah/*', 'Kelola Profil Sekolah', 'datacenter'],
            ['tahun-ajaran/*', 'Kelola Tahun Ajaran', 'datacenter'],
            ['jurusan/*', 'Kelola Jurusan', 'datacenter'],
            ['mapel/*', 'Kelola Mapel', 'datacenter'],
            ['tingkat-kelas/*', 'Kelola Tingkat Kelas', 'datacenter'],
            ['rombel/*', 'Kelola Rombel', 'datacenter'],
            ['status-kepegawaian/*', 'Kelola Status Kepegawaian', 'datacenter'],
            ['guru/*', 'Kelola Guru', 'datacenter'],
            ['guru-mapel/*', 'Kelola Guru Mapel', 'datacenter'],
            ['siswa/*', 'Kelola Siswa', 'datacenter'],
            ['periodikal/*', 'Administrasi Periodikal (Kenaikan Kelas & Kelulusan)', 'datacenter'],
            ['pengaturan/*', 'Kelola Pengaturan (Halaman Login, dsb)', 'datacenter'],
        ];

        $allPermIds = [];
        $operatorPermIds = [];
        foreach ($permList as [$perm, $label, $group]) {
            $p = Permission::firstOrCreate(['permission' => $perm], ['label' => $label, 'group' => $group]);
            $allPermIds[] = $p->id;
            // operator: hanya datacenter (tanpa guru/sekolah/periodikal/pengaturan --
            // periodikal adalah operasi bulk yang mengubah data kelas/status ratusan
            // siswa sekaligus; pengaturan adalah wewenang admin, bukan operator data)
            if (in_array($group, ['umum', 'datacenter']) && !in_array($perm, ['sekolah/*', 'guru/*', 'periodikal/*', 'pengaturan/*'])) {
                $operatorPermIds[] = $p->id;
            }
        }
        $superAdmin->permissions()->sync($allPermIds);
        $admin->permissions()->sync($allPermIds);
        $operator->permissions()->sync($operatorPermIds);

        // --- Akun panel admin (3 role RBAC) ---
        // Semua pakai kolom string role='admin' supaya lolos middleware role:admin
        // (user_type = role). Yang membedakan hak akses adalah role_id (RBAC):
        //   - super-admin & admin : akses penuh semua modul Data Center.
        //   - operator            : datacenter tanpa Profil Sekolah, Guru, Periodikal.
        $accounts = [
            ['superadmin@gmail.com', 'Super Administrator', $superAdmin->id],
            ['admin@gmail.com',      'Administrator',       $admin->id],
            ['operator@gmail.com',   'Operator Data',       $operator->id],
        ];
        foreach ($accounts as [$email, $name, $roleId]) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name, 'password' => Hash::make('password'),
                    'role' => 'admin', 'role_id' => $roleId,
                    'account_status' => 'active', 'is_aktif' => true,
                ]
            );
        }
    }
}
