<?php

namespace App\Console\Commands;

use App\Models\Klien;
use App\Models\Lead;
use App\Models\User;
use App\Support\NomorHp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Buatkan akun login apps Klien utk Cust/PJ dari Deal yg belum punya akun (mis. hasil
 * migrasi:pj-lama). Login pakai No HP/WA + PIN 6 angka acak (beda per klien).
 *
 *   php artisan klien:buat-akun              DRY RUN: laporan, tidak mengubah data
 *   php artisan klien:buat-akun --jalankan   eksekusi + tulis CSV daftar PIN utk CC kirim via WA
 *   --semua                                  ikut buatkan akun PJ yg semua Deal-nya sudah Stop
 *
 * - Deal dikelompokkan per No HP Cust/PJ (kolom kontak): 1 nomor = 1 akun, semua Deal-nya
 *   (aktif & stop) ditautkan ke akun itu lewat klien_id.
 * - No HP sudah dipakai akun klien lain -> Deal ditautkan ke akun itu, PIN tidak diubah.
 * - No HP dipakai akun non-klien (mitra/internal) / kosong / tidak valid -> dilewati, dilaporkan.
 * - CSV berisi PIN asli: simpan di storage/app (tidak publik), HAPUS setelah semua terkirim.
 */
class BuatAkunKlien extends Command
{
    protected $signature = 'klien:buat-akun {--jalankan} {--semua}';
    protected $description = 'Buat akun apps Klien (login No HP + PIN acak) dari Deal tanpa akun (default dry-run)';

    public function handle()
    {
        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tambah --jalankan untuk eksekusi) ***');

        $leads = Lead::whereNull('klien_id')
            ->whereIn('status', [Lead::STATUS_DEAL, Lead::STATUS_STOP])
            ->orderByDesc('deal_at')->orderByDesc('id')
            ->get();
        $this->line($leads->count() . ' Deal (aktif/stop) belum tertaut akun klien');

        $lewat = [];
        $grup = [];
        foreach ($leads as $l) {
            $hp = NomorHp::normal($l->kontak) ?? NomorHp::normal($l->no_wa_klien);
            if (!$hp) { $lewat[] = $this->label($l) . ' -- No HP PJ kosong/tidak valid (' . ($l->kontak ?: '-') . ')'; continue; }
            $grup[$hp][] = $l;
        }

        $baru = []; $tautLama = []; $flag = []; $nStop = 0;
        foreach ($grup as $hp => $list) {
            $aktif = collect($list)->contains(fn ($l) => (int) $l->status === Lead::STATUS_DEAL);
            $ada = NomorHp::cariUser($hp);

            if ($ada->count() > 1) { $lewat[] = "{$hp} -- dipakai {$ada->count()} akun, rapikan manual dulu"; continue; }
            if ($u = $ada->first()) {
                if ($u->role !== 'klien' || !$u->klien) { $lewat[] = "{$hp} ({$list[0]->nama_leads}) -- nomor sudah dipakai akun {$u->role} \"{$u->name}\""; continue; }
                $tautLama[] = [$u->klien, $list];
                continue;
            }
            if (!$aktif && !$this->option('semua')) { $nStop++; continue; } // PJ lama yg sudah selesai semua

            $nama = collect($list)->pluck('nama_leads')->map(fn ($n) => trim((string) $n))->filter()->unique();
            if ($nama->count() > 1) $flag[] = "{$hp}: nama PJ beda-beda di Deal (" . $nama->implode(' / ') . ") -- dipakai \"{$nama->first()}\"";
            $baru[] = ['hp' => $hp, 'nama' => $nama->first() ?: $list[0]->nama_pasien, 'list' => $list];
        }

