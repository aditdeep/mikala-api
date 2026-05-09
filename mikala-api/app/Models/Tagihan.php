<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tagihan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tagihan';

    protected $fillable = [
        'invoice_number',
        'klien_id',
        'order_id',
        'tanggal_invoice',
        'tanggal_jatuh_tempo',
        'subtotal',
        'pajak',
        'diskon',
        'total',
        'jumlah_bayar',
        'sisa',
        'status',
        'metode_pembayaran',
        'bukti_transfer',
        'catatan',
        'paid_at',
        'overdue_notified_at',
    ];

    protected $casts = [
        'tanggal_invoice' => 'date',
        'tanggal_jatuh_tempo' => 'date',
        'subtotal' => 'decimal:2',
        'pajak' => 'decimal:2',
        'diskon' => 'decimal:2',
        'total' => 'decimal:2',
        'jumlah_bayar' => 'decimal:2',
        'sisa' => 'decimal:2',
        'paid_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    // Relationships
    public function klien()
    {
        return $this->belongsTo(Klien::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Scopes
    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeDueSoon($query, $days = 7)
    {
        return $query->where('status', 'unpaid')
            ->whereBetween('tanggal_jatuh_tempo', [now(), now()->addDays($days)]);
    }

    // Helpers
    public static function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return "INV-{$date}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    public function checkOverdue(): void
    {
        if ($this->status === 'unpaid' && $this->tanggal_jatuh_tempo->isPast()) {
            $this->update(['status' => 'overdue']);
        }
    }

    public function getDaysUntilDue(): int
    {
        return now()->diffInDays($this->tanggal_jatuh_tempo, false);
    }
}
