@extends('layouts.app')
@section('title', 'Status Kepegawaian')
@section('breadcrumb', 'Data Center / Status Kepegawaian')

@section('content')
<x-page-header title="Status Kepegawaian" subtitle="Master status kepegawaian guru (PNS, PPPK, GTT, dst.)">
    <x-slot:action>
        <a href="{{ route('status-kepegawaian.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ $q }}" class="input" placeholder="Cari status kepegawaian...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card">
    <table class="table-modern">
        <thead><tr><th>Nama Status</th><th>Keterangan</th><th>Jumlah Guru</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $it)
            <tr>
                <td class="font-semibold text-ink-900">{{ $it->nama_status }}</td>
                <td class="text-ink-500">{{ $it->keterangan ?: '—' }}</td>
                <td>
                    @if($it->guru_count > 0)
                        <span class="badge-brand">{{ $it->guru_count }} guru</span>
                    @else
                        <span class="badge-muted">0 guru</span>
                    @endif
                </td>
                <td>@if($it->is_aktif)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif</td>
                <td class="text-right">
                    <a href="{{ route('status-kepegawaian.edit', $it) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    <form method="POST" action="{{ route('status-kepegawaian.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus status kepegawaian ini?')">
                        @csrf @method('DELETE')
                        <button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center py-8 text-ink-500">Belum ada data.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
