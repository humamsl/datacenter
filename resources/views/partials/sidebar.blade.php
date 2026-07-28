<aside
    class="fixed inset-y-0 left-0 z-30 w-64 border-r border-black/5 transform md:translate-x-0 transition-transform"
    style="background-color: #062275;"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
    @click="if (window.innerWidth < 768 && $event.target.closest('a[href]')) sidebarOpen = false">
    <div class="flex items-center gap-3 px-5 h-16 border-b border-white/15">
        @if($AppCfg['logo_url'])
            <img src="{{ $AppCfg['logo_url'] }}" alt="" class="w-10 h-10 object-contain rounded-lg bg-white/90 p-0.5 shadow-soft">
        @else
            <div class="w-10 h-10 rounded-xl bg-white/20 grid place-items-center text-white font-bold shadow-soft ring-2 ring-white/40">
                {{ mb_substr($AppCfg['app_name'], 0, 1) }}
            </div>
        @endif
        <div class="min-w-0">
            <div class="text-sm font-bold text-white leading-tight truncate">{{ $AppCfg['app_name'] }}</div>
            <div class="text-[11px] text-white leading-tight truncate">{{ $AppCfg['app_tagline'] }}</div>
        </div>
    </div>

    <nav class="px-3 py-4 overflow-y-auto h-[calc(100vh-4rem)]">
        <div class="sidebar-section">Beranda</div>
        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <x-icon name="home"/> Dashboard
        </a>

        <div class="sidebar-section">Data Center</div>
        <a href="{{ route('tahun-ajaran.index') }}" class="sidebar-link {{ request()->routeIs('tahun-ajaran.*') ? 'active' : '' }}">
            <x-icon name="calendar"/> Tahun Ajaran
        </a>
        <a href="{{ route('jurusan.index') }}" class="sidebar-link {{ request()->routeIs('jurusan.*') ? 'active' : '' }}">
            <x-icon name="layers"/> Jurusan
        </a>
        <a href="{{ route('mapel.index') }}" class="sidebar-link {{ request()->routeIs('mapel.*') ? 'active' : '' }}">
            <x-icon name="book"/> Mata Pelajaran
        </a>
        <a href="{{ route('tingkat-kelas.index') }}" class="sidebar-link {{ request()->routeIs('tingkat-kelas.*') ? 'active' : '' }}">
            <x-icon name="layers"/> Tingkat Kelas
        </a>
        <a href="{{ route('rombel.index') }}" class="sidebar-link {{ request()->routeIs('rombel.*') ? 'active' : '' }}">
            <x-icon name="grid"/> Rombongan Belajar
        </a>
        <a href="{{ route('status-kepegawaian.index') }}" class="sidebar-link {{ request()->routeIs('status-kepegawaian.*') ? 'active' : '' }}">
            <x-icon name="clipboard"/> Status Kepegawaian
        </a>
        <a href="{{ route('guru.index') }}" class="sidebar-link {{ request()->routeIs('guru.*') ? 'active' : '' }}">
            <x-icon name="user-tie"/> Data Guru
        </a>
        <a href="{{ route('guru-mapel.index') }}" class="sidebar-link {{ request()->routeIs('guru-mapel.*') ? 'active' : '' }}">
            <x-icon name="bookmark"/> Guru Mapel
        </a>
        <a href="{{ route('siswa.index') }}" class="sidebar-link {{ request()->routeIs('siswa.*') ? 'active' : '' }}">
            <x-icon name="users"/> Data Siswa
        </a>

        <div class="sidebar-section">Administrasi Periodikal</div>
        <a href="{{ route('periodikal.semua.form') }}" class="sidebar-link {{ request()->routeIs('periodikal.semua.*') ? 'active' : '' }}">
            <x-icon name="arrow-right"/> Proses Semua Siswa
        </a>
        <a href="{{ route('periodikal.per-rombel.form') }}" class="sidebar-link {{ request()->routeIs('periodikal.per-rombel.*') ? 'active' : '' }}">
            <x-icon name="user"/> Proses Siswa Per Rombel
        </a>
        <a href="{{ route('periodikal.koreksi.index') }}" class="sidebar-link {{ request()->routeIs('periodikal.koreksi.*') ? 'active' : '' }}">
            <x-icon name="edit"/> Koreksi Hasil Periodikal
        </a>

        <div class="sidebar-section">Administrasi</div>
        <a href="{{ route('reset-data.index') }}" class="sidebar-link {{ request()->routeIs('reset-data.*') ? 'active' : '' }}">
            <x-icon name="trash"/> Reset Data
        </a>
        <a href="{{ route('log-login.index') }}" class="sidebar-link {{ request()->routeIs('log-login.*') ? 'active' : '' }}">
            <x-icon name="key"/> Log Login
        </a>
        <a href="{{ route('pengaturan.index') }}" class="sidebar-link {{ request()->routeIs('pengaturan.*') ? 'active' : '' }}">
            <x-icon name="settings"/> Pengaturan Aplikasi
        </a>
    </nav>
</aside>

<div x-show="sidebarOpen" x-cloak
     @click="sidebarOpen = false"
     class="fixed inset-0 z-20 bg-ink-900/50 md:hidden"></div>
