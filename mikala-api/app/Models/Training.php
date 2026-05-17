<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'mitra_id',
        'tipe',
        'program_name',
        'deskripsi',
        'tanggal_mulai',
        'tanggal_selesai',
        'durasi_hari',
        'biaya',
        'checklist',
        'checklist_completed',
        'checklist_total',
        'status',
        'score',
        'feedback',
        'rekomendasi',
        'sertifikat_file',
        'completed_at',
        'trainer_id',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'biaya' => 'decimal:2',
        'checklist' => 'array',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    // Scopes
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('tipe', $type);
    }

    // Helpers
    public function updateChecklistProgress(): void
    {
        if ($this->checklist) {
            $completed = collect($this->checklist)->where('done', true)->count();
            $this->update([
                'checklist_completed' => $completed,
                'checklist_total' => count($this->checklist),
            ]);
        }
    }

    public function getProgressPercentage(): float
    {
        if ($this->checklist_total === 0) {
            return 0;
        }
        return ($this->checklist_completed / $this->checklist_total) * 100;
    }
}
