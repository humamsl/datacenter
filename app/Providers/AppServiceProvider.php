<?php

namespace App\Providers;

use App\Models\AppSetting;
use App\Models\Sekolah;
use App\Support\CopyrightGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /** Branding sekolah dimemo-kan agar Sekolah::first() hanya sekali per request. */
    protected ?array $schoolBranding = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Rate limiter default untuk routes/api.php (dipakai $middleware->throttleApi())
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Bagikan pengaturan aplikasi ke semua view
        View::composer('*', function ($view) {
            // Lapis kedua proteksi hak cipta (independen dari middleware): karena
            // composer ini WAJIB jalan agar branding/AppCfg tampil di setiap
            // halaman, menghapusnya merusak aplikasi juga. Halaman kunci itu
            // sendiri dilewati agar tidak rekursif.
            if ($view->name() === 'errors.copyright') {
                return;
            }
            if (! CopyrightGuard::passes()) {
                throw new HttpResponseException(response()->view('errors.copyright', [], 403));
            }

            // Skip jika tabel belum migrate (mis. saat install)
            if (! Schema::hasTable('app_settings')) {
                $view->with('AppCfg', $this->defaults());
                return;
            }

            // Nama + logo diambil dari Profil Sekolah (sumber tunggal branding,
            // sama seperti yang diekspos ke CBT & landing-page), dengan fallback
            // ke AppSetting bila Profil Sekolah belum diisi.
            $brand     = $this->schoolBranding();
            $localLogo = AppSetting::get('logo');
            $localFav  = AppSetting::get('favicon');

            $view->with('AppCfg', [
                'app_name'       => $brand['name'] ?: AppSetting::get('app_name', config('app.name')),
                'app_tagline'    => AppSetting::get('app_tagline', 'Sistem Informasi Data Induk Sekolah'),
                'theme_color'    => AppSetting::get('theme_color', '#0d9488'),
                'logo'           => $localLogo,
                'favicon'        => $localFav,
                'logo_url'       => $brand['logo_url'] ?? ($localLogo ? asset('storage/'.$localLogo) : null),
                'favicon_url'    => $brand['logo_url'] ?? ($localFav ? asset('storage/'.$localFav) : null),
                'login_bg'       => AppSetting::get('login_bg'),
                'login_title'    => AppSetting::get('login_title', 'Selamat datang di Data Center Sekolah.'),
                'login_subtitle' => AppSetting::get('login_subtitle', 'Kelola data sekolah, guru, siswa, dan kelas dalam satu sumber data terpusat.'),
                'footer_text'    => AppSetting::get('footer_text'),
            ]);
        });
    }

    /** Nama + URL logo dari Profil Sekolah (memoized per request). */
    protected function schoolBranding(): array
    {
        if ($this->schoolBranding !== null) {
            return $this->schoolBranding;
        }

        $sekolah = Schema::hasTable('sekolah') ? Sekolah::first() : null;
        $logoUrl = ($sekolah && $sekolah->logo) ? asset('storage/'.$sekolah->logo) : null;

        return $this->schoolBranding = [
            'name'     => $sekolah->nama_sekolah ?? null,
            'logo_url' => $logoUrl,
        ];
    }

    protected function defaults(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_tagline' => 'Sistem Informasi Data Induk Sekolah',
            'theme_color' => '#0d9488',
            'logo' => null, 'favicon' => null, 'logo_url' => null, 'favicon_url' => null, 'login_bg' => null,
            'login_title' => 'Selamat datang di Data Center Sekolah.',
            'login_subtitle' => 'Kelola data sekolah, guru, siswa, dan kelas dalam satu sumber data terpusat.',
            'footer_text' => null,
        ];
    }
}
