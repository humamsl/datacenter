<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusKepegawaian extends Model
{
    protected $table = 'status_kepegawaian';
    protected $guarded = ['id'];

    /**
     * Guru menyimpan NAMA status (string) di kolom guru.status_kepegawaian,
     * bukan foreign key id — supaya kompatibel dengan data & import lama.
     */
    public function guru()
    {
        return $this->hasMany(Guru::class, 'status_kepegawaian', 'nama_status');
    }

    public function scopeAktif($q)
    {
        return $q->where('is_aktif', true);
    }

    /** Daftar opsi ['PNS' => 'PNS', ...] untuk <select> form guru. */
    public static function options(): array
    {
        return static::aktif()->orderBy('nama_status')
            ->pluck('nama_status', 'nama_status')->toArray();
    }
}
