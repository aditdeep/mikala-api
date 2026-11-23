<?php

namespace App\Console\Commands;

use App\Http\Controllers\Internal\TrainingController;
use App\Models\TrainingMateri;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ganti daftar materi kategori "Dasar" dgn daftar baru Training Center (Nov 2026,
 * "MATERI DASAR PHC/CG/BS LPK MIKALA GLOBAL AKADEMI"). Nomor IV-XI = materi Babysitter
 * (kategori BS) dan kategori PHC tidak disentuh.
 *
 * Materi lama dicocokkan ke materi baru lewat kemiripan nama -> baris yg sama dipakai ulang
 * (riwayat ceklis mitra ikut). Materi lama yg tidak cocok dinonaktifkan (riwayat tetap ada).
 *
 *   php artisan training:ganti-materi-dasar              DRY RUN (lihat pencocokan dulu)
 *   php artisan training:ganti-materi-dasar --pasang=XXVII=45   pasangkan manual (ID dari tabel dry run)
 *   php artisan training:ganti-materi-dasar --jalankan   eksekusi + hitung ulang status mitra
 */
class GantiMateriDasar extends Command
{
    protected $signature = 'training:ganti-materi-dasar {--jalankan} {--pasang=* : pasangan manual KODE_BARU=ID_LAMA, mis. --pasang=XXVII=45}';
    protected $description = 'Ganti daftar materi Dasar dgn daftar baru (default dry-run)';

    /** [kode, nama, [sub materi...]] -- urutan = urutan tampil */
    public const DAFTAR = [
        ['I', 'Komunikasi Efektif', []],
        ['II', 'Etika dan Perilaku + Standar Grooming', []],
        ['III', 'Konsep Kebutuhan Dasar Manusia', []],
        ['XII', 'Kesehatan Reproduksi', []],
        ['XIII', 'Struktur dan Fungsi Tubuh Manusia', []],
        ['XIV', 'Kebersihan Diri atau Hygiene Perorangan', [
            '7 Langkah Cuci Tangan',
            'Membantu Pasien BAB dan BAK (Pria dan Wanita)',
            'Memandikan Pasien di Atas Tempat Tidur',
            'Mencuci dan Menyisir Rambut di Atas Tempat Tidur',
            'Memasang Kateter Kondom',
            'Merawat dan Membersihkan Kolostomi',
            'Merawat Kateter Tetap',
            'Penis dan Vulva Hygiene',
            'Perawatan Mulut Pasien (Membersihkan Mulut, Gosok Gigi dan Membersihkan Gigi Palsu)',
            'Cara Memasang Pampers',
            'Memasang Tenun dengan atau tanpa Pasien di Atas Tempat Tidur',
        ]],
        ['XV', 'Memelihara Kebersihan Lingkungan', [
            'Membersihkan dan Merapikan Kamar Pasien',
            'Membersihkan dan Merapikan Kamar Mandi Pasien',
            'Membersihkan dan Merapikan Lingkungan di Luar Kamar',
        ]],
        ['XVI', 'Tanda-Tanda Vital', [
            'Mengukur Suhu Tubuh',
            'Menghitung Denyut Nadi',
            'Menghitung Pernapasan',
            'Mengukur Tekanan Darah',
            'Mengukur Saturasi Oksigen (SpO2)',
        ]],
        ['XVII', 'Mobilisasi dan Ambulasi', [
            'Ganti Posisi Pasien (Mika-Miki)',
            'Melatih Pasien Duduk di Tempat Tidur',
            'Memindahkan Pasien dari Tempat Tidur ke Kursi Roda dan Sebaliknya',
            'Memindahkan Pasien dari Kursi Roda ke Mobil dan Sebaliknya',
            'Melatih Pergerakan Sendi (ROM)',
            'Mendorong Pasien dengan Kursi Roda',
            'Membantu Pasien Naik dan Turun Tangga',
        ]],
        ['XVIII', 'Pertolongan Pertama pada Kedaruratan (P3K)', []],
        ['XIX', 'Prinsip Pemberian Obat', [
            'Tata Cara Pemberian Obat',
            'Cara Mendokumentasikan Obat',
        ]],
        ['XX', 'Memenuhi Kebutuhan Nutrisi Lansia', [
            'Jenis-Jenis Makanan Lansia',
            'Persiapan dan Penyajian Makan Lansia',
            'Membuat Bubur Saring dan Memberikan Makan melalui NGT',
            'Diet Makanan Sesuai dengan Penyakit Pasien',
            'Mengukur TB dan BB Pasien',
        ]],
        ['XXI', 'Perawatan Kesehatan Usia Lanjut', []],
        ['XXII', 'Kesehatan Mental Usia Lanjut', []],
        ['XXIII', 'Pendampingan Pasien Menjelang Ajal', []],
        ['XXIV', 'Diabetes Melitus', [
            'Memeriksa GDS',
            'Cara Memberikan Terapi Insulin',
        ]],
        ['XXV', 'Perawatan Luka', [
            'Praktik Perawatan Luka Bersih',
            'Perawatan Luka Dekubitus',
        ]],
        ['XXVI', 'Cara Memberi Terapi Oksigen', []],
        ['XXVII', 'Cara Memberi Terapi Nebulizer', []],
        ['XXVIII', 'Bladder Wash', []],
        ['XXIX', 'Bladder Training', []],
        ['XXX', 'Clapping dan Vibrasi', []],
    ];

    /** Daftar datar: [kode, nama, parent_kode|null] */
    public static function baris(): array
    {
        $out = [];
        foreach (self::DAFTAR as [$kode, $nama, $sub]) {
            $out[] = [$kode, $nama, null];
            foreach ($sub as $i => $s) $out[] = [$kode . '-' . ($i + 1), $s, $kode];
        }
        return $out;
    }

