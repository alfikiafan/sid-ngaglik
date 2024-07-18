<?php

/*
 *
 * File ini bagian dari:
 *
 * OpenSID
 *
 * Sistem informasi desa sumber terbuka untuk memajukan desa
 *
 * Aplikasi dan source code ini dirilis berdasarkan lisensi GPL V3
 *
 * Hak Cipta 2009 - 2015 Combine Resource Institution (http://lumbungkomunitas.net/)
 * Hak Cipta 2016 - 2024 Perkumpulan Desa Digital Terbuka (https://opendesa.id)
 *
 * Dengan ini diberikan izin, secara gratis, kepada siapa pun yang mendapatkan salinan
 * dari perangkat lunak ini dan file dokumentasi terkait ("Aplikasi Ini"), untuk diperlakukan
 * tanpa batasan, termasuk hak untuk menggunakan, menyalin, mengubah dan/atau mendistribusikan,
 * asal tunduk pada syarat berikut:
 *
 * Pemberitahuan hak cipta di atas dan pemberitahuan izin ini harus disertakan dalam
 * setiap salinan atau bagian penting Aplikasi Ini. Barang siapa yang menghapus atau menghilangkan
 * pemberitahuan ini melanggar ketentuan lisensi Aplikasi Ini.
 *
 * PERANGKAT LUNAK INI DISEDIAKAN "SEBAGAIMANA ADANYA", TANPA JAMINAN APA PUN, BAIK TERSURAT MAUPUN
 * TERSIRAT. PENULIS ATAU PEMEGANG HAK CIPTA SAMA SEKALI TIDAK BERTANGGUNG JAWAB ATAS KLAIM, KERUSAKAN ATAU
 * KEWAJIBAN APAPUN ATAS PENGGUNAAN ATAU LAINNYA TERKAIT APLIKASI INI.
 *
 * @package   OpenSID
 * @author    Tim Pengembang OpenDesa
 * @copyright Hak Cipta 2009 - 2015 Combine Resource Institution (http://lumbungkomunitas.net/)
 * @copyright Hak Cipta 2016 - 2024 Perkumpulan Desa Digital Terbuka (https://opendesa.id)
 * @license   http://www.gnu.org/licenses/gpl.html GPL V3
 * @link      https://github.com/OpenSID/OpenSID
 *
 */

use Spipu\Html2Pdf\Exception\ExceptionFormatter;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Html2Pdf;

/**
 * SuratExportDesa
 *
 * Mengembalikan path surat ubahan desa apabila ada.
 * Cek folder semua komponen surat dulu, baru cek folder export
 *
 * @param mixed $nama_surat
 */
function SuratExportDesa(string $nama_surat): string
{
    $surat_export_desa = LOKASI_SURAT_DESA . $nama_surat . '/' . $nama_surat . '.rtf';
    if (is_file($surat_export_desa)) {
        return $surat_export_desa;
    }

    $surat_export_desa = LOKASI_SURAT_EXPORT_DESA . $nama_surat . '.rtf';
    if (is_file($surat_export_desa)) {
        return $surat_export_desa;
    }

    return '';
}

/**
 * SuratExport
 *
 * Mengembalikan path surat export apabila ada, dengan prioritas:
 *    1. surat export ubahan desa
 *    2. surat export asli SID
 *
 * @param mixed $nama_surat
 */
function SuratExport($nama_surat): string
{
    if (SuratExportDesa($nama_surat) != '') {
        return SuratExportDesa($nama_surat);
    }

    $file = "template-surat/{$nama_surat}/{$nama_surat}.rtf";
    if (is_file(FCPATH . $file)) {
        return $file;
    }

    return '';
}

function ikut_case(string $format, $str): string
{
    $str = strtolower($str);
    if (ctype_upper($format[0]) && ctype_upper($format[1])) {
        return strtoupper($str);
    }
    if (ctype_upper($format[0])) {
        return ucwords($str);
    }

    return $str;
}

/**
 * Membuat string yang diisi &nbsp; di awal dan di akhir, dengan panjang yang ditentukan.
 *
 * @param            string      Text yang akan ditambahi awal dan akhiran
 * @param            awal     Jumlah karakter &nbsp; pada awal text
 * @param            panjang  Panjang string yang dihasilkan,
 *                            di mana setiap &nbsp; dihitung sebagai satu karakter
 * @param mixed $str
 * @param mixed $awal
 * @param mixed $panjang
 *
 * @return string berisi text yang telah diberi awalan dan akhiran &nbsp;
 */
function padded_string_fixed_length($str, $awal, $panjang): string
{
    $padding         = '&nbsp;';
    $panjang_padding = strlen($padding);
    $panjang_text    = strlen($str);
    $str             = str_pad($str, ($awal * $panjang_padding) + $panjang_text, $padding, STR_PAD_LEFT);

    return str_pad($str, (($panjang - $panjang_text) * $panjang_padding) + $panjang_text, $padding, STR_PAD_RIGHT);
}

function padded_string_center($str, $panjang)
{
    $padding      = '&nbsp;';
    $panjang_text = strlen($str);
    $to_pad       = ($panjang - $panjang_text) / 2;

    for ($i = 0; $i < $to_pad; $i++) {
        $str = $padding . $str . $padding;
    }

    return $str;
}

