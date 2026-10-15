<?php

namespace App\Support;

/**
 * PDF A4 dengan kop surat resmi PT. Mikala Global Medika di SETIAP halaman:
 * header full lebar menempel di tepi atas, footer full lebar menempel di tepi bawah (tanpa
 * margin). Area teks otomatis diberi jarak dari kop supaya isi tidak menabrak ornamen.
 * Gambar kop: resources/kop/kop-header.jpg (2000x384) & kop-footer.jpg (2000x541).
 *
 * Dipakai Kontrak 2 (Perjanjian Penempatan MGM-Mitra) & Kontrak 3 (Perjanjian Kerja Mitra-Klien).
 */
class KopSuratPdf extends \TCPDF
{
    public const LEBAR = 210.0;                       // A4 mm
    public const TINGGI = 297.0;
    public const HEADER_H = self::LEBAR * 384 / 2000; // ±40,3 mm
    public const FOOTER_H = self::LEBAR * 541 / 2000; // ±56,8 mm
    private const JARAK = 6;                          // jarak teks ke kop (mm)

    public static function buat(string $judul): self
    {
        $pdf = new self('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Mikala Global Medika');
        $pdf->SetAuthor('PT. Mikala Global Medika');
        $pdf->SetTitle($judul);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);
        $pdf->SetMargins(20, self::HEADER_H + self::JARAK, 20);
        $pdf->SetAutoPageBreak(true, self::FOOTER_H + self::JARAK);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        return $pdf;
    }

    public function Header()
    {
        $this->Image(resource_path('kop/kop-header.jpg'), 0, 0, self::LEBAR, self::HEADER_H, 'JPG');
    }

    public function Footer()
    {
        $pb = $this->getAutoPageBreak();
        $bm = $this->getBreakMargin();
        $this->SetAutoPageBreak(false, 0); // gambar footer berada di area margin bawah
        $this->Image(resource_path('kop/kop-footer.jpg'), 0, self::TINGGI - self::FOOTER_H, self::LEBAR, self::FOOTER_H, 'JPG');
        $this->SetAutoPageBreak($pb, $bm);
    }
}
