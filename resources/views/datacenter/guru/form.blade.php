@extends('layouts.app')
@section('title', $item->exists ? 'Edit Guru' : 'Tambah Guru')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Data Guru' : 'Tambah Data Guru'"/>
<form method="POST" action="{{ $item->exists ? route('guru.update', $item) : route('guru.store') }}" class="card card-pad space-y-4 max-w-4xl">
    @csrf @if($item->exists) @method('PUT') @endif

    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="nip" label="NIP" :value="$item->nip" required/>
        <x-field name="nama_ptk" label="Nama Lengkap" :value="$item->nama_ptk" required/>
        <x-field name="email" type="email" label="Email" :value="$item->email"/>
        <x-field name="nomor_hp" label="Nomor HP" :value="$item->nomor_hp"/>
        <x-field type="select" name="jenis_kelamin" label="Jenis Kelamin" :value="$item->jenis_kelamin"
                 :options="['L'=>'Laki-laki','P'=>'Perempuan']"/>
        <x-field name="tempat_lahir" label="Tempat Lahir" :value="$item->tempat_lahir"/>
        <x-field name="tanggal_lahir" type="date" label="Tanggal Lahir" :value="optional($item->tanggal_lahir)->format('Y-m-d')"/>
        <x-field name="jabatan" label="Jabatan" :value="$item->jabatan"/>
        <x-field type="select" name="status_kepegawaian" label="Status Kepegawaian" :value="$item->status_kepegawaian"
                 :options="$statusOptions"/>
        <x-field name="password" type="password" label="Password" :help="$item->exists ? 'Kosongkan jika tidak diubah' : 'Default sama dengan password'"/>

        {{-- Mapel disimpan ke tabel guru_mapel (tahun ajaran aktif), bukan kolom di tabel guru. --}}
        <div>
            <label class="label" for="mata_pelajaran_id">Guru Mata Pelajaran
                <span class="text-xs text-ink-500">(bisa pilih lebih dari satu, Ctrl+klik)</span></label>
            <select name="mata_pelajaran_id[]" id="mata_pelajaran_id" multiple size="6" class="select h-auto">
                @foreach($mapelList as $m)
                    <option value="{{ $m->id }}"
                        @selected(in_array($m->id, old('mata_pelajaran_id', $mapelTerpilih)))>
                        {{ $m->kode_mapel }} — {{ $m->nama_mapel }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-ink-500">Penugasan per rombel diatur di menu Guru Mapel.</p>
            @error('mata_pelajaran_id.*')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <x-field name="alamat" label="Alamat" :value="$item->alamat"/>
    <x-field type="checkbox" name="is_aktif" :value="$item->is_aktif ?? true"/>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('guru.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
