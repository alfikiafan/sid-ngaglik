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

use App\Models\Kehadiran;
use App\Models\KehadiranPengaduan;
use App\Models\LogSurat;
use App\Models\Pamong;
use App\Models\RefJabatan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

defined('BASEPATH') || exit('No direct script access allowed');

class Pamong_model extends MY_Model
{
    private Urut_Model $urut_model;

    public function __construct()
    {
        parent::__construct();
        require_once APPPATH . '/models/Urut_model.php';
        $this->urut_model = new Urut_Model('tweb_desa_pamong', 'pamong_id');
        $this->load->model(['referensi_model']);
    }

    public function list_data($offset = 0, $limit = 500)
    {
        $this->db->select(
            'u.*, rj.nama AS jabatan, rj.id AS ref_jabatan_id, p.nama, p.nik, p.tag_id_card, p.tempatlahir, p.tanggallahir,
            (case when p.sex is not null then p.sex else u.pamong_sex end) as id_sex,
            (case when p.foto is not null then p.foto else u.foto end) as foto,
            (case when p.nama is not null then p.nama else u.pamong_nama end) as nama,
            x.nama AS sex, b.nama AS pendidikan_kk, g.nama AS agama, x2.nama AS pamong_sex, b2.nama AS pamong_pendidikan, g2.nama AS pamong_agama,
            !EXISTS (SELECT s.id_pamong FROM log_surat as s where s.id_pamong = u.pamong_id  ) as deletable'
        );

        $this->list_data_sql();

        $kades  = kades()->id ?: 0;
        $sekdes = sekdes()->id ?: 0;

        $this->db
            ->order_by(sprintf('
                case
                    when u.jabatan_id=%s then 1
                    when u.jabatan_id=%s then 2
                    else 3
                end
            ', $kades, $sekdes), '', false)
            ->order_by('u.urut')
            ->limit($limit, $offset);

        $data = $this->db->get()->result_array();

        $j       = $offset;
        $counter = count($data);

        for ($i = 0; $i < $counter; $i++) {
            if (empty($data[$i]['id_pend'])) {
                // Dari luar desa
                $data[$i]['nik']           = $data[$i]['pamong_nik'];
                $data[$i]['tag_id_card']   = $data[$i]['pamong_tag_id_card'];
                $data[$i]['tempatlahir']   = empty($data[$i]['pamong_tempatlahir']) ? '-' : $data[$i]['pamong_tempatlahir'];
                $data[$i]['tanggallahir']  = $data[$i]['pamong_tanggallahir'];
                $data[$i]['sex']           = $data[$i]['pamong_sex'];
                $data[$i]['pendidikan_kk'] = $data[$i]['pamong_pendidikan'];
                $data[$i]['agama']         = $data[$i]['pamong_agama'];
                if (empty($data[$i]['pamong_nosk'])) {
                    $data[$i]['pamong_nosk'] = '-';
                }
                if (empty($data[$i]['pamong_nohenti'])) {
                    $data[$i]['pamong_nohenti'] = '-';
                }
            } elseif (empty($data[$i]['tempatlahir'])) {
                $data[$i]['tempatlahir'] = '-';
            }

            $data[$i]['nama'] = gelar($data[$i]['gelar_depan'], $data[$i]['nama'], $data[$i]['gelar_belakang']);
            $data[$i]['no']   = $j + 1;
            $j++;
        }

        return $data;
    }

    public function paging($p)
    {
        $this->db->select('COUNT(u.pamong_id) AS jml');
        $this->list_data_sql();

        $row      = $this->db->get()->row_array();
        $jml_data = $row['jml'];

        $this->load->library('paging');
        $cfg['page']     = $p;
        $cfg['per_page'] = $this->session->per_page;
        $cfg['num_rows'] = $jml_data;
        $this->paging->init($cfg);

        return $this->paging;
    }

    private function list_data_sql(): void
    {
        $this->config_id('u')
            ->from('tweb_desa_pamong u')
            ->join('tweb_penduduk p', 'u.id_pend = p.id', 'LEFT')
            ->join('tweb_penduduk_pendidikan_kk b', 'p.pendidikan_kk_id = b.id', 'LEFT')
            ->join('tweb_penduduk_sex x', 'p.sex = x.id', 'LEFT')
            ->join('tweb_penduduk_agama g', 'p.agama_id = g.id', 'LEFT')
            ->join('tweb_penduduk_pendidikan_kk b2', 'u.pamong_pendidikan = b2.id', 'LEFT')
            ->join('tweb_penduduk_sex x2', 'u.pamong_sex = x2.id', 'LEFT')
            ->join('tweb_penduduk_agama g2', 'u.pamong_agama = g2.id', 'LEFT')
            ->join('ref_jabatan rj', 'rj.id = u.jabatan_id', 'left');
        $this->search_sql();
        $this->filter_sql();
    }

    public function autocomplete()
    {
        $sql = "SELECT * FROM
            (SELECT p.nama
                FROM tweb_desa_pamong u
                LEFT JOIN tweb_penduduk p ON u.id_pend = p.id WHERE u.config_id = {$this->config_id}) a
            UNION SELECT pamong_nama FROM tweb_desa_pamong WHERE config_id = {$this->config_id}
            UNION SELECT p.nik FROM tweb_desa_pamong u LEFT JOIN tweb_penduduk p ON u.id_pend = p.id WHERE u.config_id = {$this->config_id}
            UNION SELECT pamong_nik FROM tweb_desa_pamong WHERE config_id = {$this->config_id}
            UNION SELECT pamong_niap FROM tweb_desa_pamong WHERE config_id = {$this->config_id}
            UNION SELECT pamong_nip FROM tweb_desa_pamong WHERE config_id = {$this->config_id}";

        $data = $this->db->query($sql)->result_array();

        return autocomplete_data_ke_str($data);
    }

    private function search_sql(): void
    {
        if ($this->session->has_userdata('cari')) {
            $cari = $this->session->cari;
            $this->db
                ->group_start()
                ->like('p.nama', $cari)
                ->or_like('u.pamong_nama', $cari)
                ->or_like('u.pamong_niap', $cari)
                ->or_like('u.pamong_nip', $cari)
                ->or_like('u.pamong_nik', $cari)
                ->or_like('p.nik', $cari)
                ->group_end();
        }
    }

    private function filter_sql(): void
    {
        if ($this->session->has_userdata('status')) {
            $this->db->where('u.pamong_status', $this->session->status);
        }
    }

    public function get_data($id = 0)
    {
        $data = $this->config_id('u')
            ->select('u.*, rj.nama AS jabatan, rj.nama AS pamong_jabatan, rj.id AS ref_jabatan_id,
				(case when p.nama is not null then p.nama else u.pamong_nama end) as nama,
				(case when p.foto is not null then p.foto else u.foto end) as foto,
				(case when p.sex is not null then p.sex else u.pamong_sex end) as id_sex')
            ->from('tweb_desa_pamong u')
            ->join('tweb_penduduk p', 'u.id_pend = p.id', 'left')
            ->join('ref_jabatan rj', 'rj.id = u.jabatan_id', 'left')
            ->where('pamong_id', $id)
            ->get()
            ->row_array();

        if ($data) {
            $data['pamong_niap_nip'] = (! empty($data['pamong_nip']) && $data['pamong_nip'] != '-') ? $data['pamong_nip'] : $data['pamong_niap'];
            if (! empty($data['pamong_nip']) && $data['pamong_nip'] != '-') {
                $data['sebutan_pamong_niap_nip'] = 'NIP: ';
            } elseif (! empty($data['pamong_niap']) && $data['pamong_niap'] != '-') {
                $data['sebutan_pamong_niap_nip'] = $this->setting->sebutan_nip_desa . ': ';
            } else {
                $data['sebutan_pamong_niap_nip'] = '';
            }

            $data['nama'] = gelar($data['gelar_depan'], $data['nama'], $data['gelar_belakang']);
        }

        return $data;
    }

    public function get_pamong($id = null)
    {
        return $this->get_data($id);
    }

    public function insert(): void
    {
        $post = $this->input->post();
        $data = $this->siapkan_data($post);

        $data['pamong_tgl_terdaftar'] = date('Y-m-d');

        $outp       = $this->db->insert('tweb_desa_pamong', $data);
        $post['id'] = $this->db->insert_id();

        $this->foto($post);

        if ($data['jabatan_id'] == kades()->id) {
            $this->ttd('pamong_ttd', $post['id'], 1);
        } else {
            $this->ttd('pamong_ub', $post['id'], 1);
        }

        status_sukses($outp);
    }

    public function update($id = 0): void
    {
        $post = $this->input->post();
        $data = $this->siapkan_data($post, $id);
        RefJabatan::getKades()->id;
        RefJabatan::getSekdes()->id;

        if (! in_array($data['jabatan_id'], RefJabatan::getKadesSekdes())) {
            $data['pamong_ttd'] = $data['pamong_ub'] = 0;
        }

        $outp       = $this->config_id()->where('pamong_id', $id)->update('tweb_desa_pamong', $data);
        $post['id'] = $id;
        $this->foto($post);

        if ($data['jabatan_id'] == kades()->id) {
            $this->ttd('pamong_ttd', $post['id'], 1);
        } else {
            $this->ttd('pamong_ub', $post['id'], 1);
        }

        status_sukses($outp);
    }

    protected function foto($post)
    {
        if ($post['id_pend']) {
            // Penduduk Dalam Desa
            $id    = $post['id_pend'];
            $field = 'id';
            $tabel = 'tweb_penduduk';
            $foto  = time() . '-' . $id . '-' . random_int(10000, 999999);
        } else {
            // Penduduk Luar Desa
            $id    = $post['id'];
            $field = 'pamong_id';
            $tabel = 'tweb_desa_pamong';
            $foto  = 'pamong_' . time() . '-' . $id . '-' . random_int(10000, 999999);
        }
        $dimensi = $post['lebar'] . 'x' . $post['tinggi'];
        if ($foto = upload_foto_penduduk($foto, $dimensi)) {
            $this->config_id()->where($field, $id)->update($tabel, ['foto' => $foto]);
        }
    }

    public function delete($id = '', $semua = false)
    {
        // Cek boleh hapus
        if ($this->boleh_hapus($id)) {
            return session_error("ID : {$id} tidak dapat dihapus, data sudah tersedia di kehadiran perangkatl, pengaduan kehadiran dan layanan Surat.");
        }

        if (! $semua) {
            $this->session->success = 1;
        }

        $pamong = Pamong::find($id) ?? show_404();
        if (! empty($pamong->foto)) {
            unlink(LOKASI_USER_PICT . $pamong->foto);
            unlink(LOKASI_USER_PICT . 'kecil_' . $pamong->foto);
        }

        $outp = $pamong->delete();

        status_sukses($outp, true); //Tampilkan Pesan
    }

    public function delete_all(): void
    {
        $this->session->success = 1;

        $id_cb = $_POST['id_cb'];

        foreach ($id_cb as $id) {
            $this->delete($id, true);
        }
    }

    private function siapkan_data($post, $id = null)
    {
        $data                       = [];
        $data['id_pend']            = $post['id_pend'];
        $data['pamong_nama']        = null;
        $data['pamong_nip']         = strip_tags($post['pamong_nip']);
        $data['pamong_niap']        = strip_tags($post['pamong_niap']);
        $data['pamong_tag_id_card'] = strip_tags($post['pamong_tag_id_card']) ?: null;
        $data['pamong_pin']         = strip_tags($post['pamong_pin']);
        $data['jabatan_id']         = bilangan($post['jabatan_id']);
        $data['pamong_pangkat']     = strip_tags($post['pamong_pangkat']);
        $data['pamong_status']      = $post['pamong_status'];
        $data['pamong_nosk']        = empty($post['pamong_nosk']) ? '' : strip_tags($post['pamong_nosk']);
        $data['pamong_tglsk']       = empty($post['pamong_tglsk']) ? null : tgl_indo_in($post['pamong_tglsk']);
        $data['pamong_nohenti']     = empty($post['pamong_nohenti']) ? null : strip_tags($post['pamong_nohenti']);
        $data['pamong_tglhenti']    = empty($post['pamong_tglhenti']) ? null : tgl_indo_in($post['pamong_tglhenti']);
        $data['pamong_masajab']     = strip_tags($post['pamong_masajab']) ?: null;
        $data['atasan']             = bilangan($post['atasan']) ?: null;
        $data['bagan_tingkat']      = bilangan($post['bagan_tingkat']) ?: null;
        $data['bagan_offset']       = (int) $post['bagan_offset'] ?: null;
        $data['bagan_layout']       = htmlentities($post['bagan_layout']);
        $data['bagan_warna']        = warna($post['bagan_warna']);
        $data['gelar_depan']        = strip_tags($post['gelar_depan']) ?: null;
        $data['gelar_belakang']     = strip_tags($post['gelar_belakang']) ?: null;

        if ($data['jabatan_id'] == kades()->id) {
            $data['urut'] = 1;
        } elseif ($data['jabatan_id'] == sekdes()->id) {
            $data['urut'] = 2;
        } elseif ($id == 0 || $id == null) {
            $data['urut'] = $this->urut_model->urut_max() + 1;
        }

        if (empty($data['id_pend'])) {
            $data['id_pend']             = null;
            $data['pamong_nama']         = strip_tags($post['pamong_nama']);
            $data['pamong_nik']          = strip_tags($post['pamong_nik']) ?: null;
            $data['pamong_tempatlahir']  = strip_tags($post['pamong_tempatlahir']) ?: null;
            $data['pamong_tanggallahir'] = empty($post['pamong_tanggallahir']) ? null : tgl_indo_in($post['pamong_tanggallahir']);
            $data['pamong_sex']          = $post['pamong_sex'] ?: null;
            $data['pamong_pendidikan']   = $post['pamong_pendidikan'] ?: null;
            $data['pamong_agama']        = $post['pamong_agama'] ?: null;
        }

        if (null === $id) {
            $data['config_id'] = identitas('id');
        }

        return $data;
    }

    /**
     * Update pamong ttd.
     *
     * @param mixed $jenis Jenis pamong_ttd atau pamong_ub
     * @param mixed $id    ID pamong
     * @param mixed $val   1. checklist 2. un-checklist
     *
     * @return mixed
     */
    public function ttd($jenis, $id, $val)
    {
        $pamong = Pamong::find($id) ?? show_404();
        RefJabatan::getSekdes()->id;

        if ($jenis == 'a.n') {
            if ($pamong->jabatan_id == sekdes()->id) {
                $output = Pamong::where('jabatan_id', sekdes()->id)->find($id)->update(['pamong_ttd' => $val]);

                // Hanya 1 yang bisa jadi a.n dan harus sekretaris
                if ($output) {
                    Pamong::where('pamong_ttd', 1)->where('pamong_id', '!=', $id)->update(['pamong_ttd' => 0]);
                }
            } else {
                $pesan = ', Penandatangan a.n harus ' . RefJabatan::whereJenis(RefJabatan::SEKDES)->first(['nama'])->nama;
            }
        }

        if ($jenis == 'u.b') {
            if (! in_array($pamong->jabatan_id, RefJabatan::getKadesSekdes())) {
                $output = Pamong::whereNotIn('jabatan_id', RefJabatan::getKadesSekdes())->find($id)->update(['pamong_ub' => $val]);
            } else {
                $pesan = ', Penandatangan u.b harus pamong selain ' . RefJabatan::whereJenis(RefJabatan::KADES)->first(['nama'])->nama . ' dan ' . RefJabatan::whereJenis(RefJabatan::SEKDES)->first(['nama'])->nama;
            }
        }

        session_error($pesan);

        return status_sukses($output);
    }

    private function select_data_pamong(): void
    {
        $this->db
            ->select('m.*')
            ->select('(case when p.id is null then m.pamong_nama else p.nama end) as pamong_nama')
            ->select('(case when p.id is null then m.pamong_nik else p.nik end) as pamong_nik')
            ->select('(case when p.id is null then m.pamong_tag_id_card else p.tag_id_card end) as pamong_tag_id_card')
            ->select('(case when p.id is null then m.pamong_tempatlahir else p.tempatlahir end) as pamong_tempatlahir')
            ->select('(case when p.id is null then m.pamong_tanggallahir else p.tanggallahir end) as pamong_tanggallahir')
            ->select('(case when p.id is null then m.pamong_sex else p.sex end) as pamong_sex')
            ->select('(case when p.id is null then m.pamong_pendidikan else p.pendidikan_kk_id end) as pamong_pendidikan')
            ->select('(case when p.id is null then m.pamong_agama else p.agama_id end) as pamong_agama')
            ->from('tweb_desa_pamong m')
            ->join('tweb_penduduk p', 'p.id = m.id_pend', 'left');
    }

    public function get_ttd()
    {
        $this->select_data_pamong();

        return $this->db
            ->where('m.pamong_ttd', 1)
            ->get()
            ->row_array();
    }

    public function get_ub()
    {
        $this->select_data_pamong();

        return $this->db
            ->where('pamong_ub', 1)
            ->get()
            ->row_array();
    }

    // $arah:
    //		1 - turun
    // 		2 - naik
    public function urut($id, $arah): void
    {
        $outp = $this->urut_model->urut($id, $arah);

        status_sukses($outp);
    }

    // Mengambil semua data penduduk kecuali yg sdh menjadi pamong dan tdk termasuk yang di ubah untuk pilihan drop-down form
    public function list_penduduk($id_pend)
    {
        return $this->config_id('u')
            ->select('u.id, u.nik, u.nama, w.dusun, w.rw, w.rt, u.sex')
            ->from('penduduk_hidup u')
            ->join('tweb_wil_clusterdesa w', 'u.id_cluster = w.id', 'left')
            ->where("u.id NOT IN (SELECT id_pend FROM tweb_desa_pamong WHERE id_pend IS NOT NULL AND id_pend != {$id_pend})")
            ->get()
            ->result_array();
    }

    // Ambil data untuk widget aparatur desa
    public function list_aparatur_desa()
    {
        // Jika kolom jabatan_id tidak tersedia, jangan tampilkan dulu.
        if (! Schema::hasColumn('tweb_desa_pamong', 'jabatan_id')) {
            return null;
        }

        $data_query = $this->config_id_exist('tweb_desa_pamong', 'dp')
            ->select(
                'dp.pamong_id, rj.nama AS jabatan, dp.pamong_niap, dp.gelar_depan, dp.gelar_belakang, dp.kehadiran,
                CASE WHEN dp.id_pend IS NULL THEN dp.foto ELSE p.foto END as foto,
                CASE WHEN p.sex IS NOT NULL THEN p.sex ELSE dp.pamong_sex END as id_sex,
                CASE WHEN dp.id_pend IS NULL THEN dp.pamong_nama ELSE p.nama END AS nama',
                false
            )
            ->from('tweb_desa_pamong dp')
            ->join('tweb_penduduk p', 'p.id = dp.id_pend', 'left')
            ->join('ref_jabatan rj', 'rj.id = dp.jabatan_id', 'left')
            ->where('dp.pamong_status', '1')
            ->order_by('dp.urut')
            ->get()
            ->result_array();

        return ['daftar_perangkat' => collect($data_query)->map(static function (array $item): array {
            $kehadiran                = Kehadiran::where('pamong_id', $item['pamong_id'])->where('tanggal', Carbon::now()->format('Y-m-d'))->orderBy('id', 'DESC')->first();
            $item['status_kehadiran'] = $kehadiran ? $kehadiran->status_kehadiran : null;
            $item['tanggal']          = $kehadiran ? $kehadiran->tanggal : null;
            $item['foto']             = AmbilFoto($item['foto'] ?? '', 'besar', $item['id_sex']);
            $item['nama']             = gelar($item['gelar_depan'], $item['nama'], $item['gelar_belakang']);

            return $item;
        })->toArray(),
        ];
    }

    //----------------------------------------------------------------------------------------------------

    /**
     * @param $id  id
     * @param $val status : 1 = Unlock, 2 = Lock
     */
    public function lock($id, $val)
    {
        $pamong        = Pamong::find($id) ?? show_404();
        $jabatan_aktif = Pamong::whereJabatanId($pamong->jabatan_id)->wherePamongStatus(1)->exists();

        // Cek untuk kades atau sekdes apakah sudah ada yang aktif saat mengaktifkan
        if ($val == 1 && $jabatan_aktif && in_array($pamong->jabatan_id, RefJabatan::getKadesSekdes())) {
            return session_error('<br>Pamong ' . $pamong->jabatan->nama . ' sudah tersedia, silahakan non-aktifkan terlebih dahulu jika ingin menggantinya.');
        }

        $outp = $pamong->update(['pamong_status' => $val]);
        status_sukses($outp);
    }

    /**
     * @param $id  id
     * @param $val status : 1 = Aktif, 0 = Tidak aktif
     */
    public function kehadiran($id, $val): void
    {
        $pamong = Pamong::find($id) ?? show_404();
        $outp   = $pamong->update(['kehadiran' => $val]);

        status_sukses($outp);
    }

    public function list_bagan()
    {
        // atasan => bawahan. Contoh:
        // data['struktur'] = [
        //  ['14' => '20'],
        //  ['14' => '26'],
        //  ['20' => '24']
        // ;
        $atasan = $this->config_id()
            ->select('atasan, pamong_id')
            ->where('atasan IS NOT NULL')
            ->where('pamong_status', 1)
            ->get('tweb_desa_pamong')
            ->result_array();
        $data['struktur'] = [];

        foreach ($atasan as $pamong) {
            $data['struktur'][] = [$pamong['atasan'] => $pamong['pamong_id']];
        }

        $data_query = $this->config_id('p')
            ->select('p.pamong_id, rj.nama AS jabatan, p.gelar_depan, p.gelar_belakang, p.bagan_tingkat, p.bagan_offset, p.bagan_layout, p.bagan_warna')
            ->select('(CASE WHEN id_pend IS NOT NULL THEN ph.foto ELSE p.foto END) as foto')
            ->select('(CASE WHEN id_pend IS NOT NULL THEN ph.nama ELSE p.pamong_nama END) as nama')
            ->select('(CASE WHEN id_pend IS NOT NULL THEN ph.sex ELSE p.pamong_sex END) as jenis_kelamin')
            ->from('tweb_desa_pamong p')
            ->join('penduduk_hidup ph', 'ph.id = p.id_pend', 'left')
            ->join('ref_jabatan rj', 'rj.id = p.jabatan_id', 'left')
            ->where('pamong_status', 1)
            ->get()
            ->result_array();

        $data['nodes'] = collect($data_query)->map(static function (array $item): array {
            $item['nama'] = gelar($item['gelar_depan'], $item['nama'], $item['gelar_belakang']);

            return $item;
        })
            ->toArray();

        return $data;
    }

    public function list_atasan($ex_id = '')
    {
        if ($ex_id) {
            $this->db->where('pamong_id <>', $ex_id);
        }

        return $this->config_id('p')
            ->select('pamong_id as id, rj.nama AS jabatan')
            ->select('(CASE WHEN id_pend IS NOT NULL THEN ph.nik ELSE p.pamong_nik END) as nik')
            ->select('(CASE WHEN id_pend IS NOT NULL THEN ph.nama ELSE p.pamong_nama END) as nama')
            ->from('tweb_desa_pamong p')
            ->join('penduduk_hidup ph', 'ph.id = p.id_pend', 'left')
            ->join('ref_jabatan rj', 'rj.id = p.jabatan_id', 'left')
            ->where('pamong_status', 1)
            ->order_by('nama')
            ->get()
            ->result_array();
    }

    public function update_bagan($post): void
    {
        $list_id = $post['list_id'];
        if ($post['atasan']) {
            $data['atasan'] = ($post['atasan'] <= 0) ? null : $post['atasan'];
        }
        if ($post['bagan_tingkat']) {
            $data['bagan_tingkat'] = ($post['bagan_tingkat'] <= 0) ? null : $post['bagan_tingkat'];
        }
        if ($post['bagan_warna']) {
            $data['bagan_warna'] = (warna($post['bagan_warna'] == '#000000')) ? null : warna($post['bagan_warna']);
        }

        $outp = $this->config_id()
            ->where("pamong_id in ({$list_id})")
            ->update('tweb_desa_pamong', $data);

        status_sukses($outp);
    }

    public function status_aktif()
    {
        $this->db->where('u.pamong_status', 1);

        return $this;
    }

    public function boleh_hapus($id = null)
    {
        $kehadiranPerangkat = Kehadiran::where('pamong_id', $id)->exists();
        $kehadiranPengaduan = KehadiranPengaduan::where('id_pamong', $id)->exists();
        $kehadiranPengaduan = LogSurat::where('id_pamong', $id)->exists();

        return $kehadiranPerangkat || $kehadiranPengaduan || $kehadiranPengaduan;
    }
}
