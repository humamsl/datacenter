@extends('layouts.app')
@section('title', 'Reset Data')
@section('breadcrumb', 'Data Center / Administrasi / Reset Data')

@section('content')
<x-page-header title="Reset Data" subtitle="Hapus permanen data induk — siswa, penugasan guru mapel, guru, dan rombel"/>

<div class="card card-pad mb-6 text-sm text-rose-700" style="border:1px solid #fecdd3;background:#fff1f2;">
    <strong>Perhatian:</strong> Tindakan di halaman ini menghapus data secara permanen beserta data terkaitnya
    (penempatan rombel, riwayat kenaikan kelas, penugasan guru mapel) dan <u>tidak bisa dibatalkan</u>.
    Pastikan sudah export/backup data terlebih dahulu sebelum melanjutkan.
</div>

<div x-data="{ tab: '{{ request('tab', 'tingkat') }}' }" class="space-y-6">

    <div class="flex gap-1 bg-white rounded-xl p-1 shadow-soft border border-slate-100 w-fit overflow-x-auto max-w-full">
        @foreach([
            'tingkat'    => 'Siswa: Per Tingkat',
            'rombel'     => 'Siswa: Per Rombel',
            'siswa'      => 'Siswa: Per Siswa',
            'semua'      => 'Siswa: Semua',
            'guru-mapel' => 'Guru Mapel',
            'guru'       => 'Guru',
            'data-rombel'=> 'Rombel',
        ] as $key => $label)
            <button type="button" @click="tab='{{ $key }}'"
                    :class="tab==='{{ $key }}' ? 'bg-brand-600 text-white shadow-soft' : 'text-ink-600 hover:bg-slate-100'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition whitespace-nowrap">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ============ TAB SISWA PER TINGKAT KELAS ============ --}}
    <div x-show="tab==='tingkat'" x-cloak class="space-y-4">
        <form method="GET" action="{{ route('reset-data.index') }}" class="card card-pad flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="tingkat">
            <div class="min-w-[260px]">
                <label class="label">Tingkat Kelas</label>
                <select name="tingkat" class="select" onchange="this.form.submit()">
                    <option value="">— Pilih Tingkat Kelas —</option>
                    @foreach($tingkatList as $t)
                        <option value="{{ $t->nomor }}" @selected(request('tingkat') == $t->nomor)>{{ $t->nama }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-xs text-ink-500">Cakupan: siswa yang saat ini ditempatkan di rombel tingkat ini pada tahun ajaran aktif
                @if($tahunAktif) ({{ $tahunAktif->nama_tahun_ajaran }}) @endif.</p>
        </form>

        @if(!is_null($siswaTingkat))
            @if($siswaTingkat->isEmpty())
                <div class="card card-pad text-center py-8 text-ink-500">Tidak ada siswa pada tingkat kelas ini.</div>
            @else
                <div class="card">
                    <table class="table-modern">
                        <thead><tr><th>NISN</th><th>Nama Siswa</th><th>Rombel</th></tr></thead>
                        <tbody>
                        @foreach($siswaTingkat as $s)
                            <tr>
                                <td class="font-mono text-xs">{{ $s->nisn }}</td>
                                <td class="font-semibold text-ink-900">{{ $s->nama_siswa }}</td>
                                <td>{{ optional($s->rombelSekarang?->rombel ?? null)->nama_rombel ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('reset-data.per-tingkat') }}"
                      x-data="{ konfirmasi: '' }"
                      class="card card-pad flex flex-wrap items-end gap-3 bg-rose-50/60">
                    @csrf
                    <input type="hidden" name="tingkat" value="{{ request('tingkat') }}">
                    <div class="min-w-[260px]">
                        <label class="label text-rose-700">Ketik <strong>HAPUS</strong> untuk menghapus {{ $siswaTingkat->count() }} data siswa ini</label>
                        <input type="text" name="konfirmasi" x-model="konfirmasi" class="input" autocomplete="off">
                    </div>
                    <button type="submit" class="btn-danger" :disabled="konfirmasi !== 'HAPUS'"
                            :class="konfirmasi !== 'HAPUS' ? 'opacity-50 cursor-not-allowed' : ''">
                        <x-icon name="trash" class="w-4 h-4"/> Hapus {{ $siswaTingkat->count() }} Siswa
                    </button>
                </form>
            @endif
        @endif
    </div>

    {{-- ============ TAB SISWA PER ROMBEL ============ --}}
    <div x-show="tab==='rombel'" x-cloak class="space-y-4">
        <form method="GET" action="{{ route('reset-data.index') }}" class="card card-pad flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="rombel">
            <div class="min-w-[260px]">
                <label class="label">Rombel</label>
                <select name="rombel" class="select" onchange="this.form.submit()">
                    <option value="">— Pilih Rombel —</option>
                    @foreach($rombelList as $rb)
                        <option value="{{ $rb->id }}" @selected(request('rombel') == $rb->id)>{{ $rb->nama_rombel }} (Tingkat {{ $rb->tingkat }})</option>
                    @endforeach
                </select>
            </div>
        </form>

        @if(!is_null($siswaRombel))
            @if($siswaRombel->isEmpty())
                <div class="card card-pad text-center py-8 text-ink-500">Tidak ada siswa pada rombel ini.</div>
            @else
                <div class="card">
                    <table class="table-modern">
                        <thead><tr><th>NISN</th><th>Nama Siswa</th></tr></thead>
                        <tbody>
                        @foreach($siswaRombel as $s)
                            <tr>
                                <td class="font-mono text-xs">{{ $s->nisn }}</td>
                                <td class="font-semibold text-ink-900">{{ $s->nama_siswa }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <form method="POST" action="{{ route('reset-data.per-rombel') }}"
                      x-data="{ konfirmasi: '' }"
                      class="card card-pad flex flex-wrap items-end gap-3 bg-rose-50/60">
                    @csrf
                    <input type="hidden" name="rombel" value="{{ request('rombel') }}">
                    <div class="min-w-[260px]">
                        <label class="label text-rose-700">Ketik <strong>HAPUS</strong> untuk menghapus {{ $siswaRombel->count() }} data siswa ini</label>
                        <input type="text" name="konfirmasi" x-model="konfirmasi" class="input" autocomplete="off">
                    </div>
                    <button type="submit" class="btn-danger" :disabled="konfirmasi !== 'HAPUS'"
                            :class="konfirmasi !== 'HAPUS' ? 'opacity-50 cursor-not-allowed' : ''">
                        <x-icon name="trash" class="w-4 h-4"/> Hapus {{ $siswaRombel->count() }} Siswa
                    </button>
                </form>
            @endif
        @endif
    </div>

    {{-- ============ TAB PER SISWA ============ --}}
    <div x-show="tab==='siswa'" x-cloak class="space-y-4">
        <form method="GET" action="{{ route('reset-data.index') }}" class="card card-pad flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="siswa">
            <input name="q" value="{{ request('q') }}" class="input flex-1 min-w-[220px]" placeholder="Cari nama, NISN, atau NIS...">
            <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/> Cari</button>
        </form>

        @if(!is_null($siswaHasil))
            @if($siswaHasil->isEmpty())
                <div class="card card-pad text-center py-8 text-ink-500">Tidak ditemukan siswa yang cocok.</div>
            @else
                <div class="card">
                    <table class="table-modern">
                        <thead><tr><th>NISN</th><th>Nama Siswa</th><th>Rombel</th><th></th></tr></thead>
                        <tbody>
                        @foreach($siswaHasil as $s)
                            <tr>
                                <td class="font-mono text-xs">{{ $s->nisn }}</td>
                                <td class="font-semibold text-ink-900">{{ $s->nama_siswa }}</td>
                                <td>{{ optional($s->rombelSekarang?->rombel ?? null)->nama_rombel ?? '—' }}</td>
                                <td class="text-right">
                                    <button type="button" class="btn-ghost p-2 text-rose-600" title="Hapus data siswa ini"
                                            onclick="resetSatuSiswa({{ $s->id }}, '{{ addslashes($s->nama_siswa) }}')">
                                        <x-icon name="trash"/>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>

    {{-- ============ TAB SEMUA SISWA ============ --}}
    <div x-show="tab==='semua'" x-cloak class="space-y-4">
        <div class="card card-pad text-center py-6">
            <p class="text-sm text-ink-500">Total data siswa saat ini</p>
            <p class="text-4xl font-bold text-ink-900">{{ $totalSiswa }}</p>
        </div>

        @if($totalSiswa > 0)
            <form method="POST" action="{{ route('reset-data.semua') }}"
                  x-data="{ konfirmasi: '' }"
                  class="card card-pad flex flex-wrap items-end gap-3 bg-rose-50/60">
                @csrf
                <div class="min-w-[320px]">
                    <label class="label text-rose-700">Ketik <strong>HAPUS SEMUA</strong> untuk menghapus seluruh {{ $totalSiswa }} data siswa</label>
                    <input type="text" name="konfirmasi" x-model="konfirmasi" class="input" autocomplete="off">
                </div>
                <button type="submit" class="btn-danger" :disabled="konfirmasi !== 'HAPUS SEMUA'"
                        :class="konfirmasi !== 'HAPUS SEMUA' ? 'opacity-50 cursor-not-allowed' : ''">
                    <x-icon name="trash" class="w-4 h-4"/> Hapus Semua ({{ $totalSiswa }}) Siswa
                </button>
            </form>
        @endif
    </div>

    {{-- ============ TAB GURU MAPEL ============ --}}
    <div x-show="tab==='guru-mapel'" x-cloak class="space-y-4"
         x-data="{ ta: '', counts: @js($gmPerTa), total: {{ $totalGuruMapel }} }">
        <div class="card card-pad text-center py-6">
            <p class="text-sm text-ink-500">Penugasan guru mapel yang akan dihapus</p>
            <p class="text-4xl font-bold text-ink-900" x-text="ta ? (counts[ta] ?? 0) : total"></p>
            <p class="text-xs text-ink-500 mt-1" x-text="ta ? 'pada tahun ajaran terpilih' : 'seluruh tahun ajaran'"></p>
        </div>

        <form method="POST" action="{{ route('reset-data.guru-mapel') }}"
              x-data="{ konfirmasi: '' }"
              class="card card-pad flex flex-wrap items-end gap-3 bg-rose-50/60">
            @csrf
            <div class="min-w-[240px]">
                <label class="label">Batasi Tahun Ajaran (opsional)</label>
                <select name="tahun_ajaran_id" class="select" x-model="ta">
                    <option value="">Semua tahun ajaran</option>
                    @foreach($taList as $t)
                        <option value="{{ $t->id }}">{{ $t->nama_tahun_ajaran }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[240px]">
                <label class="label text-rose-700">Ketik <strong>HAPUS</strong> untuk konfirmasi</label>
                <input type="text" name="konfirmasi" x-model="konfirmasi" class="input" autocomplete="off">
            </div>
            <button type="submit" class="btn-danger"
                    :disabled="konfirmasi !== 'HAPUS' || (ta ? (counts[ta] ?? 0) : total) === 0"
                    :class="(konfirmasi !== 'HAPUS' || (ta ? (counts[ta] ?? 0) : total) === 0) ? 'opacity-50 cursor-not-allowed' : ''">
                <x-icon name="trash" class="w-4 h-4"/> Hapus <span x-text="ta ? (counts[ta] ?? 0) : total"></span> Penugasan
            </button>
        </form>
        <p class="text-xs text-ink-500">Hanya menghapus data penugasan (guru mengajar mapel &amp; rombel apa) — data induk guru, mapel, dan rombelnya tetap ada.</p>
    </div>

    {{-- ============ TAB GURU ============ --}}
    <div x-show="tab==='guru'" x-cloak class="space-y-4">
        <div class="card card-pad text-center py-6">
            <p class="text-sm text-ink-500">Total data guru saat ini</p>
            <p class="text-4xl font-bold text-ink-900">{{ $totalGuru }}</p>
        </div>

        @if($totalGuru > 0)
            <form method="POST" action="{{ route('reset-data.guru') }}"
                  x-data="{ konfirmasi: '' }"
                  class="card card-pad flex flex-wrap items-end gap-3 bg-rose-50/60">
                @csrf
                <div class="min-w-[320px]">
                    <label class="label text-rose-700">Ketik <strong>HAPUS SEMUA</strong> untuk menghapus seluruh {{ $totalGuru }} data guru</label>
                    <input type="text" name="konfirmasi" x-model="konfirmasi" class="input" autocomplete="off">
                </div>
                <button type="submit" class="btn-danger" :disabled="konfirmasi !== 'HAPUS SEMUA'"
                        :class="konfirmasi !== 'HAPUS SEMUA' ? 'opacity-50 cursor-not-allowed' : ''">
                    <x-icon name="trash" class="w-4 h-4"/> Hapus Semua ({{ $totalGuru }}) Guru
                </button>
            </form>
            <p class="text-xs text-ink-500">Seluruh akun guru terhapus permanen — penugasan guru mapelnya ikut terhapus dan wali kelas pada rombel dikosongkan. Guru tidak lagi bisa login ke aplikasi (termasuk CBT).</p>
        @endif
    </div>

    {{-- ============ TAB ROMBEL ============ --}}
    <div x-show="tab==='data-rombel'" x-cloak class="space-y-4"
         x-data="{ ta: '', counts: @js($rbPerTa), total: {{ $totalRombel }} }">
        <div class="card card-pad text-center py-6">
            <p class="text-sm text-ink-500">Rombel yang akan dihapus</p>
            <p class="text-4xl font-bold text-ink-900" x-text="ta ? (counts[ta] ?? 0) : total"></p>
            <p class="text-xs text-ink-500 mt-1" x-text="ta ? 'pada tahun ajaran terpilih' : 'seluruh tahun ajaran'"></p>
        </div>

        <form method="POST" action="{{ route('reset-data.rombel') }}"
              x-data="{ konfirmasi: '' }"
              class="card card-pad flex flex-wrap items-end gap-3 bg-rose-50/60">
            @csrf
            <div class="min-w-[240px]">
                <label class="label">Batasi Tahun Ajaran (opsional)</label>
                <select name="tahun_ajaran_id" class="select" x-model="ta">
                    <option value="">Semua tahun ajaran</option>
                    @foreach($taList as $t)
                        <option value="{{ $t->id }}">{{ $t->nama_tahun_ajaran }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[240px]">
                <label class="label text-rose-700">Ketik <strong>HAPUS</strong> untuk konfirmasi</label>
                <input type="text" name="konfirmasi" x-model="konfirmasi" class="input" autocomplete="off">
            </div>
            <button type="submit" class="btn-danger"
                    :disabled="konfirmasi !== 'HAPUS' || (ta ? (counts[ta] ?? 0) : total) === 0"
                    :class="(konfirmasi !== 'HAPUS' || (ta ? (counts[ta] ?? 0) : total) === 0) ? 'opacity-50 cursor-not-allowed' : ''">
                <x-icon name="trash" class="w-4 h-4"/> Hapus <span x-text="ta ? (counts[ta] ?? 0) : total"></span> Rombel
            </button>
        </form>
        <p class="text-xs text-ink-500">Menghapus rombel ikut menghapus penempatan siswa di rombel tsb (data induk siswanya tetap ada) dan penugasan guru mapel rombel tsb. Rombel asal/tujuan pada riwayat periodikal dikosongkan.</p>
    </div>

</div>

{{-- Form tersembunyi utk hapus per-siswa, dipicu via resetSatuSiswa() setelah konfirmasi ketik "HAPUS" --}}
<form id="form-reset-per-siswa" method="POST" action="{{ route('reset-data.per-siswa') }}" class="hidden">
    @csrf
    <input type="hidden" name="siswa_id" id="reset-per-siswa-id">
    <input type="hidden" name="konfirmasi" id="reset-per-siswa-konfirmasi">
</form>
@endsection

@push('scripts')
<script>
function resetSatuSiswa(id, nama) {
    var k = prompt('Ketik HAPUS untuk menghapus permanen data siswa "' + nama + '":');
    if (k !== 'HAPUS') return;
    document.getElementById('reset-per-siswa-id').value = id;
    document.getElementById('reset-per-siswa-konfirmasi').value = k;
    document.getElementById('form-reset-per-siswa').submit();
}
</script>
@endpush
