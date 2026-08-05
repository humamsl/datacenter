<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\StatusKepegawaian;
use App\Models\TahunAjaran;
use App\Services\Master\GuruExcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuruController extends Controller
{
    public function index(Request $r)
    {
        $items = Guru::when($r->q, function ($x) use ($r) {
                $x->where('nama_ptk', 'like', "%{$r->q}%")
                  ->orWhere('nip', 'like', "%{$r->q}%");
            })->orderBy('nama_ptk')->paginate(20)->withQueryString();
        return view('datacenter.guru.index', compact('items'));
    }

    public function create()
    {
        return view('datacenter.guru.form', [
            'item' => new Guru(),
            'statusOptions' => StatusKepegawaian::options(),
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(),
            'mapelTerpilih' => [],
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->v($r);
        $mapelIds = $this->vMapel($r);
        $data['password'] = $data['password'] ?? 'password'; // password default untuk guru baru
        $guru = Guru::create($data);
        $this->syncMapel($guru, $mapelIds);
        return redirect()->route('guru.index')->with('success', 'Data guru ditambahkan.');
    }

    public function edit(Guru $guru)
    {
        // Status lama milik guru tetap dimunculkan walau sudah non-aktif /
        // belum terdaftar di master, supaya nilai tidak hilang saat disimpan.
        $options = StatusKepegawaian::options();
        if ($guru->status_kepegawaian && ! isset($options[$guru->status_kepegawaian])) {
            $options[$guru->status_kepegawaian] = $guru->status_kepegawaian.' (non-aktif)';
        }

        return view('datacenter.guru.form', [
            'item' => $guru,
            'statusOptions' => $options,
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(),
            'mapelTerpilih' => $guru->guruMapel()->pluck('mata_pelajaran_id')->unique()->all(),
        ]);
    }

    public function update(Request $r, Guru $guru)
    {
        $data = $this->v($r, $guru->id);
        $mapelIds = $this->vMapel($r);
        if (empty($data['password'])) unset($data['password']);
        $guru->update($data);
        $this->syncMapel($guru, $mapelIds);
        return redirect()->route('guru.index')->with('success', 'Data guru diperbarui.');
    }

    public function destroy(Guru $guru)
    {
        $guru->delete();
        return back()->with('success', 'Data guru dihapus.');
    }

    /** Hapus banyak guru sekaligus (dipilih lewat checkbox di daftar). */
    public function bulkDestroy(Request $r)
    {
        $data = $r->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:guru,id',
        ]);

        $count = Guru::whereIn('id', $data['ids'])->count();
        Guru::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} data guru dihapus.");
    }

    /**
     * Buka kunci akun guru yang sedang terkunci (5x salah login berturut-turut).
     * Reset failed_login_count & locked_until supaya guru bisa login lagi
     * saat itu juga, tanpa perlu menunggu 15 menit habis.
     */
    public function unlock(Guru $guru)
    {
        $guru->update(['locked_until' => null, 'failed_login_count' => 0]);
        return back()->with('success', "Akun {$guru->nama_ptk} berhasil dibuka. Guru bisa login lagi sekarang.");
    }

    /* ===================== IMPORT / EXPORT ===================== */

    public function importForm()
    {
        return view('datacenter.guru.import');
    }

    public function importStore(Request $r, GuruExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        // File dengan banyak baris (hash password per guru baru) bisa melebihi
        // max_execution_time default dan berhenti mendadak (500) di tengah loop.
        set_time_limit(0);

        $result = $svc->import($r->file('file'));

        return redirect()->route('guru.import.form')
            ->with('success', "Import selesai: {$result->success} sukses, {$result->failed} gagal.")
            ->with('importErrors', $result->errors);
    }

    public function importTemplate(GuruExcelService $svc)
    {
        return $svc->template();
    }

    public function exportExcel(Request $r, GuruExcelService $svc)
    {
        $query = Guru::query();
        if ($r->q) {
            $query->where('nama_ptk', 'like', "%{$r->q}%")
                  ->orWhere('nip', 'like', "%{$r->q}%");
        }
        return $svc->export($query->orderBy('nama_ptk')->get());
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'nip' => 'required|string|max:30|unique:guru,nip,'.$id,
            'nama_ptk' => 'required|string|max:255',
            'email' => 'nullable|email|max:100',
            'nomor_hp' => 'nullable|string|max:20',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'alamat' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50|exists:status_kepegawaian,nama_status',
            'password' => 'nullable|string|min:6',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true)];
    }

    /**
     * Divalidasi terpisah dari v(), karena mapel bukan kolom tabel guru —
     * hasil v() langsung dipakai untuk create/update model Guru.
     */
    protected function vMapel(Request $r): array
    {
        return $r->validate([
            'mata_pelajaran_id' => 'nullable|array',
            'mata_pelajaran_id.*' => 'integer|exists:mata_pelajaran,id',
        ])['mata_pelajaran_id'] ?? [];
    }

    /**
     * Simpan pilihan mapel guru ke tabel guru_mapel untuk tahun ajaran aktif,
     * tanpa rombel (rombongan_belajar_id null). Baris yang sudah punya rombel
     * tidak disentuh — penugasan per rombel tetap dikelola di menu Guru Mapel.
     */
    protected function syncMapel(Guru $guru, array $mapelIds): void
    {
        $ta = TahunAjaran::aktif();
        if (! $ta) return;

        DB::transaction(function () use ($guru, $mapelIds, $ta) {
            $guru->guruMapel()
                ->where('tahun_ajaran_id', $ta->id)
                ->whereNull('rombongan_belajar_id')
                ->when($mapelIds, fn ($q) => $q->whereNotIn('mata_pelajaran_id', $mapelIds))
                ->delete();

            $sudahAda = $guru->guruMapel()->where('tahun_ajaran_id', $ta->id)
                ->pluck('mata_pelajaran_id')->unique()->all();

            foreach (array_diff($mapelIds, $sudahAda) as $id) {
                GuruMapel::create([
                    'guru_id' => $guru->id,
                    'mata_pelajaran_id' => $id,
                    'rombongan_belajar_id' => null,
                    'tahun_ajaran_id' => $ta->id,
                ]);
            }
        });
    }
}