    private static function normNama(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($s)));
    }

    /**
     * Pasangkan materi lama ke materi baru berdasar kemiripan nama (greedy, skor tertinggi dulu).
     * @param array<int,string> $lama id => nama
     * @param array<int,string> $baru idx => nama
     * @return array<int,array{0:int,1:int}> idx => [id lama, skor]
     */
    public static function cocokkan(array $lama, array $baru, int $min = 75): array
    {
        $pasangan = [];
        foreach ($baru as $i => $nb) {
            $b = self::normNama($nb);
            foreach ($lama as $id => $nl) {
                $l = self::normNama($nl);
                if ($b === '' || $l === '') continue;
                similar_text($b, $l, $persen);
                // nama lama berupa bagian dari nama baru (atau sebaliknya), mis. "Pemberian Nebulizer"
                if (min(strlen($b), strlen($l)) >= 8 && (str_contains($b, $l) || str_contains($l, $b))) $persen = max($persen, 85);
                if ($persen >= $min) $pasangan[] = [$i, $id, (int) round($persen)];
            }
        }
        usort($pasangan, fn ($x, $y) => $y[2] <=> $x[2]);
        $hasil = []; $dipakai = [];
        foreach ($pasangan as [$i, $id, $skor]) {
            if (isset($hasil[$i]) || isset($dipakai[$id])) continue;
            $hasil[$i] = [$id, $skor];
            $dipakai[$id] = true;
        }
        return $hasil;
    }

    public function handle()
    {
        $jalankan = (bool) $this->option('jalankan');
        $this->info($jalankan ? '*** MODE EKSEKUSI ***' : '*** DRY RUN (tambah --jalankan untuk eksekusi) ***');

        $lama = TrainingMateri::where('kategori', 'Dasar')->whereRaw('is_active = true')->orderBy('urutan')->get()->keyBy('id');
        $baris = self::baris();
        $manual = [];
        foreach ((array) $this->option('pasang') as $p) {
            [$k, $id] = array_pad(explode('=', $p, 2), 2, null);
            $i = array_search(strtoupper(trim($k)), array_column($baris, 0), true);
            if ($i === false || !$lama->has((int) $id)) { $this->error("--pasang={$p} tidak valid (kode baru / ID materi Dasar aktif tidak ditemukan)."); return 1; }
            $manual[$i] = [(int) $id, 'manual'];
        }
        $sisaLama = array_diff_key($lama->pluck('nama', 'id')->all(), array_flip(array_column($manual, 0)));
        $cocok = $manual + self::cocokkan($sisaLama, array_diff_key(array_column($baris, 1), $manual));
        $jml = fn ($id) => DB::table('training_checklist')->where('materi_id', $id)->count();

        $this->table(['Kode', 'Materi baru', 'Diambil dari materi lama (riwayat ceklis ikut)', 'Mirip', 'Ceklis'],
            array_map(function ($i) use ($baris, $cocok, $lama, $jml) {
                [$kode, $nama, $induk] = $baris[$i];
                $c = $cocok[$i] ?? null;
                return [$kode, ($induk ? '   ' : '') . mb_strimwidth($nama, 0, 55, '…'),
                    $c ? mb_strimwidth($lama[$c[0]]->kode . ' ' . $lama[$c[0]]->nama, 0, 55, '…') : '(BARU)',
                    $c ? (is_int($c[1]) ? $c[1] . '%' : $c[1]) : '', $c ? $jml($c[0]) : ''];
            }, array_keys($baris)));

        $terpakai = array_column($cocok, 0);
        $nonaktif = $lama->except($terpakai);
        if ($nonaktif->isNotEmpty()) {
            $this->warn('Materi lama yg TIDAK ada di daftar baru -> dinonaktifkan (riwayat ceklis tetap tersimpan):');
            $this->table(['ID', 'Kode', 'Nama', 'Ceklis'], $nonaktif->map(fn ($m) => [$m->id, $m->kode, $m->nama, $jml($m->id) . ' mitra'])->values()->all());
        }
        $this->line(count($baris) . ' materi baru: ' . count($cocok) . ' pakai baris lama, ' . (count($baris) - count($cocok)) . ' dibuat baru, ' . $nonaktif->count() . ' materi lama dinonaktifkan.');

        if (!$jalankan) { $this->newLine(); $this->info('Cek tabel di atas. Kalau pasangannya benar, jalankan ulang dengan --jalankan.'); return 0; }

        DB::transaction(function () use ($lama, $baris, $cocok, $nonaktif) {
            // kode lama dilepas dulu supaya tidak bentrok saat kode baru dipasang
            foreach ($lama as $m) $m->update(['kode' => $m->kode . '~' . $m->id]);
            foreach ($nonaktif as $m) $m->update(['is_active' => DB::raw('false'), 'parent_kode' => null]);
            foreach ($baris as $i => [$kode, $nama, $induk]) {
                $data = ['kode' => $kode, 'nama' => $nama, 'parent_kode' => $induk, 'urutan' => $i + 1, 'kategori' => 'Dasar'];
                if (isset($cocok[$i])) $lama[$cocok[$i][0]]->update($data);
                else TrainingMateri::create($data + ['is_active' => DB::raw('true')]);
            }
        });

        // daftar materi wajib berubah -> hitung ulang status mitra yg masih training
        $tc = app(TrainingController::class);
        $ids = DB::table('mitra')->whereIn('training_status', ['pending', 'in_progress'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('training_checklist')->whereColumn('training_checklist.mitra_id', 'mitra.id'))
            ->pluck('id');
        foreach ($ids as $id) $tc->hitungUlangStatus($id);

        $this->info("Selesai. Status {$ids->count()} mitra yg masih training dihitung ulang.");
        return 0;
    }
}