function strip_kosong($str)
{
    return empty($str) ? '-' : $str;
}

// Simpan laporan html sebagai file PDF
function buat_pdf(string $isi, string $file, $style = null, $orientation = 'P', $page_size = 'A4'): void
{
    // CSS perlu ditambahkan secara eksplisit
    $style     = $style ?: APPPATH . '../assets/css/report.css';
    $style_isi = "<style>\n " . file_get_contents($style) . "</style>\n" . $isi;

    // Konversi ke PDF menggunakan html2pdf
    try {
        $html2pdf = new Html2Pdf($orientation, $page_size);
        $html2pdf->setDefaultFont('Arial');
        $html2pdf->writeHTML($style_isi);
        $html2pdf->output($file . '.pdf', 'FI');
    } catch (Html2PdfException $e) {
        file_put_contents($file . '_asli', $isi);
        echo $isi;
        echo '<br>================================================<br>';
        $html2pdf->clean();
        $formatter = new ExceptionFormatter($e);
        echo $formatter->getHtmlMessage();
    }
}

if (! function_exists('QRCodeExist')) {
    /**
     * Cek QRCode yang ada di template surat .rtf
     *
     * @param mixed $value
     * @param mixed $awalanQrCode
     *
     * @return Builder
     */
    function QRCodeExist($value, $awalanQrCode = '89504e470d0a1a0a0000000d4948445200000084000000840802000000de'): bool
    {
        $ada = false;
        // Pakai surat ubahan desa apabila ada
        $file = SuratExportDesa($value);
        if ($file == '') {
            $file = "template-surat/{$value}/{$value}.rtf";
        }

        if (is_file($file)) {
            $handle = fopen($file, 'rb');
            $buffer = stream_get_contents($handle);
            $ada    = strpos($buffer, (string) $awalanQrCode) !== false;
            fclose($handle);
        }

        return $ada;
    }

    function kode_isian($urlSurat = null)
    {
        if (null === $urlSurat) {
            return null;
        }

        // Lokasi instalasi SID mungkin di sub-folder
        require_once FCPATH . 'vendor/simplehtmldom/simplehtmldom/simple_html_dom.php';

        $pathBawaan = FCPATH . 'template-surat/' . $urlSurat . '/' . $urlSurat . '.php';
        $pathLokal  = FCPATH . LOKASI_SURAT_DESA . $urlSurat . '/' . $urlSurat . '.php';

        if (file_exists($pathLokal)) {
            $html = file_get_html($pathLokal);
        } elseif (file_exists($pathBawaan)) {
            $html = file_get_html($pathBawaan);
        } else {
            return [];
        }
        // Kumpulkan semua isian (tag input) di form surat
        // Asumsi di form surat, struktur input seperti ini
        // <tr>
        // 		<th>Keterangan Isian</th>
        // 		<td><input><td>
        // </tr>
        $inputs = [];

        foreach ($html->find('input') as $input) {
            if ($input->type == 'hidden') {
                continue;
            }
            if ($input->title == 'Pilih Tanggal') {
                $inputs[$input->name] = $input->parent->parent->parent->children[0]->innertext;

                continue;
            }
            if ($input->type == 'radio') {
                $inputs[$input->name] = $input->parent->parent->parent->children[0]->innertext;

                continue;
            }
            if ($input->id == 'jam_1') {
                $inputs[$input->name] = $input->parent->parent->parent->children[0]->innertext;

                continue;
            }
            if ($input->id == 'input_group') {
                $inputs[$input->name] = $input->parent->parent->parent->children[0]->innertext;

                continue;
            }
            $inputs[$input->name] = $input->parent->parent->children[0]->innertext;
        }

        foreach ($html->find('textarea') as $input) {
            if ($input->type == 'hidden') {
                continue;
            }
            $inputs[$input->name] = $input->parent->parent->children[0]->innertext;
        }

        foreach ($html->find('select') as $input) {
            if ($input->type == 'hidden') {
                continue;
            }
            $inputs[$input->name] = $input->parent->parent->children[0]->innertext;
        }

        $html->clear();

        return $inputs;
    }
}

// Untuk Lampiran
if (! function_exists('kotak')) {
    function kotak(array $data_kolom, $max_kolom = 26): string
    {
        $view = '';

        for ($i = 0; $i < $max_kolom; $i++) {
            $view .= '<td class="kotak padat tengah">';
            if (isset($data_kolom[$i])) {
                $view .= strtoupper($data_kolom[$i]);
            } else {
                $view .= '&nbsp;';
            }
            $view .= '</td>';
        }

        return $view;
    }
}

if (! function_exists('checklist')) {
    function checklist($kondisi_1, $kondisi_2): string
    {
        $view = '<td class="kotak padat tengah">';
        if ($kondisi_1 == $kondisi_2) {
            $view .= '<img src="' . base_url('assets/images/check.png') . '" height="10" width="10"/>';
        }

        return $view . '</td>';
    }
}

function get_key_form_kategori($data, $utama = false)
{
    $kategori = collect($data)->mapWithKeys(static function ($item, $key) {
        $judul = $item->judul ?: str_replace('_', ' ', $key);
        if ($key == 'individu') {
            $judul = 'Utama';
        }

        return [$key => $judul];
    })->toArray();

    if (! $utama) {
        unset($kategori['individu']);
    }

    return $kategori;
}
