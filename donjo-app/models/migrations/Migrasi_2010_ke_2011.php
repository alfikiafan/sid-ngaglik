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

defined('BASEPATH') || exit('No direct script access allowed');

class Migrasi_2010_ke_2011 extends MY_model
{
    public function up()
    {
        $hasil = true;

        $hasil = $hasil && $this->tambah_kolom_ket($hasil);
        // Ubah tipe data field nilai menjadi INT
        $hasil = $hasil && $this->db->query('ALTER TABLE `analisis_parameter` MODIFY COLUMN nilai INT(3) NOT NULL DEFAULT 0');
        $hasil = $hasil && $this->db->query('ALTER TABLE `analisis_parameter` MODIFY COLUMN kode_jawaban INT(3) DEFAULT 0');

        status_sukses($hasil);

        return $hasil;
    }

    private function tambah_kolom_ket(bool $hasil)
    {
        //tambah kolom keterangan di tabel kelompok_anggota
        if (! $this->db->field_exists('keterangan', 'kelompok_anggota')) {
            return $hasil && $this->dbforge->add_column('kelompok_anggota', [
                'keterangan' => [
                    'type' => 'text',
                    'null' => true,
                ],
            ]);
        }

        return $hasil;
    }
}