        $this->newLine();
        $this->info('Akun baru: ' . count($baru) . ' (' . collect($baru)->sum(fn ($b) => count($b['list'])) . ' Deal)');
        $this->line('Ditautkan ke akun klien yg sudah ada: ' . count($tautLama) . ' (' . collect($tautLama)->sum(fn ($t) => count($t[1])) . ' Deal)');
        if ($nStop) $this->line("PJ yg semua Deal-nya sudah Stop (tidak dibuatkan akun, tambah --semua kalau perlu): {$nStop}");
        foreach ($tautLama as [$k, $list]) $this->line("  - {$k->nama_lengkap} <- " . implode(', ', array_map(fn ($l) => $this->label($l), $list)));
        if ($lewat) { $this->newLine(); $this->line('<fg=yellow>Dilewati (lengkapi manual di CC):</>'); foreach ($lewat as $x) $this->line("  - {$x}"); }
        if ($flag) { $this->newLine(); $this->line('<fg=yellow>Perlu dicek:</>'); foreach ($flag as $x) $this->line("  - {$x}"); }

        if (!$jalankan) { $this->newLine(); $this->info('Jalankan ulang dengan --jalankan untuk eksekusi.'); return 0; }

        $loginUrl = rtrim((string) config('services.frontend.klien'), '/') . '/auth/login';
        $baris = [];
        DB::transaction(function () use ($baru, $tautLama, $loginUrl, &$baris) {
            foreach ($tautLama as [$k, $list]) Lead::whereIn('id', collect($list)->pluck('id'))->update(['klien_id' => $k->id]);

            foreach ($baru as $b) {
                $pin = $this->pin();
                $l0 = $b['list'][0];
                $user = User::create([
                    'name' => $b['nama'], 'email' => null, 'phone' => $b['hp'],
                    'password' => Hash::make($pin), 'role' => 'klien', 'status' => 'active',
                ]);
                $klien = Klien::create([
                    'user_id' => $user->id, 'nama_lengkap' => $b['nama'], 'tipe' => 'keluarga',
                    'alamat' => $l0->alamat_cust_pj ?: ($l0->alamat_klien ?: '-'), 'kota' => '-', 'provinsi' => '-',
                    'status' => 'active', 'is_verified' => DB::raw('true'),
                ]);
                Lead::whereIn('id', collect($b['list'])->pluck('id'))->update(['klien_id' => $klien->id]);

                $pasien = collect($b['list'])->pluck('nama_pasien')->filter()->unique()->implode(', ');
                $pesan = "Halo Bapak/Ibu {$b['nama']}, berikut akun aplikasi Klien Mikala Global Medika untuk memantau layanan" . ($pasien ? " {$pasien}" : '') . ":\n\n"
                    . "No HP: {$b['hp']}\nPIN: {$pin}\n\nLogin di {$loginUrl}\nMohon PIN ini tidak dibagikan ke siapa pun. Terima kasih.";
                $baris[] = [$b['nama'], $b['hp'], $pin, $pasien, collect($b['list'])->map(fn ($l) => $l->nomor_deal ?: $l->nomor)->implode(', '),
                    'https://wa.me/' . NomorHp::wa($b['hp']) . '?text=' . rawurlencode($pesan)];
            }
        });

        if ($baris) {
            $rel = 'akun_klien_' . now()->format('Ymd_His') . '.csv';
            $path = storage_path('app/' . $rel);
            $f = fopen($path, 'w');
            fputcsv($f, ['nama_pj', 'no_hp', 'pin', 'pasien', 'deal', 'link_wa']);
            foreach ($baris as $r) fputcsv($f, $r);
            fclose($f);
            @chmod($path, 0600);
            $this->newLine();
            $this->info('Daftar PIN: ' . $path);
            $this->warn('File ini berisi PIN asli -- kirim ke CC lewat jalur privat, lalu HAPUS dari server setelah semua terkirim.');
        }
        $this->info('Selesai: ' . count($baris) . ' akun baru, ' . count($tautLama) . ' ditautkan ke akun lama.');
        return 0;
    }

    private function label(Lead $l): string
    {
        return '#' . ($l->nomor_deal ?: $l->nomor ?: $l->id) . ' ' . ($l->nama_leads ?: '-') . ' / pasien ' . ($l->nama_pasien ?: '-');
    }

    /** PIN 6 angka acak, hindari yg gampang ditebak (angka kembar semua / urut). */
    private function pin(): string
    {
        do {
            $p = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (count(array_unique(str_split($p))) < 3 || str_contains('0123456789', $p) || str_contains('9876543210', $p));
        return $p;
    }
}
