<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TingkatKelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Modul ultra-sensitif: hapus permanen data induk (siswa, penugasan guru
 * mapel, guru, rombel). Siswa bisa dihapus per tingkat / per rombel /
 * per siswa / semuanya; guru mapel & rombel bisa dibatasi per tahun ajaran.
 * Dilindungi middleware `admin` (bukan sekadar role:admin) & wajib mengetik
 * frasa konfirmasi ("HAPUS" / "HAPUS SEMUA") sebelum dieksekusi.
 *
 * Efek berantai (foreign key di level database):
 *  - hapus siswa  → siswa_rombel & riwayat_periodikal siswa ikut terhapus;
 *  - hapus guru   → penugasan guru_mapel ikut terhapus, wali kelas rombel di-null-kan;
 *  - hapus rombel → siswa_rombel & guru_mapel rombel tsb ikut terhapus,
 *                   rombel asal/tujuan di riwayat_periodikal di-null-kan.
 */
class ResetDataController extends Controller
{
    public function index(Request $r)
    {
        $tingkatList = TingkatKelas::aktif()->orderBy('urutan')->orderBy('nomor')->get();
        $rombelList = RombonganBelajar::whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->orderBy('tingkat')->orderBy('nama_rombel')->get();
        $tahunAktif = TahunAjaran::where('is_aktif', true)->first();

        $siswaTingkat = null;
        if ($r->filled('tingkat')) {
            $siswaTingkat = $this->siswaByTingkat($r->integer('tingkat'))->orderBy('nama_siswa')->get();
        }

        $siswaRombel = null;
        if ($r->filled('rombel')) {
            $siswaRombel = $this->siswaByRombel($r->integer('rombel'))->orderBy('nama_siswa')->get();
        }

        $siswaHasil = null;
        if ($r->filled('q')) {
            $siswaHasil = Siswa::with('rombelSekarang.rombel')
                ->where(function ($x) use ($r) {
                    $x->where('nama_siswa', 'like', "%{$r->q}%")
                      ->orWhere('nisn', 'like', "%{$r->q}%")
                      ->orWhere('nis', 'like', "%{$r->q}%");
                })
                ->orderBy('nama_siswa')->limit(25)->get();
        }

        return view('datacenter.reset-data.index', [
            'tingkatList'    => $tingkatList,
            'rombelList'     => $rombelList,
            'tahunAktif'     => $tahunAktif,
            'siswaTingkat'   => $siswaTingkat,
            'siswaRombel'    => $siswaRombel,
            'siswaHasil'     => $siswaHasil,
            'totalSiswa'     => Siswa::count(),
            'taList'         => TahunAjaran::orderByDesc('id')->get(),
            // Total & rincian per tahun ajaran — dipakai Alpine di tab
            // Guru Mapel / Rombel untuk menampilkan jumlah tanpa reload.
            'totalGuruMapel' => GuruMapel::count(),
            'gmPerTa'        => GuruMapel::selectRaw('tahun_ajaran_id, COUNT(*) as c')
                                    ->groupBy('tahun_ajaran_id')->pluck('c', 'tahun_ajaran_id'),
            'totalGuru'      => Guru::count(),
            'totalRombel'    => RombonganBelajar::count(),
            'rbPerTa'        => RombonganBelajar::selectRaw('tahun_ajaran_id, COUNT(*) as c')
                                    ->groupBy('tahun_ajaran_id')->pluck('c', 'tahun_ajaran_id'),
        ]);
    }

    /* ===================== SISWA ===================== */

    public function perTingkat(Request $r)
    {
        $data = $r->validate([
            'tingkat' => 'required|integer',
            'konfirmasi' => 'required|in:HAPUS',
        ]);

        $count = DB::transaction(function () use ($data) {
            $ids = $this->siswaByTingkat($data['tingkat'])->pluck('siswa.id');
            Siswa::whereIn('id', $ids)->delete();
            return $ids->count();
        });

        return redirect()->route('reset-data.index')
            ->with('success', "{$count} data siswa pada tingkat kelas ini berhasil dihapus permanen.");
    }

