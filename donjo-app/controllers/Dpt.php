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

use App\Enums\StatusEnum;

defined('BASEPATH') || exit('No direct script access allowed');

class Dpt extends Admin_Controller
{
    private array $set_page     = ['50', '100', '200', [0, 'Semua']];
    private array $list_session = ['cari', 'sex', 'dusun', 'rw', 'rt', 'tanggal_pemilihan', 'umurx', 'umur_min', 'umur_max', 'cacatx', 'menahunx', 'pekerjaan_id', 'status', 'agama', 'pendidikan_sedang_id', 'pendidikan_kk_id', 'status_penduduk', 'tag_id_card'];

    public function __construct()
    {
        parent::__construct();
        $this->load->model(['penduduk_model', 'dpt_model', 'wilayah_model']);
        $this->modul_ini     = 'kependudukan';
        $this->sub_modul_ini = 'calon-pemilih';
    }

    public function clear(): void
    {
        $this->session->unset_userdata($this->list_session);
        $this->session->per_page = $this->set_page[0];

        redirect($this->controller);
    }

    public function index($p = 1, $o = 0): void
    {
        $data['p'] = $p;
        $data['o'] = $o;

        foreach ($this->list_session as $list) {
            if (in_array($list, ['dusun', 'rw', 'rt'])) {
                ${$list} = $this->session->{$list};
            } else {
                $data[$list] = $this->session->{$list} ?: '';
            }
        }

        if (isset($dusun)) {
            $data['dusun']   = $dusun;
            $data['list_rw'] = $this->wilayah_model->list_rw($dusun);

            if (isset($rw)) {
                $data['rw']      = $rw;
                $data['list_rt'] = $this->wilayah_model->list_rt($dusun, $rw);

                $data['rt'] = $rt ?? '';
            } else {
                $data['rw'] = '';
            }
        } else {
            $data['dusun'] = $data['rw'] = $data['rt'] = '';
        }

        $per_page = $this->input->post('per_page');
        if (isset($per_page)) {
            $this->session->per_page = $per_page;
        }

        $data['func']               = 'index';
        $data['set_page']           = $this->set_page;
        $list_data                  = $this->dpt_model->list_data($o, $p);
        $data['paging']             = $list_data['paging'];
        $data['main']               = $list_data['main'];
        $data['list_jenis_kelamin'] = $this->referensi_model->list_data('tweb_penduduk_sex');
        $data['list_dusun']         = $this->wilayah_model->list_dusun();
        $data['keyword']            = $this->dpt_model->autocomplete();

        $this->render('dpt/dpt', $data);
    }

    public function filter($filter): void
    {
        if ($filter == 'dusun') {
            $this->session->unset_userdata(['rw', 'rt']);
        }
        if ($filter == 'rw') {
            $this->session->unset_userdata('rt');
        }

        $value = $this->input->post($filter);
        if ($value != '') {
            $this->session->{$filter} = $value;
        } else {
            $this->session->unset_userdata($filter);
        }

        redirect($this->controller);
    }

    public function ajax_adv_search(): void
    {
        foreach ($this->list_session as $list) {
            $data[$list] = $this->session->{$list} ?: '';
        }

        $data['input_umur']           = true;
        $data['list_agama']           = $this->referensi_model->list_data('tweb_penduduk_agama');
        $data['list_pendidikan']      = $this->referensi_model->list_data('tweb_penduduk_pendidikan');
        $data['list_pendidikan_kk']   = $this->referensi_model->list_data('tweb_penduduk_pendidikan_kk');
        $data['list_pekerjaan']       = $this->referensi_model->list_data('tweb_penduduk_pekerjaan');
        $data['list_status_kawin']    = $this->referensi_model->list_data('tweb_penduduk_kawin');
        $data['list_status_penduduk'] = $this->referensi_model->list_data('tweb_penduduk_status');
        $data['list_tag_id_card']     = StatusEnum::all();
        $data['form_action']          = site_url("{$this->controller}/adv_search_proses");

        $this->load->view('sid/kependudukan/ajax_adv_search_form', $data);
    }

    public function adv_search_proses(): void
    {
        $adv_search = $_POST;
        $i          = 0;

        while ($i++ < count($adv_search)) {
            $col[$i] = key($adv_search);
            next($adv_search);
        }
        $i = 0;

        while ($i++ < count($col)) {
            if ($adv_search[$col[$i]] == '') {
                unset($adv_search[$col[$i]], $_SESSION[$col[$i]]);
            } else {
                $_SESSION[$col[$i]] = $adv_search[$col[$i]];
            }
        }

        redirect($this->controller);
    }

    public function cetak($page = 1, $o = 0, $aksi = '', $privasi_nik = 0): void
    {
        $data['main'] = $this->dpt_model->list_data($o, $page)['main'];
        $data['aksi'] = $aksi;
        if ($privasi_nik == 1) {
            $data['privasi_nik'] = true;
        }
        $this->load->view("dpt/dpt_{$aksi}", $data);
    }

    public function ajax_cetak($page = 1, $o = 0, $aksi = ''): void
    {
        $data['o']                   = $o;
        $data['aksi']                = $aksi;
        $data['form_action']         = site_url("{$this->controller}/cetak/{$page}/{$o}/{$aksi}");
        $data['form_action_privasi'] = site_url("{$this->controller}/cetak/{$page}/{$o}/{$aksi}/1");

        $this->load->view('sid/kependudukan/ajax_cetak_bersama', $data);
    }
}
