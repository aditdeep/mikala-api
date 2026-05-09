<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payroll extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'payroll';

    protected $fillable = [
        'payroll_number',
        'mitra_id',
        'order_id',
        'periode_mulai',
        'periode_selesai',
        'jumlah_hari_kerja',
        'tarif_per_hari',
        'gaji_pokok',
        'bonus',
        'potongan',
        'transport',
        'total',
        'status',
        'metode_pembayaran',
        'bank_name',
        'bank_account',
        'bukti_transfer',
        'catatan',
        'approved_at',
        'approved_by',
        'paid_at',
    ];

    protected $casts = [
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
        'tarif_per_hari' => 'decimal:2',
        'gaji_pokok' => 'decimal:2',
        'bonus' => 'decimal:2',
        'potongan' => 'decimal:2',
        'transport' => 'decimal:2',
        'total' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // Helpers
    public static function generatePayrollNumber(): string
    {
        $date = now()->format('Ym');
        $count = self::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count() + 1;
        return "PAY-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotal(): void
    {
        $this->gaji_pokok = $this->tarif_per_hari * $this->jumlah_hari_kerja;
        $this->total = $this->gaji_pokok + $this->bonus + $this->transport - $this->potongan;
        $this->save();
    }
}
