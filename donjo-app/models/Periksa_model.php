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

use App\Enums\StatusDasarEnum;
use App\Models\CovidVaksin;
use App\Models\InventarisAsset;
use App\Models\Keluarga;
use App\Models\KlasifikasiSurat;
use App\Models\LogPenduduk;
use App\Models\LogPerubahanPenduduk;
use App\Models\Penduduk;
use App\Models\PendudukMandiri;
use App\Models\RefJabatan;
use App\Models\SettingAplikasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

defined('BASEPATH') || exit('No direct script access allowed');

class Periksa_model extends MY_Model
{
    public $periksa = [];

    public function __construct()
    {
        parent::__construct();
        $this->periksa['migrasi_utk_diulang'] = $this->deteksi_masalah();
    }

    public function getSetting($key)
    {
        return SettingAplikasi::where('key', $key)->pluck('value')->first();
    }

    private function deteksi_masalah()
    {
        $db_error_code    = $this->session->db_error['code'];
        $db_error_message = $this->session->db_error['message'];
        $current_version  = $this->getSetting('current_version');
        $calon            = $current_version;

        // Hanya jalankan periksa model lama jika belum ada app_key di tabel config pada kasus tertentu
        if (! $this->db->field_exists('app_key', 'config')) {
            // tag_id_card belum ada
            if ($db_error_code == 1054 && strpos($db_error_message, 'tag_id_card') !== false) {
                $calon_ini                  = '19.04';
                $this->periksa['masalah'][] = 'coloumn_tag_id_card_doesnt_exist';
                $calon                      = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // Table tweb_penduduk no_kk ganda
            if (! empty($kk_ganda = $this->deteksi_tweb_keluarga_no_kk_ganda())) {
                $calon_ini                    = '19.11';
                $this->periksa['masalah'][]   = 'no_kk_ganda';
                $this->periksa['no_kk_ganda'] = $kk_ganda;
                $calon                        = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // pamong_id belum ada
            if ($db_error_code == 1406) {
                $pos       = strpos($this->session->message_query, "CONCAT_WS('_', kode, id)");
                $calon_ini = $current_version;
                if ($pos !== false) {
                    $calon_ini                     = '20.12';
                    $this->periksa['masalah'][]    = 'kode_kelompok';
                    $this->periksa['kode_panjang'] = $this->deteksi_kode_panjang();
                }
                $calon = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // tag_id_ganda
            $tag_id_ganda_1 = ($db_error_code == 1054 && strpos($db_error_message, 'pamong_id') !== false);
            $tag_id_ganda_2 = ($db_error_code == 1062 && strpos($this->session->message_query, 'ALTER TABLE tweb_penduduk ADD UNIQUE tag_id_card'));
            if ($tag_id_ganda_1 || $tag_id_ganda_2) {
                $calon_ini                     = '21.04';
                $this->periksa['masalah'][]    = 'tag_id_ganda';
                $this->periksa['tag_id_ganda'] = $this->deteksi_tag_id_ganda();
                $calon                         = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // kartu_tempat_lahir atau kartu_alamat berisi null
            if ($db_error_code == 1138) {
                $pos1      = strpos($this->session->message_query, 'kartu_tempat_lahir');
                $pos2      = strpos($this->session->message_query, 'kartu_alamat');
                $calon_ini = $current_version;
                if ($pos1 !== false && $pos2 !== false) {
                    $calon_ini                  = '21.05';
                    $this->periksa['masalah'][] = 'kartu_alamat';
                }
                $calon = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            $id_pengunjung = ($db_error_code == 1054 && strpos($db_error_message, 'id_pengunjung') !== false);
            $tipe          = ($db_error_code == 1054 && strpos($db_error_message, 'tipe') !== false);

            if ($id_pengunjung || $tipe) {
                $this->perbaiki_anjungan();
            }

            // id_cluster Keluarga beserta anggota keluarganya ada yg null
            if ($db_error_code == 1138) {
                $pos       = strpos($this->session->message_query, 'id_cluster');
                $calon_ini = $current_version;
                if ($pos !== false) {
                    $calon_ini                        = '21.07';
                    $this->periksa['masalah'][]       = 'id_cluster_null';
                    $this->periksa['id_cluster_null'] = $this->deteksi_id_cluster_null();
                    $this->periksa['wilayah_pertama'] = $this->wilayah_pertama();
                }
                $calon = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // NIK penduduk ganda
            if ($db_error_code == 1062) {
                $pos       = strpos($this->session->message_query, 'ALTER TABLE tweb_penduduk ADD UNIQUE nik');
                $calon_ini = $current_version;
                if ($pos !== false) {
                    $calon_ini                  = '21.09';
                    $this->periksa['masalah'][] = 'nik_ganda';
                    $this->periksa['nik_ganda'] = $this->deteksi_nik_ganda();
                }
                $calon = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // No KK terlalu panjang
            if ($db_error_code == 1265 || $db_error_code == 1406) {
                $pos1 = strpos($db_error_message, "Data too long for column 'no_kk' ");
                $pos2 = strpos($db_error_message, "Data truncated for column 'no_kk'");
                log_message('error', $pos2);
                $calon_ini = $current_version;
                if ($pos1 !== false || $pos2 !== false) {
                    log_message('error', 'kk_panjang');
                    $calon_ini                   = '21.11';
                    $this->periksa['masalah'][]  = 'kk_panjang';
                    $this->periksa['kk_panjang'] = $this->deteksi_kk_panjang();
                }
                $calon = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // email user ganda
            if ($db_error_code == 1062) {
                $pos       = strpos($this->session->message_query, 'ALTER TABLE user ADD UNIQUE email');
                $calon_ini = $current_version;
                if ($pos !== false) {
                    $calon_ini                         = '22.02';
                    $this->periksa['masalah'][]        = 'email_user_ganda';
                    $this->periksa['email_user_ganda'] = $this->deteksi_email_user_ganda();
                }
                $calon = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // username user ganda
            if (! empty($username = $this->deteksi_username_user_ganda())) {
                $calon_ini                            = '22.02';
                $this->periksa['masalah'][]           = 'username_user_ganda';
                $this->periksa['username_user_ganda'] = $username;
                $calon                                = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // Email penduduk ganda, menyebabkan migrasi 22.02 gagal.
            if ($db_error_code == 1062) {
                $pos       = strpos($this->session->message_query, 'ALTER TABLE tweb_penduduk ADD UNIQUE email');
                $calon_ini = '22.02';
                if ($pos !== false) {
                    $calon_ini                    = '22.02';
                    $this->periksa['masalah'][]   = 'email_ganda';
                    $this->periksa['email_ganda'] = $this->deteksi_email_ganda();
                }
                $calon = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }

            // Error table doesn't exist
            if ($db_error_code === 1146) {
                $calon_ini                  = $this->deteksi_table_doesnt_exist($db_error_message);
                $this->periksa['masalah'][] = 'table_not_exist';
                $calon                      = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
            }
        }

        // error config_id
        if ($db_error_code == 1054) {
            $calon_ini                  = '23.04';
            $this->periksa['masalah'][] = 'config_id';
            $calon                      = version_compare($calon, $calon_ini, '<') ? $calon : $calon_ini;
        }

        // Deteksi jabatan kades atau sekdes tidak ada
        if (! empty($jabatan = $this->deteksi_jabatan())) {
            $this->periksa['masalah'][]    = 'data_jabatan_tidak_ada';
            $this->periksa['data_jabatan'] = $jabatan;
        }

        $zero_date_default_value = $this->deteksi_zero_date_default_value();
        if (! empty($zero_date_default_value)) {
            $this->periksa['masalah'][]               = 'zero_date_default_value';
            $this->periksa['zero_date_default_value'] = $zero_date_default_value;
        }

        // Tabel ref_persil_kelas dan tabel ref inventaris lain terhapus isinya
        if ($db_error_code == 99001 && preg_match('/ref_persil_kelas|ref_persil_mutasi|ref_peruntukan_tanah_kas/', $db_error_message)) {
            $this->periksa['masalah'][] = 'ref_inventaris_kosong';
        }

        // Error invalid date
        if (! empty($tabel_invalid_date = $this->deteksi_invalid_date())) {
            $this->periksa['masalah'][]          = 'tabel_invalid_date';
            $this->periksa['tabel_invalid_date'] = $tabel_invalid_date;
        }

        // Autoincrement hilang, mungkin karena proses backup/restore yang tidak sempurna
        // Untuk masalah yg tidak melalui exception, letakkan sesuai urut migrasi
        if ($db_error_code == 1364) {
            $pos = strpos($db_error_message, "Field 'id' doesn't have a default value");
            if ($pos !== false) {
                $this->periksa['masalah'][] = 'autoincrement';
            }
        }

        // Error collation table
        $collation_table = $this->deteksi_collation_table_tidak_sesuai();
        $error_msg       = strpos($this->session->message_query, 'Illegal mix of collations');
        if (! empty($collation_table) || $error_msg) {
            $this->periksa['masalah'][]       = 'collation';
            $this->periksa['collation_table'] = $collation_table;
        }

        // Error penduduk tanpa ada keluarga di tweb_keluarga
        $penduduk_tanpa_keluarga = $this->deteksi_penduduk_tanpa_keluarga();

        if (! $penduduk_tanpa_keluarga->isEmpty()) {
            $this->periksa['masalah'][]               = 'penduduk_tanpa_keluarga';
            $this->periksa['penduduk_tanpa_keluarga'] = $penduduk_tanpa_keluarga->toArray();
        }

        $log_penduduk_tidak_sinkron = $this->deteksi_log_penduduk_tidak_sinkron();
        if (! $log_penduduk_tidak_sinkron->isEmpty()) {
            $this->periksa['masalah'][]                  = 'log_penduduk_tidak_sinkron';
            $this->periksa['log_penduduk_tidak_sinkron'] = $log_penduduk_tidak_sinkron->toArray();
        }

        $log_penduduk_null = $this->deteksi_log_penduduk_null();
        if (! $log_penduduk_null->isEmpty()) {
            $this->periksa['masalah'][]         = 'log_penduduk_null';
            $this->periksa['log_penduduk_null'] = $log_penduduk_null->toArray();
        }

        $log_keluarga_bermasalah = $this->deteksi_log_keluarga_bermasalah();
        if (! $log_keluarga_bermasalah->isEmpty()) {
            $this->periksa['masalah'][]               = 'log_keluarga_bermasalah';
            $this->periksa['log_keluarga_bermasalah'] = $log_keluarga_bermasalah->toArray();
        }

        $log_keluarga_ganda = $this->deteksi_log_keluarga_ganda();
        if (! $log_keluarga_ganda->isEmpty()) {
            $this->periksa['masalah'][]          = 'log_keluarga_ganda';
            $this->periksa['log_keluarga_ganda'] = $log_keluarga_ganda->toArray();
        }

        // deteksi no anggota ganda
        $no_anggota_ganda = $this->deteksi_no_anggota_ganda();
        if (! $no_anggota_ganda->isEmpty()) {
            $this->periksa['masalah'][]        = 'no_anggota_ganda';
            $this->periksa['no_anggota_ganda'] = $no_anggota_ganda->toArray();
        }

        $klasifikasi_surat_ganda = $this->deteksi_klasifikasi_surat_ganda();
        if (! $klasifikasi_surat_ganda->isEmpty()) {
            $this->periksa['masalah'][]               = 'klasifikasi_surat_ganda';
            $this->periksa['klasifikasi_surat_ganda'] = $klasifikasi_surat_ganda->toArray();
        }

        return $calon;
    }

    private function wilayah_pertama()
    {
        $wilayah_pertama = [];
        // Ambil sebutan dusun
        $sebutan_dusun = setting('sebutan_dusun');

        // Ambil wilayah pada keluarga pertama yg tidak kosong
        $id_wil = $this->db
            ->select('id_cluster')
            ->where('id_cluster IS NOT NULL')
            ->order_by('id')
            ->limit(1)
            ->get('tweb_keluarga')
            ->row()->id_cluster;
        $wilayah_pertama['id'] = $id_wil;
        $wil                   = $this->db
            ->select('dusun, rw, rt')
            ->where('id', $id_wil)
            ->get('tweb_wil_clusterdesa')
            ->row();
        if ($wil->dusun) {
            $wilayah_pertama['wil'] .= strtoupper($sebutan_dusun) . ' ' . $wil->dusun . ' ';
        }
        if ($wil->rw) {
            $wilayah_pertama['wil'] .= 'RW ' . $wil->rw . ' ';
        }
        if ($wil->rt) {
            $wilayah_pertama['wil'] .= 'RT ' . $wil->rt . ' ';
        }

        return $wilayah_pertama;
    }

    private function deteksi_tag_id_ganda()
    {
        return $this->config_id_exist('tweb_penduduk')
            ->select('tag_id_card, COUNT(tag_id_card) as jml')
            ->from('tweb_penduduk')
            ->group_by('tag_id_card')
            ->having('jml > 1')
            ->get()
            ->result_array();
    }

    private function deteksi_kode_panjang()
    {
        // Jika di CONCAT akan melebihi panjang yg diperbolehkan untuk kode (16)
        return $this->config_id_exist('kelompok')
            ->select('id, kode')
            ->from('kelompok')
            ->where('CHAR_LENGTH(kode) + CHAR_LENGTH(id) + 1 > 16')
            ->get()
            ->result_array();
    }

    private function deteksi_kk_panjang()
    {
        // No KK melebihi 16 karakter
        return $this->config_id_exist('tweb_keluarga')
            ->select('id, no_kk, CHAR_LENGTH(no_kk) AS panjang')
            ->from('tweb_keluarga')
            ->where('CHAR_LENGTH(no_kk) > 16')
            ->get()
            ->result_array();
    }

    private function deteksi_id_cluster_null()
    {
        return $this->config_id_exist('tweb_keluarga', 'k')
            ->select('no_kk, p.nama')
            ->from('tweb_keluarga k')
            ->join('tweb_penduduk p', 'p.id = k.nik_kepala', 'left')
            ->where('k.id_cluster IS NULL')
            ->get()
            ->result_array();
    }

    private function deteksi_nik_ganda()
    {
        return $this->config_id_exist('tweb_penduduk')
            ->select('nik, COUNT(id) as jml')
            ->from('tweb_penduduk')
            ->group_by('nik')
            ->having('jml >', 1)
            ->get()
            ->result_array();
    }

    private function deteksi_email_ganda()
    {
        $email = $this->config_id_exist('tweb_penduduk', 'p')
            ->select('email, COUNT(id) as jml')
            ->from('tweb_penduduk p')
            ->where('email IS NOT NULL')
            ->group_by('email')
            ->having('jml >', 1)
            ->get()
            ->result_array();
        if (empty($email)) {
            return $email;
        }
        if (! empty($this->session->db_error)) {
            return $email;
        }
        // Berikan kode kustom karena exception dihindari di migrasi.
        $this->session->db_error = [
            'code'    => 99001,
            'message' => 'Email penduduk ganda',
        ];

        return $email;
    }

    private function deteksi_email_user_ganda()
    {
        return $this->config_id_exist('user')
            ->select('email, COUNT(id) as jml')
            ->from('user')
            ->where('email IS NOT NULL')
            ->group_by('email')
            ->having('jml >', 1)
            ->get()
            ->result_array();
    }

    private function deteksi_username_user_ganda()
    {
        return $this->config_id_exist('user')
            ->select('username, COUNT(id) as jml')
            ->from('user')
            ->where('username IS NOT NULL')
            ->group_by('username')
            ->having('jml >', 1)
            ->get()
            ->result_array();
    }

    private function deteksi_tweb_keluarga_no_kk_ganda()
    {
        return $this->config_id_exist('tweb_keluarga')
            ->select('no_kk, COUNT(id) as jml')
            ->from('tweb_keluarga')
            ->group_by('no_kk')
            ->having('jml >', 1)
            ->get()
            ->result_array();
    }

    private function deteksi_collation_table_tidak_sesuai()
    {
        return $this->db
            ->query("SELECT TABLE_NAME, TABLE_COLLATION FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = '{$this->db->database}' AND TABLE_COLLATION != '{$this->db->dbcollat}'")
            ->result_array();
    }

    private function deteksi_invalid_date()
    {
        $tabel       = [];
        $logPenduduk = LogPenduduk::select('id')
            ->when(Schema::hasColumn('log_penduduk', 'updated_at'), static fn ($query) => $query->addSelect('updated_at'))
            ->when(Schema::hasColumn('log_penduduk', 'created_at'), static fn ($query) => $query->addSelect('created_at')->orWhereDate('created_at', '0000-00-00'))
            ->when(Schema::hasColumn('log_penduduk', 'tgl_lapor'), static fn ($query) => $query->addSelect('tgl_lapor')->orWhereDate('tgl_lapor', '0000-00-00'))
            ->when(Schema::hasColumn('log_penduduk', 'tgl_peristiwa'), static fn ($query) => $query->addSelect('tgl_peristiwa')->orWhereDate('tgl_peristiwa', '0000-00-00'))
            ->get();

        if ($logPenduduk->count() > 0) {
            $tabel['log_penduduk'] = $logPenduduk;
        }

        // Tabel log_perubahan_penduduk
        $logPerubahanPenduduk = LogPerubahanPenduduk::select(['id', 'id_pend', 'tanggal'])
            ->whereDate('tanggal', '0000-00-00')
            ->get();

        if ($logPerubahanPenduduk->count() > 0) {
            $tabel['log_perubahan_penduduk'] = $logPerubahanPenduduk;
        }

        // Tabel penduduk_mandiri
        $pendudukMandiri = PendudukMandiri::select(['id_pend', 'tanggal_buat'])
            ->when(Schema::hasColumn('tweb_penduduk_mandiri', 'updated_at'), static fn ($query) => $query->addSelect('updated_at')->orWhereDate('updated_at', '0000-00-00'))
            ->get();

        if ($pendudukMandiri->count() > 0) {
            $tabel['tweb_penduduk_mandiri'] = $pendudukMandiri;
        }

        //deteksi di modul inventaris
        $inventaris_asset = InventarisAsset::select(['id', 'updated_at'])->where('updated_at', '0000-00-00')->get();
        if ($inventaris_asset->count() > 0) {
            $tabel['inventaris_asset'] = $inventaris_asset;
        }

        // Tabel covid19_vaksin
        $covidVaksin = CovidVaksin::select(['id_penduduk', 'tgl_vaksin_1', 'tgl_vaksin_2', 'tgl_vaksin_3'])->where(
            static fn ($q) => $q->where('tgl_vaksin_1', '0000-00-00')
                ->orwhere('tgl_vaksin_2', '0000-00-00')
                ->orwhere('tgl_vaksin_3', '0000-00-00')
        )->get();

        if ($covidVaksin->count() > 0) {
            $tabel['covid19_vaksin'] = $covidVaksin;
        }

        // tabel tweb_penduduk column tanggal_cetak_ktp
        $tanggal_cetak_ktp = Penduduk::setEagerLoads([])->where('tanggal_cetak_ktp', '0000-00-00')->get();

        if ($tanggal_cetak_ktp->count() > 0) {
            $tabel['tweb_penduduk'] = $tanggal_cetak_ktp;
        }

        return $tabel;
    }

    private function deteksi_jabatan()
    {
        $jabatan = [];
        // perlu cek ketika insert ? apakah ada data jenis 1/2
        // penggantian find ke where 'jenis'. cari data jabatan berdasarkan jenis. ?

        if (Schema::hasTable('ref_jabatan')) {
            $user = auth()->id ?? User::first()->id;

            // Cek jabatan kades
            if (! kades()) {
                $jabatan[] = [
                    'config_id'  => identitas('id'),
                    'nama'       => 'Kepala ' . ucwords($this->getSetting('sebutan_desa')),
                    'jenis'      => RefJabatan::KADES,
                    'created_by' => $user,
                    'updated_by' => $user,
                ];
            }

            // Cek jabatan sekdes
            if (! sekdes()) {
                $jabatan[] = [
                    'config_id'  => identitas('id'),
                    'nama'       => 'Sekretaris',
                    'jenis'      => RefJabatan::SEKDES,
                    'created_by' => $user,
                    'updated_by' => $user,
                ];
            }
        }

        return $jabatan;
    }

    private function deteksi_table_doesnt_exist($table = null)
    {
        $database = $this->db->database;
        $table    = str_replace(["Table '", $database, '.', "' doesn't exist"], '', $table);

        switch ($table) {
            case 'ref_penduduk_hamil':
                return '22.02';

            case 'user_grup':
                return '19.03';

            case 'setting_aplikasi_options':
                return '18.11';

            default:
                return null;
        }
    }

    public function deteksi_zero_date_default_value()
    {
        return $this->db
            ->query("SELECT table_schema, table_name, column_name, column_default, data_type FROM INFORMATION_SCHEMA.columns WHERE column_default IS NOT NULL  AND table_schema NOT IN ( 'information_schema', 'sys', 'performance_schema', 'mysql' ) and table_schema = '{$this->db->database}' and column_default = \"'0000-00-00 00:00:00'\"")
            ->result_array();
    }

    public function deteksi_penduduk_tanpa_keluarga()
    {
        $config_id = identitas('id');

        return Penduduk::select('id', 'nama', 'nik', 'id_cluster', 'id_kk', 'alamat_sekarang', 'created_at')
            ->kepalaKeluarga()
            ->whereNotNull('id_kk')
            ->wheredoesntHave('keluarga', static fn ($q) => $q->where('config_id', $config_id))
            ->get();
    }

    // status dasar penduduk seharusnya mengikuti status terakhir dari log_penduduk
    public function deteksi_log_penduduk_tidak_sinkron()
    {
        $config_id = identitas('id');

        $sqlRaw                = "( SELECT MAX(id) max_id, id_pend FROM log_penduduk where config_id = {$config_id} GROUP BY  id_pend)";
        $statusDasarBukanHidup = Penduduk::select('tweb_penduduk.id', 'nama', 'nik', 'status_dasar', 'alamat_sekarang', 'kode_peristiwa', 'tweb_penduduk.created_at')
            ->where('status_dasar', '=', StatusDasarEnum::HIDUP)
            ->join(DB::raw("({$sqlRaw}) as log"), 'log.id_pend', '=', 'tweb_penduduk.id')
            ->join('log_penduduk', static function ($q) use ($config_id): void {
                $q->on('log_penduduk.id', '=', 'log.max_id')
                    ->where('log_penduduk.config_id', $config_id)
                    ->whereIn('kode_peristiwa', [LogPenduduk::MATI, LogPenduduk::PINDAH_KELUAR, LogPenduduk::HILANG, LogPenduduk::TIDAK_TETAP_PERGI]);
            });

        return Penduduk::select('tweb_penduduk.id', 'nama', 'nik', 'status_dasar', 'alamat_sekarang', 'kode_peristiwa', 'tweb_penduduk.created_at')
            ->where('status_dasar', '!=', StatusDasarEnum::HIDUP)
            ->join(DB::raw("({$sqlRaw}) as log"), 'log.id_pend', '=', 'tweb_penduduk.id')
            ->join('log_penduduk', static function ($q) use ($config_id): void {
                $q->on('log_penduduk.id', '=', 'log.max_id')
                    ->where('log_penduduk.config_id', $config_id)
                    ->whereNotIn('kode_peristiwa', [LogPenduduk::MATI, LogPenduduk::PINDAH_KELUAR, LogPenduduk::HILANG, LogPenduduk::TIDAK_TETAP_PERGI]);
            })->union(
                $statusDasarBukanHidup
            )
            ->get();
    }

    public function deteksi_log_penduduk_null()
    {
        identitas('id');

        return LogPenduduk::select('log_penduduk.id', 'nama', 'nik', 'kode_peristiwa', 'log_penduduk.created_at')
            ->whereNull('kode_peristiwa')
            ->join('tweb_penduduk', 'tweb_penduduk.id', '=', 'log_penduduk.id_pend')
            ->get();
    }

    public function deteksi_log_keluarga_bermasalah()
    {
        return Keluarga::whereDoesntHave('LogKeluarga')->get();
    }

    public function deteksi_log_keluarga_ganda()
    {
        $config_id = identitas('id');

        return Keluarga::whereIn('id', static fn ($query) => $query->from('log_keluarga')->where(['config_id' => $config_id])->select(['id_kk'])->groupBy(['id_kk', 'tgl_peristiwa'])->having(DB::raw('count(tgl_peristiwa)'), '>', 1))->get();
    }

    private function deteksi_no_anggota_ganda()
    {
        return DB::table('kelompok_anggota')
            ->selectRaw('count(id) as jml, config_id, id_kelompok, no_anggota')
            ->groupBy('config_id', 'id_kelompok', 'no_anggota')
            ->having('jml', '>', 1)
            ->get();
    }

    private function deteksi_klasifikasi_surat_ganda()
    {
        $config_id = identitas('id');

        return KlasifikasiSurat::where(['config_id' => $config_id])->whereIn('kode', static fn ($q) => $q->from('klasifikasi_surat')->select(['kode'])->where(['config_id' => $config_id])->groupBy('kode')->having(DB::raw('count(kode)'), '>', 1))->orderBy('kode')->get();
    }

    public function perbaiki(): void
    {
        // TODO: login
        $this->session->user_id = $this->session->user_id ?: 1;

        // Perbaiki masalah data yg terdeteksi untuk error yg dilaporkan
        log_message('error', '========= Perbaiki masalah data =========');

        foreach ($this->periksa['masalah'] as $masalah_ini) {
            $this->selesaikan_masalah($masalah_ini);
        }
        $this->session->db_error = null;

        // Ulangi migrasi mulai dari migrasi_utk_diulang
        if (! $this->db->table_exists('migrasi') && ! $this->field_exists('app_key', 'config')) {
            $this->dbforge->add_field([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'auto_increment' => true,
                ],
                'versi_database' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => false,
                ],
                'premium' => [
                    'type' => 'text',
                    'null' => true,
                ],
            ]);
            $this->dbforge->add_key('id', true);
            $this->dbforge->create_table('migrasi', true);
        }
        $this->db
            ->where('versi_database', VERSI_DATABASE)
            ->delete('migrasi');
        $this->db
            ->set('value', $this->periksa['migrasi_utk_diulang'])
            ->where('key', 'current_version')
            ->update('setting_aplikasi');
        $this->cache->hapus_cache_untuk_semua('setting_aplikasi');
        $this->load->model('database_model');
        $this->database_model->migrasi_db_cri();
    }

    public function perbaiki_sebagian($masalah_ini): void
    {
        // TODO: login
        $this->session->user_id = $this->session->user_id ?: 1;

        $this->selesaikan_masalah($masalah_ini);

        $this->session->db_error = null;
    }

    private function perbaiki_kode_kelompok(): void
    {
        if (empty($this->periksa['kode_panjang'])) {
            return;
        }

        $kode = array_column($this->periksa['kode_panjang'], 'kode');
        log_message('error', 'Kode kelompok berikut telah diperpendek: ' . print_r($this->periksa['kode_panjang'], true));
        // Ubah kode kelompok dengan panjang id + 1
        $this->db
            ->set('kode', 'SUBSTRING(kode, 1, (CHAR_LENGTH(kode) - CHAR_LENGTH(id) - 1))', false)
            ->where_in('kode', $kode)
            ->update('kelompok');
    }

    // Isi kembali tabel referensi:
    // ref_persil_kelas, ref_persil_mutasi, ref_peruntukan_tanah_kas
    private function perbaiki_referensi_kosong(): void
    {
        $this->load->model('migrations/Migrasi_2007_ke_2008', 'migrasi1');
        $this->migrasi1->buat_ref_persil_kelas();
        $this->migrasi1->buat_ref_persil_mutasi();
        $this->load->model('migrations/Migrasi_fitur_premium_2106', 'migrasi2');
        $this->migrasi2->add_value_ref_peruntukan_tanah_kas(true);
        log_message('error', 'Isi tabel ref_persil_kelas, ref_persil_mutasi dan ref_peruntukan_tanah_kas telah dikembalikan');
    }

    // Hanya terjadi pada keluarga yg tidak memiliki anggota lagi
    private function perbaiki_id_cluster_null(): void
    {
        if (empty($this->periksa['id_cluster_null'])) {
            return;
        }

        $kel_kosong = array_column($this->periksa['id_cluster_null'], 'no_kk');
        log_message('error', 'Lokasi keluarga berikut telah diubah menjadi ' . $this->periksa['wilayah_pertama']['wil'] . ' : ' . print_r($kel_kosong, true));

        // Ganti id_cluster kosong dengan wilayah pertama
        $this->db
            ->set('id_cluster', $this->periksa['wilayah_pertama']['id'])
            ->where('id_cluster IS NULL')
            ->update('tweb_keluarga');
    }

    // Migrasi 23.11 gagal jika ada no anggota ganda
    private function perbaiki_no_anggota_ganda(): void
    {
        $no_anggota_ganda = $this->periksa['no_anggota_ganda'];

        if ($no_anggota_ganda) {
            foreach ($no_anggota_ganda as $value) {
                $duplikat = DB::table('kelompok_anggota')
                    ->selectRaw('id, id_penduduk, config_id, id_kelompok, no_anggota')
                    ->where('config_id', $value->config_id)
                    ->where('id_kelompok', $value->id_kelompok)
                    ->where('no_anggota', $value->no_anggota)
                    ->get();

                foreach ($duplikat as $item) {
                    DB::table('kelompok_anggota')
                        ->where('id', $item->id)
                        ->where('config_id', $item->config_id)
                        ->where('id_kelompok', $item->id_kelompok)
                        ->where('no_anggota', $item->no_anggota)
                        ->update(['no_anggota' => $item->id_penduduk]);

                    log_message('notice', 'No Anggota berikut telah diubah sbb : ' . print_r(['No Anggota Lama' => $item->no_anggota, 'No Anggota Baru' => $item->id_penduduk], true));
                }
            }
        }
    }

    // Migrasi 21.09 gagal jika ada NIK ganda
    private function perbaiki_nik_ganda(): void
    {
        if (empty($this->periksa['nik_ganda'])) {
            return;
        }

        $this->load->model('penduduk_model');

        // Catat semua NIK bukan numerik
        $nik_ganda = $this->db
            ->select('id, nik as nik_ganda, nama')
            ->where("nik NOT REGEXP '^[0-9]+$'")
            ->get('tweb_penduduk')
            ->result_array();

        // Catat semua NIK ganda numerik selain pertama
        foreach ($this->periksa['nik_ganda'] as $nik) {
            if (! is_numeric($nik['nik'])) {
                continue;
            }
            $daftar_nik = $this->db
                ->select('id, nik as nik_ganda, nama')
                ->from('tweb_penduduk')
                ->where('nik', $nik['nik'])
                ->get()
                ->result_array();
            array_shift($daftar_nik);

            $nik_ganda = array_merge($nik_ganda, $daftar_nik);
        }

        // Catat NIK sementara untuk NIK ganda yg akan diubah
        $nik_sementara = $this->penduduk_model->nik_sementara();
        $nik_sementara = str_split($nik_sementara, 11);

        foreach ($nik_ganda as $key => $nik) {
            $urut                             = sprintf('%05d', $nik_sementara[1] + $key);
            $nik_ganda[$key]['nik_sementara'] = $nik_sementara[0] . $urut;
        }

        // Ubah NIK ganda dengan menjadi NIK sementara
        foreach ($nik_ganda as $nik) {
            $this->db
                ->set('nik', $nik['nik_sementara'])
                ->where('id', $nik['id'])
                ->update('tweb_penduduk');
        }
        log_message('error', 'NIK penduduk berikut telah diubah menjadi NIK semenntara: ' . print_r($nik_ganda, true));
    }

    // Migrasi 22.02 sengaja gagal jika ada email ganda
    private function perbaiki_email(): void
    {
        if (empty($this->periksa['email_ganda'])) {
            return;
        }

        // Ubah semua email kosong menjadi null
        $this->db
            ->set('email', null)
            ->where('email', '')
            ->update('tweb_penduduk');
        // Ubah semua email ganda
        $email_ganda = [];

        foreach ($this->periksa['email_ganda'] as $email) {
            if (empty($email['email'])) {
                continue;
            }
            $daftar_ubah = $this->db
                ->select('id, nik, nama, CONCAT(id, "_", email)')
                ->from('tweb_penduduk')
                ->where('email', $email['email'])
                ->get()
                ->result_array();
            log_message('error', 'Email penduduk berikut telah diubah dengan menambah id: ' . print_r($daftar_ubah, true));
            $email_ganda = [...$email_ganda, ...array_column($daftar_ubah, 'id')];
        }
        // Ubah email ganda dengan menambah id
        $this->db
            ->set('email', 'CONCAT(id, "_", email)', false)
            ->where_in('id', $email_ganda)
            ->update('tweb_penduduk');
    }

    // Migrasi 21.11 gagal jika ada no KK melebihi 16 karakter
    private function perbaiki_kk_panjang(): void
    {
        if (empty($this->periksa['kk_panjang'])) {
            return;
        }

        $this->load->model('keluarga_model');

        // Catat semua No KK panjang
        $kk_panjang = $this->db
            ->select('id, no_kk as kk_panjang, nik_kepala')
            ->where('CHAR_LENGTH(no_kk) > 16')
            ->get('tweb_keluarga')
            ->result_array();

        // Catat No KK sementara untuk No KK panjang yg akan diubah
        $nokk_sementara = $this->keluarga_model->nokk_sementara();
        $nokk_sementara = str_split($nokk_sementara, 11);

        foreach ($kk_panjang as $key => $kk) {
            $urut                               = sprintf('%05d', $nokk_sementara[1] + $key);
            $kk_panjang[$key]['nokk_sementara'] = $nokk_sementara[0] . $urut;
        }

        // Ubah No KK panjang menjadi No KK sementara
        foreach ($kk_panjang as $kk) {
            $this->db
                ->set('no_kk', $kk['nokk_sementara'])
                ->where('id', $kk['id'])
                ->update('tweb_keluarga');
        }
        log_message('error', ' No KK berikut telah diubah menjadi No KK sementara: ' . print_r($kk_panjang, true));
    }

    private function perbaiki_no_kk_ganda(): void
    {
        $this->load->model('keluarga_model');

        if (empty($this->periksa['no_kk_ganda'])) {
            return;
        }

        $duplikat = $this->db
            ->select('id')
            ->where_in('no_kk', 'SELECT tk.no_kk FROM tweb_keluarga as tk GROUP BY tk.no_kk HAVING COUNT(tk.no_kk) > 1', false)
            ->get('tweb_keluarga')
            ->result_array();

        // Ubah No KK panjang menjadi No KK sementara
        foreach ($duplikat as $item) {
            $this->db
                ->set('no_kk', $this->keluarga_model->nokk_sementara())
                ->where('id', $item['id'])
                ->update('tweb_keluarga');
        }

        log_message('error', ' No KK berikut telah diubah menjadi No KK sementara: ' . print_r($this->periksa['no_kk_ganda'], true));
    }

    // Migrasi 22.02 gagal jika ada email user ganda
    private function perbaiki_email_user(): void
    {
        if (empty($this->periksa['email_user_ganda'])) {
            return;
        }

        // Ubah semua email kosong menjadi null
        $this->db
            ->set('email', null)
            ->where('email', '')
            ->update('user');
        // Ubah semua email ganda
        $email_ganda = [];

        foreach ($this->periksa['email_user_ganda'] as $email) {
            if (empty($email['email'])) {
                continue;
            }
            $daftar_ubah = $this->db
                ->select('id, username, nama, CONCAT(id, "_", email)')
                ->from('user')
                ->where('email', $email['email'])
                ->get()
                ->result_array();
            log_message('error', 'Email user berikut telah diubah dengan menambah id: ' . print_r($daftar_ubah, true));
            $email_ganda = [...$email_ganda, ...array_column($daftar_ubah, 'id')];
        }
        // Ubah email user ganda dengan menambah id
        $this->db
            ->set('email', 'CONCAT(id, "_", email)', false)
            ->where_in('id', $email_ganda)
            ->update('user');
    }

    // Migrasi 22.02 gagal jika ada username user ganda
    private function perbaiki_username_user(): void
    {
        if (empty($this->periksa['username_user_ganda'])) {
            return;
        }

        // Ubah semua usernamer ganda
        $username_ganda = [];

        foreach ($this->periksa['username_user_ganda'] as $username) {
            if (empty($username['username'])) {
                continue;
            }
            $daftar_ubah = $this->db
                ->select('id, username, nama, CONCAT(id, "_", username)')
                ->from('user')
                ->where('username', $username['username'])
                ->get()
                ->result_array();
            log_message('error', 'Username user berikut telah diubah dengan menambah id: ' . print_r($daftar_ubah, true));
            $username_ganda = [...$username_ganda, ...array_column($daftar_ubah, 'id')];
        }

        // Ubah username user ganda dengan menambah id dan set tidak aktif
        if ($username_ganda !== []) {
            $this->db
                ->set('username', 'CONCAT(id, "_", username)', false)
                ->set('active', 0)
                ->where('id !=', 1)
                ->where_in('id', $username_ganda)
                ->update('user');
        }

        // Ubah semua username kosong menjadi null
        $this->db
            ->set('username', null)
            ->set('active', 0)
            ->where('username', '')
            ->update('user');
    }

    // Migrasi 21.04 tidak antiCONCAT(id, "_", email)sipasi tag_id_card ganda
    private function perbaiki_tag_id(): void
    {
        if (empty($this->periksa['tag_id_ganda'])) {
            return;
        }
        $daftar_tag_id = [];

        // Ubah semua tag_id_card kosong menjadi null
        $this->db
            ->set('tag_id_card', null)
            ->where('tag_id_card', '')
            ->update('tweb_penduduk');

        // Ubah tag_id_card ganda menjadi kosong selain yg pertama
        foreach ($this->periksa['tag_id_ganda'] as $tag_ganda) {
            if (empty($tag_ganda['tag_id_card'])) {
                continue;
            }
            $select_pertama = $this->db
                ->select('MIN(id) as id')
                ->from('tweb_penduduk')
                ->where('tag_id_card', $tag_ganda['tag_id_card'])
                ->group_by('tag_id_card')
                ->get_compiled_select();
            $daftar_ubah = $this->db
                ->select('p.id, nik, nama, tag_id_card')
                ->from('tweb_penduduk p')
                ->join('(' . $select_pertama . ') p_pertama', 'p.id > p_pertama.id')
                ->where('p.tag_id_card', $tag_ganda['tag_id_card'])
                ->get()
                ->result_array();
            log_message('error', 'Ubah tag_id_card penduduk berikut menjadi kosong: ' . print_r($daftar_ubah, true));
            $daftar_tag_id = [...$daftar_tag_id, ...array_column($daftar_ubah, 'id')];
        }
        // Ubah tag_id ganda menjadi kosong
        $this->db
            ->set('tag_id_card', null)
            ->where_in('id', $daftar_tag_id)
            ->update('tweb_penduduk');
    }

    // Migrasi 21.05 tidak antisipasi kartu_tempat_lahir atau kartu_alamat berisi null
    private function perbaiki_kartu_alamat(): void
    {
        $this->db
            ->set('kartu_tempat_lahir', '')
            ->where('kartu_tempat_lahir is null')
            ->update('program_peserta');
        $this->db
            ->set('kartu_alamat', '')
            ->where('kartu_alamat is null')
            ->update('program_peserta');
        log_message('error', "Kolom kartu_tempat_lahir dan kartu_alamat peserta bantuan yang berisi null telah diubah menjadi '' ");
    }

    private function perbaiki_autoincrement()
    {
        $hasil = true;

        // Tabel yang tidak memerlukan Auto_Increment
        $exclude_table = [
            'analisis_respon',
            'analisis_respon_hasil',
            'captcha_codes',
            'password_resets',
            'sentitems', // Belum tau bentuk datanya bagamana
            'sys_traffic',
            'tweb_penduduk_mandiri',
            'tweb_penduduk_map', // id pada tabel tweb_penduduk_map == penduduk.id (buka id untuk AI)
        ];

        // Auto_Increment hanya diterapkan pada kolom berikut
        $only_pk = [
            'id',
            'id_kontak',
            'id_aset',
        ];

        // Daftar tabel yang tidak memiliki Auto_Increment
        $tables = $this->db->query("SELECT `TABLE_NAME` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_SCHEMA = '{$this->db->database}' AND AUTO_INCREMENT IS NULL");

        foreach ($tables->result() as $tbl) {
            $name = $tbl->TABLE_NAME;
            if (! in_array($name, $exclude_table) && in_array($key = $this->db->list_fields($name)[0], $only_pk)) {
                $fields = [
                    $key => [
                        'type'           => 'INT',
                        'constraint'     => 11,
                        'auto_increment' => true,
                    ],
                ];

                $this->db->simple_query('SET FOREIGN_KEY_CHECKS=0');
                if ($hasil = $hasil && $this->dbforge->modify_column($name, $fields)) {
                    log_message('error', "Auto_Increment pada tabel {$name} dengan kolom {$key} telah ditambahkan.");
                }
                $this->db->simple_query('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        return $hasil;
    }

    private function perbaiki_collation_table()
    {
        $hasil  = true;
        $tables = $this->periksa['collation_table'];

        if ($tables) {
            foreach ($tables as $tbl) {
                if ($this->db->table_exists($tbl['TABLE_NAME'])) {
                    $hasil = $hasil && $this->db->query("ALTER TABLE {$tbl['TABLE_NAME']} CONVERT TO CHARACTER SET utf8 COLLATE {$this->db->dbcollat}");

                    log_message('error', 'Tabel ' . $tbl['TABLE_NAME'] . ' collation diubah dari ' . $tbl['TABLE_COLLATION'] . " menjadi {$this->db->dbcollat}.");
                }
            }
        }

        return $hasil;
    }

    private function perbaiki_invalid_date()
    {
        $hasil = true;

        // Tabel log_penduduk
        if ($logPenduduk = $this->periksa['tabel_invalid_date']['log_penduduk']) {
            log_message('error', 'ada log_penduduk');

            foreach ($logPenduduk as $log) {
                $update = LogPenduduk::find($log->id);

                // cek kolom update_at
                if ($log->updated_at == null) {
                    $update->timestamps = false;
                }

                // created_at, ambil data dari updated_at
                if ($log->created_at != null && $log->created_at->format('Y-m-d H:i:s') == '-0001-11-30 00:00:00') {
                    $hasil = $hasil && $update->update(['created_at' => $log->updated_at->format('Y-m-d H:i:s')]);
                }

                // tgl_lapor, ambil data dari created_at
                if ($log->tgl_lapor != null && $log->tgl_lapor->format('Y-m-d H:i:s') == '-0001-11-30 00:00:00') {
                    $hasil = $hasil && $update->update(['tgl_lapor' => $log->created_at->format('Y-m-d H:i:s')]);
                }
                // tgl_peristiwa, ambil data dari default 1971-01-01 00:00:00 (agar tidak merusak laporan yg sudah ada)
                if ($log->tgl_peristiwa == null) {
                    continue;
                }
                if ($log->tgl_peristiwa->format('Y-m-d H:i:s') != '-0001-11-30 00:00:00') {
                    continue;
                }
                $hasil = $hasil && $update->update(['tgl_peristiwa' => '1971-01-01 00:00:00']);
            }

            log_message('error', 'Sesuaikan tanggal invalid pada kolom tgl_lapor, tgl_peristiwa dan created_at tabel log_pendudk pada data berikut ini : ' . print_r($logPenduduk->toArray(), true));
        }

        // Tabel log_perubahan_penduduk, field tanggal => isi data dari tweb_penduduk->updated_at
        if ($logPerubahanPenduduk = $this->periksa['tabel_invalid_date']['log_perubahan_penduduk']) {
            foreach ($logPerubahanPenduduk as $log) {
                if (null === $log->penduduk) {
                    // Hapus data log yang tidak digunakan
                    $hasil = $hasil && LogPerubahanPenduduk::find($log->id)->delete();
                } else {
                    $hasil = $hasil && LogPerubahanPenduduk::where('id', $log->id)->update(['tanggal' => ($log->penduduk->updated_at != null) ? $log->penduduk->updated_at->format('Y-m-d H:i:s') : $log->tanggal->format('Y-m-d H:i:s')]);
                }
            }

            log_message('error', 'Sesuaikan tanggal invalid pada kolom tanggal tabel log_perubahan_penduduk pada data berikut ini : ' . print_r($logPerubahanPenduduk->toArray(), true));
        }

        // Tabel tweb_penduduk_mandiri, field updated_at => isi data dari tweb_penduduk_mandiri->taanggal_buat
        if ($pendudukMandiri = $this->periksa['tabel_invalid_date']['tweb_penduduk_mandiri']) {
            foreach ($pendudukMandiri as $mandiri) {
                $hasil = $hasil && PendudukMandiri::where('id_pend', $mandiri->id_pend)->update(['updated_at' => $mandiri->tanggal_buat]);
            }

            log_message('error', 'Sesuaikan tanggal invalid pada kolom tanggal tabel mandiri_perubahan_penduduk pada data berikut ini : ' . print_r($pendudukMandiri->toArray(), true));
        }

        if ($covidVaksin = $this->periksa['tabel_invalid_date']['covid19_vaksin']) {
            foreach ($covidVaksin as $vaksin) {
                if ($vaksin->getRawOriginal('tgl_vaksin_1') == '0000-00-00') {
                    $vaksin->tgl_vaksin_1 = null;
                }
                if ($vaksin->getRawOriginal('tgl_vaksin_2') == '0000-00-00') {
                    $vaksin->tgl_vaksin_2 = null;
                }
                if ($vaksin->getRawOriginal('tgl_vaksin_3') == '0000-00-00') {
                    $vaksin->tgl_vaksin_3 = null;
                }
                $hasil = $hasil && $vaksin->save();
            }

            log_message('error', 'Sesuaikan tanggal invalid pada kolom tgl_vaksin_1, tgl_vaksin_2, tgl_vaksin_3 tabel covid19_vaksin pada data berikut ini : ' . print_r($covidVaksin->toArray(), true));
        }

        if ($twebPenduduk = $this->periksa['tabel_invalid_date']['tweb_penduduk']) {
            foreach ($twebPenduduk as $penduduk) {
                if ($penduduk->getRawOriginal('tanggal_cetak_ktp') == '0000-00-00') {
                    $penduduk->tanggal_cetak_ktp = null;
                }
                $hasil = $hasil && $penduduk->save();
            }

            log_message('error', 'Sesuaikan tanggal invalid pada kolom tanggal_cetak_ktp tabel tweb_penduduk pada data berikut ini : ' . print_r($twebPenduduk->toArray(), true));
        }

        return $hasil;
    }

    public function perbaiki_zero_date_default_value()
    {
        $hasil = true;

        if ($zero_date_default_value = $this->periksa['zero_date_default_value']) {
            foreach ($zero_date_default_value as $value) {
                $fields = [
                    $value['column_name'] => [
                        'type'    => $value['data_type'],
                        'null'    => false,
                        'default' => 'CURRENT_TIMESTAMP',
                    ],
                ];

                $hasil = $hasil && $this->dbforge->modify_column($value['table_name'], "{$value['column_name']} TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                log_message('error', 'Tabel ' . $value['TABLE_NAME'] . ' kolom ' . $value['column_name'] . ' default value menjadi CURRENT_TIMESTAMP.');
            }
        }

        return $hasil;
    }

    private function perbaiki_jabatan()
    {
        if ($jabatan = $this->periksa['data_jabatan']) {
            RefJabatan::insert($jabatan);
        }

        return true;
    }

    private function perbaiki_anjungan(): void
    {
        if (! $this->db->field_exists('id_pengunjung', 'anjungan')) {
            $fields_id_pengunjung = [
                'id_pengunjung' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'default'    => null,
                ],
            ];
            $this->dbforge->add_column('anjungan', $fields_id_pengunjung);
        }

        if (! $this->db->field_exists('tipe', 'anjungan')) {
            $fields_tipe = [
                'tipe' => [
                    'type'       => 'TINYINT',
                    'default'    => 1, // 1 => anjungan, 2 => gawai layanan
                    'constraint' => 3,
                ],
            ];
            $this->dbforge->add_column('anjungan', $fields_tipe);
        }

        if (! $this->db->field_exists('tipe', 'teks_berjalan')) {
            $fields = [
                'tipe' => [
                    'type'       => 'TINYINT',
                    'constraint' => 2,
                    'null'       => true,
                    'default'    => 1,
                    'after'      => 'status',
                ],
            ];
            $this->dbforge->add_column('teks_berjalan', $fields);
        }
    }

    private function perbaiki_penduduk_tanpa_keluarga(): void
    {
        $config_id     = identitas('id');
        $kode_desa     = identitas('kode_desa');
        $data_penduduk = Penduduk::select('id', 'id_cluster', 'id_kk', 'alamat_sekarang', 'created_at')
            ->kepalaKeluarga()
            ->whereNotNull('id_kk')
            ->wheredoesntHave('keluarga', static fn ($q) => $q->where('config_id', $config_id))
            ->get();
        // nomer urut kk sementara
        $digit = Keluarga::nomerKKSementara();

        $id_sementara = [];

        foreach ($data_penduduk as $value) {
            if (isset($id_sementara[$value->id_kk])) {
                continue;
            }
            $nokk_sementara = '0' . $kode_desa . sprintf('%05d', $digit);
            $hasil          = Keluarga::create([
                'id'         => $value->id_kk,
                'config_id'  => $config_id,
                'no_kk'      => $nokk_sementara,
                'nik_kepala' => $value->id,
                'tgl_daftar' => $value->created_at,
                'id_cluster' => $value->id_cluster,
                'alamat'     => $value->alamat_sekarang,
                'updated_at' => $value->created_at,
                'updated_by' => 1,
            ]);

            $digit++;
            $id_sementara[$value->id_kk] = 1;
            if ($hasil) {
                log_message('notice', 'Berhasil. Penduduk ' . $value->id . ' sudah terdaftar di keluarga');
            } else {
                log_message('error', 'Gagal. Penduduk ' . $value->id . ' belum terdaftar di keluarga');
            }
        }
    }

    private function perbaiki_log_penduduk_null(): void
    {
        LogPenduduk::whereIn('id', array_column($this->periksa['log_penduduk_null'], 'id'))->update(['kode_peristiwa' => LogPenduduk::BARU_PINDAH_MASUK]);
    }

    private function perbaiki_log_keluarga_bermasalah(): void
    {
        $configId = identitas('id');
        $userId   = auth()->id;
        $sql      = "insert into log_keluarga (config_id, id_kk, id_peristiwa, tgl_peristiwa, updated_by)
                select {$configId} as config_id, id as id_kk, 1 as id_peristiwa, tgl_daftar as tgl_peristiwa, {$userId} as updated_by
                from tweb_keluarga  where id not in ( select id_kk from log_keluarga where id_peristiwa = 1 ) ";
        DB::statement($sql);
    }

    private function selesaikan_masalah($masalah_ini): void
    {
        switch ($masalah_ini) {
            case 'kode_kelompok':
                $this->perbaiki_kode_kelompok();
                break;

            case 'ref_inventaris_kosong':
                $this->perbaiki_referensi_kosong();
                break;

            case 'id_cluster_null':
                $this->perbaiki_id_cluster_null();
                break;

            case 'nik_ganda':
                $this->perbaiki_nik_ganda();
                break;

            case 'email_ganda':
                $this->perbaiki_email();
                break;

            case 'kk_panjang':
                $this->perbaiki_kk_panjang();
                break;

            case 'no_kk_ganda':
                $this->perbaiki_no_kk_ganda();
                break;

            case 'email_user_ganda':
                $this->perbaiki_email_user();
                break;

            case 'username_user_ganda':
                $this->perbaiki_username_user();
                break;

            case 'tag_id_ganda':
                $this->perbaiki_tag_id();
                break;

            case 'kartu_alamat':
                $this->perbaiki_kartu_alamat();
                break;

            case 'autoincrement':
                $this->perbaiki_autoincrement();
                break;

            case 'collation':
                $this->perbaiki_collation_table();
                break;

            case 'tabel_invalid_date':
                $this->perbaiki_invalid_date();
                break;

            case 'data_jabatan_tidak_ada':
                $this->perbaiki_jabatan();
                break;

            case 'zero_date_default_value':
                $this->perbaiki_zero_date_default_value();
                break;

            case 'penduduk_tanpa_keluarga':
                $this->perbaiki_penduduk_tanpa_keluarga();
                break;

            case 'log_penduduk_null':
                $this->perbaiki_log_penduduk_null();
                break;

            case 'log_keluarga_bermasalah':
                $this->perbaiki_log_keluarga_bermasalah();
                break;

            case 'no_anggota_ganda':
                $this->perbaiki_no_anggota_ganda();
                break;

            default:
                break;
        }
    }
}
