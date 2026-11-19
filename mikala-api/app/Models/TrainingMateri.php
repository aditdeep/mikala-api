<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TrainingMateri extends Model {
    protected $table = 'training_materi';
    protected $fillable = ['kode','nama','kategori','parent_kode','urutan','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function checklists() { return $this->hasMany(TrainingChecklist::class,'materi_id'); }

    /** Kode kadang ditulis "IV." / " iv" -> samakan dulu sebelum dicocokkan dgn parent_kode. */
    public static function normKode($k): string
    {
        return strtoupper(trim(rtrim(trim((string) $k), '.')));
    }

    /**
     * Materi induk (punya sub materi) tidak diceklis manual: dianggap selesai otomatis kalau
     * SEMUA sub-nya sudah diceklis. Yg dihitung progress/nilai hanya materi tanpa sub (daun).
     *
     * @param iterable $materi baris materi aktif (id, kode, kategori, parent_kode)
     * @param array $checkedIds id materi yg punya baris training_checklist
     * @return array{checked: array<int,bool>, induk: array<int,int>, daun: array<int,int>}
     */
    public static function pohon($materi, array $checkedIds): array
    {
        $byId = collect($materi)->keyBy('id');
        $ada = array_flip($checkedIds);
        $kunci = fn ($kat, $kode) => $kat . '|' . self::normKode($kode);
        $anak = [];
        foreach ($byId as $m) {
            if (self::normKode($m->parent_kode) !== '') $anak[$kunci($m->kategori, $m->parent_kode)][] = $m->id;
        }

        $checked = [];
        $cek = function ($id) use (&$cek, &$checked, $anak, $byId, $ada, $kunci) {
            if (isset($checked[$id])) return $checked[$id];
            $checked[$id] = false; // jaga2 kalau ada data melingkar
            $m = $byId[$id];
            $sub = array_values(array_diff($anak[$kunci($m->kategori, $m->kode)] ?? [], [$id]));
            return $checked[$id] = $sub ? collect($sub)->every(fn ($s) => $cek($s)) : isset($ada[$id]);
        };
        foreach ($byId->keys() as $id) $cek($id);

        $induk = $byId->filter(fn ($m) => array_diff($anak[$kunci($m->kategori, $m->kode)] ?? [], [$m->id]))->keys()->all();
        return ['checked' => $checked, 'induk' => $induk, 'daun' => $byId->keys()->diff($induk)->values()->all()];
    }
}
