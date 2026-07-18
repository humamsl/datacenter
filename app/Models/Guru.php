<?php

namespace App\Models;

use App\Concerns\HasRbac;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Guru extends Authenticatable
{
    use HasFactory, Notifiable, HasRbac;

    protected $table = 'guru';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_aktif' => 'boolean',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
            'locked_until' => 'datetime',
            'otp_enabled' => 'boolean',
        ];
    }

    public function otpCodes(): MorphMany
    {
        return $this->morphMany(OtpCode::class, 'authable');
    }

    public function getUserTypeAttribute(): string { return 'guru'; }
    public function getNameAttribute(): ?string { return $this->attributes['nama_ptk'] ?? null; }
    public function getUserNameAttribute(): string { return $this->nip; }

    /** Apakah akun sedang terkunci karena terlalu banyak percobaan login gagal. */
    public function getIsTerkunciAttribute(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/'.$this->foto) : null;
    }

    public function mapel()
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function guruMapel()
    {
        return $this->hasMany(GuruMapel::class);
    }

    public function rombelWali()
    {
        return $this->hasMany(RombonganBelajar::class, 'wali_kelas_id');
    }
}
