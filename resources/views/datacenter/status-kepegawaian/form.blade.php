@extends('layouts.app')
@section('title', $item->exists ? 'Edit Status Kepegawaian' : 'Tambah Status Kepegawaian')

@section('content')
<x-page-header :title="$item->exists ? 'Edit Status Kepegawaian' : 'Tambah Status Kepegawaian'"/>
<form method="POST" action="{{ $item->exists ? route('status-kepegawaian.update', $item) : route('status-kepegawaian.store') }}" class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif
    <x-field name="nama_status" label="Nama Status" :value="$item->nama_status" required
             placeholder="Contoh: PNS, PPPK, GTT, Honorer"
             :help="$item->exists && $item->guru()->count() ? 'Mengganti nama akan ikut memperbarui status pada semua guru yang memakainya.' : null"/>
    <x-field type="textarea" name="keterangan" label="Keterangan" :value="$item->keterangan"/>
    <x-field type="checkbox" name="is_aktif" :value="$item->is_aktif ?? true"/>
    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('status-kepegawaian.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
