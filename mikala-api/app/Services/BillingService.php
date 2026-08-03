<?php

namespace App\Services;

use App\Models\Tagihan;
use App\Models\Order;
use App\Models\Klien;
use Illuminate\Support\Facades\DB;

class BillingService
{
    protected $notifikasiService;

    public function __construct(NotifikasiService $notifikasiService)
    {
        $this->notifikasiService = $notifikasiService;
    }

    /**
     * Generate invoice from order
     */
    public function generateFromOrder(Order $order): Tagihan
    {
        return DB::transaction(function () use ($order) {
            $tagihan = Tagihan::create([
                'invoice_number' => Tagihan::generateInvoiceNumber(),
                'klien_id' => $order->klien_id,
                'order_id' => $order->id,
                'tanggal_invoice' => now(),
                'tanggal_jatuh_tempo' => now()->addDays(14), // 14 days default
                'subtotal' => $order->subtotal,
                'pajak' => $order->pajak,
                'diskon' => $order->diskon,
                'total' => $order->total,
                'sisa' => $order->total,
                'status' => 'unpaid',
            ]);

            // Send notification to klien, finance, and customer care
            $this->notifikasiService->send(
                $order->klien->user_id,
                'billing',
                'Tagihan Baru',
                "Tagihan {$tagihan->invoice_number} sebesar Rp " . number_format($tagihan->total, 0, ',', '.') . " telah dibuat.",
                ['related_type' => Tagihan::class, 'related_id' => $tagihan->id]
            );

            $financeUserIds = \App\Models\User::byRole('finance')->pluck('id')->toArray();
            if (!empty($financeUserIds)) {
                $this->notifikasiService->sendBulk(
                    $financeUserIds,
                    'billing',
                    'Tagihan Baru Dibuat',
                    "Tagihan {$tagihan->invoice_number} untuk klien {$order->klien->nama_lengkap} telah dibuat.",
                    ['related_type' => Tagihan::class, 'related_id' => $tagihan->id]
                );
            }

            return $tagihan;
        });
    }

    /**
     * Process payment
     */
    public function processPayment(Tagihan $tagihan, float $amount, string $method, ?string $bukti = null): bool
    {
        $tagihan->jumlah_bayar += $amount;
        $tagihan->sisa = $tagihan->total - $tagihan->jumlah_bayar;
        $tagihan->metode_pembayaran = $method;
        
        if ($bukti) {
            $tagihan->bukti_transfer = $bukti;
        }

        if ($tagihan->sisa <= 0) {
            $tagihan->status = 'paid';
            $tagihan->paid_at = now();
        } elseif ($tagihan->jumlah_bayar > 0) {
            $tagihan->status = 'partial';
        }

        $tagihan->save();

        // Notify
        $this->notifikasiService->send(
            $tagihan->klien->user_id,
            'billing',
            'Pembayaran Diterima',
            "Pembayaran sebesar Rp " . number_format($amount, 0, ',', '.') . " untuk tagihan {$tagihan->invoice_number} telah diterima.",
            ['related_type' => Tagihan::class, 'related_id' => $tagihan->id]
        );

        return true;
    }

    /**
     * Check and send billing reminders
     */
    public function sendReminders(): void
    {
        // H-7
        $dueIn7Days = Tagihan::unpaid()->dueSoon(7)->get();
        foreach ($dueIn7Days as $tagihan) {
            $this->sendReminderNotification($tagihan, 'H-7');
        }

        // H-3
        $dueIn3Days = Tagihan::unpaid()->dueSoon(3)->get();
        foreach ($dueIn3Days as $tagihan) {
            $this->sendReminderNotification($tagihan, 'H-3');
        }

        // H-1
        $dueIn1Day = Tagihan::unpaid()->dueSoon(1)->get();
        foreach ($dueIn1Day as $tagihan) {
            $this->sendReminderNotification($tagihan, 'H-1');
        }

        // Overdue
        $overdue = Tagihan::where('status', 'unpaid')
            ->where('tanggal_jatuh_tempo', '<', now())
            ->get();
        foreach ($overdue as $tagihan) {
            $tagihan->update(['status' => 'overdue']);
            $this->sendReminderNotification($tagihan, 'Overdue');
        }
    }

    protected function sendReminderNotification(Tagihan $tagihan, string $stage): void
    {
        $message = match($stage) {
            'H-7' => "Pengingat: Tagihan {$tagihan->invoice_number} akan jatuh tempo dalam 7 hari.",
            'H-3' => "Pengingat: Tagihan {$tagihan->invoice_number} akan jatuh tempo dalam 3 hari.",
            'H-1' => "Pengingat Penting: Tagihan {$tagihan->invoice_number} akan jatuh tempo besok!",
            'Overdue' => "Perhatian: Tagihan {$tagihan->invoice_number} telah melewati jatuh tempo.",
            default => "Pengingat pembayaran tagihan {$tagihan->invoice_number}",
        };

        $this->notifikasiService->send(
            $tagihan->klien->user_id,
            'billing',
            'Pengingat Pembayaran',
            $message,
            ['related_type' => Tagihan::class, 'related_id' => $tagihan->id]
        );

        $tagihan->update(['overdue_notified_at' => now()]);
    }
}