    public function perRombel(Request $r)
    {
        $data = $r->validate([
            'rombel' => 'required|integer|exists:rombongan_belajar,id',
            'konfirmasi' => 'required|in:HAPUS',
        ]);

        $count = DB::transaction(function () use ($data) {
            $ids = $this->siswaByRombel($data['rombel'])->pluck('siswa.id');
            Siswa::whereIn('id', $ids)->delete();
            return $ids->count();
        });

        return redirect()->route('reset-data.index')
            ->with('success', "{$count} data siswa pada rombel ini berhasil dihapus permanen.");
    }

    public function perSiswa(Request $r)
    {
        $data = $r->validate([
            'siswa_id' => 'required|integer|exists:siswa,id',
            'konfirmasi' => 'required|in:HAPUS',
        ]);

        $siswa = Siswa::findOrFail($data['siswa_id']);
        $nama = $siswa->nama_siswa;
        $siswa->delete();

        return redirect()->route('reset-data.index')
            ->with('success', "Data siswa {$nama} berhasil dihapus permanen.");
    }

    /** Hapus SELURUH data induk siswa. Frasa konfirmasi sengaja dibuat beda & lebih panjang dari cakupan lain. */
    public function semua(Request $r)
    {
        $r->validate(['konfirmasi' => 'required|in:HAPUS SEMUA']);

        $count = DB::transaction(function () {
            $count = Siswa::count();
            Siswa::query()->delete();
            return $count;
        });

        return redirect()->route('reset-data.index')
            ->with('success', "Seluruh data siswa ({$count} siswa) berhasil dihapus permanen.");
    }

    /* ===================== GURU MAPEL / GURU / ROMBEL ===================== */

    /** Hapus penugasan guru mapel — semuanya, atau dibatasi satu tahun ajaran. */
    public function guruMapel(Request $r)
    {
        $data = $r->validate([
            'tahun_ajaran_id' => 'nullable|integer|exists:tahun_ajaran,id',
            'konfirmasi' => 'required|in:HAPUS',
        ]);

        $count = DB::transaction(function () use ($data) {
            $q = GuruMapel::query()
                ->when($data['tahun_ajaran_id'] ?? null, fn ($x, $ta) => $x->where('tahun_ajaran_id', $ta));
            $count = $q->count();
            $q->delete();
            return $count;
        });

        return redirect()->route('reset-data.index')
            ->with('success', "{$count} data penugasan guru mapel berhasil dihapus permanen.");
    }

    /** Hapus SELURUH data induk guru (penugasan guru mapel ikut terhapus). */
    public function guru(Request $r)
    {
        $r->validate(['konfirmasi' => 'required|in:HAPUS SEMUA']);

        $count = DB::transaction(function () {
            $count = Guru::count();
            Guru::query()->delete();
            return $count;
        });

        return redirect()->route('reset-data.index')
            ->with('success', "Seluruh data guru ({$count} guru) berhasil dihapus permanen.");
    }

    /** Hapus rombel — semuanya, atau dibatasi satu tahun ajaran. */
    public function rombel(Request $r)
    {
        $data = $r->validate([
            'tahun_ajaran_id' => 'nullable|integer|exists:tahun_ajaran,id',
            'konfirmasi' => 'required|in:HAPUS',
        ]);

        $count = DB::transaction(function () use ($data) {
            $q = RombonganBelajar::query()
                ->when($data['tahun_ajaran_id'] ?? null, fn ($x, $ta) => $x->where('tahun_ajaran_id', $ta));
            $count = $q->count();
            $q->delete();
            return $count;
        });

        return redirect()->route('reset-data.index')
            ->with('success', "{$count} data rombel berhasil dihapus permanen (beserta penempatan siswa & penugasan guru mapel rombel tsb).");
    }

    /* ===================== HELPERS ===================== */

    /** Siswa yg penempatan rombelnya (tahun ajaran aktif) berada di tingkat tertentu. */
    protected function siswaByTingkat(int $tingkat)
    {
        return Siswa::query()
            ->whereHas('siswaRombel', function ($q) use ($tingkat) {
                $q->whereHas('tahunAjaran', fn ($qa) => $qa->where('is_aktif', true))
                  ->whereHas('rombel', fn ($qr) => $qr->where('tingkat', $tingkat));
            });
    }

    /** Siswa yg saat ini tercatat di rombel tertentu (lintas tahun ajaran rombel itu). */
    protected function siswaByRombel(int $rombelId)
    {
        return Siswa::query()
            ->whereHas('siswaRombel', fn ($q) => $q->where('rombongan_belajar_id', $rombelId));
    }
}
