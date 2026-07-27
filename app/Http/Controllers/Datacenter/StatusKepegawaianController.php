<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\StatusKepegawaian;
use Illuminate\Http\Request;

class StatusKepegawaianController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->input('q');
        $items = StatusKepegawaian::withCount('guru')
            ->when($q, fn ($x) => $x->where('nama_status', 'like', "%$q%"))
            ->orderBy('nama_status')
            ->paginate(15)->withQueryString();
        return view('datacenter.status-kepegawaian.index', compact('items', 'q'));
    }

    public function create() { return view('datacenter.status-kepegawaian.form', ['item' => new StatusKepegawaian()]); }

    public function store(Request $r)
    {
        StatusKepegawaian::create($this->v($r));
        return redirect()->route('status-kepegawaian.index')->with('success', 'Status kepegawaian ditambahkan.');
    }

    public function edit(StatusKepegawaian $statusKepegawaian)
    {
        return view('datacenter.status-kepegawaian.form', ['item' => $statusKepegawaian]);
    }

    public function update(Request $r, StatusKepegawaian $statusKepegawaian)
    {
        $data = $this->v($r, $statusKepegawaian->id);

        // Nama status dipakai sebagai nilai di guru.status_kepegawaian —
        // saat diganti, ikut perbarui semua guru agar tetap sinkron.
        if ($data['nama_status'] !== $statusKepegawaian->nama_status) {
            $statusKepegawaian->guru()->update(['status_kepegawaian' => $data['nama_status']]);
        }

        $statusKepegawaian->update($data);
        return redirect()->route('status-kepegawaian.index')->with('success', 'Status kepegawaian diperbarui.');
    }

    public function destroy(StatusKepegawaian $statusKepegawaian)
    {
        $dipakai = $statusKepegawaian->guru()->count();
        if ($dipakai > 0) {
            return back()->with('error', "Status \"{$statusKepegawaian->nama_status}\" masih dipakai {$dipakai} guru dan tidak bisa dihapus. Non-aktifkan saja jika tidak ingin dipakai lagi.");
        }
        $statusKepegawaian->delete();
        return back()->with('success', 'Status kepegawaian dihapus.');
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'nama_status' => 'required|string|max:50|unique:status_kepegawaian,nama_status,'.$id,
            'keterangan' => 'nullable|string|max:255',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true)];
    }
}
