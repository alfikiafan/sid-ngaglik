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

use App\Enums\SatuanWaktuEnum;

defined('BASEPATH') || exit('No direct script access allowed');

class Admin_pembangunan extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->modul_ini = 'pembangunan';
        $this->load->library('zip');
        $this->load->library('MY_Upload', null, 'upload');
        $this->load->model('pembangunan_model', 'pembangunan');
        $this->load->model('pembangunan_dokumentasi_model', 'dokumentasi');
        $this->load->model('wilayah_model');
        $this->load->model('pamong_model');
        $this->load->model('plan_lokasi_model');
        $this->load->model('plan_area_model');
        $this->load->model('plan_garis_model');
    }

    public function index()
    {
        if ($this->input->is_ajax_request()) {
            $start  = $this->input->post('start');
            $length = $this->input->post('length');
            $search = $this->input->post('search[value]');
            $order  = $this->pembangunan::ORDER_ABLE[$this->input->post('order[0][column]')];
            $dir    = $this->input->post('order[0][dir]');
            $tahun  = $this->input->post('tahun');

            $this->pembangunan->set_tipe(''); // Ambil semua pembangunan

            $data = $this->pembangunan->get_data($search, $tahun)->order_by($order, $dir)->limit($length, $start)->get()->result();
            $data = collect($data)->map(static function ($item) {
                $item->url_foto = to_base64(LOKASI_GALERI . $item->foto);

                return $item;
            })->toArray();

            return json([
                'draw'            => $this->input->post('draw'),
                'recordsTotal'    => $this->pembangunan->get_data()->count_all_results(),
                'recordsFiltered' => $this->pembangunan->get_data($search, $tahun)->count_all_results(),
                'data'            => $data,
            ]);
        }

        $this->render(ADMIN . '/pembangunan/index', [
            'list_tahun' => $this->pembangunan->list_filter_tahun(),
        ]);
    }

    public function form($id = ''): void
    {
        $this->redirect_hak_akses('u');
        if ($id) {
            $data['main']        = $this->pembangunan->find($id) ?? show_404();
            $data['form_action'] = site_url("{$this->controller}/update/{$id}");
        } else {
            $data['main'] = null;

            $data['form_action'] = site_url("{$this->controller}/insert");
        }

        $data['list_lokasi']  = $this->wilayah_model->list_semua_wilayah();
        $data['sumber_dana']  = $this->referensi_model->list_ref(SUMBER_DANA);
        $data['satuan_waktu'] = SatuanWaktuEnum::all();

        $this->render(ADMIN . '/pembangunan/form', $data);
    }

    public function insert(): void
    {
        $this->redirect_hak_akses('u');
        $this->pembangunan->insert();
        redirect($this->controller);
    }

    public function update($id = ''): void
    {
        $this->redirect_hak_akses('u');
        $this->pembangunan->update($id);
        redirect($this->controller);
    }

    public function delete($id): void
    {
        $this->redirect_hak_akses('h');
        $this->pembangunan->delete($id);

        $this->session->success = $this->db->affected_rows() ? 4 : -4;

        redirect($this->controller);
    }

    public function lokasi_maps($id): void
    {
        $data = $this->pembangunan->find($id) ?? show_404();

        // Update lokasi maps
        if ($request = $this->input->post()) {
            $this->redirect_hak_akses('u');
            $this->pembangunan->update_lokasi_maps($id, $request);

            $this->session->success = 1;

            redirect($this->controller);
        }

        $this->render(ADMIN . '/pembangunan/lokasi_maps', [
            'data'                   => $data,
            'desa'                   => $this->header['desa'],
            'wil_atas'               => $this->header['desa'],
            'dusun_gis'              => $this->wilayah_model->list_dusun(),
            'rw_gis'                 => $this->wilayah_model->list_rw(),
            'rt_gis'                 => $this->wilayah_model->list_rt(),
            'all_lokasi'             => $this->plan_lokasi_model->list_lokasi(),
            'all_garis'              => $this->plan_garis_model->list_garis(),
            'all_area'               => $this->plan_area_model->list_area(),
            'all_lokasi_pembangunan' => $this->pembangunan->list_lokasi_pembangunan(),
        ]);
    }

    public function dialog_daftar($id = 0, $aksi = ''): void
    {
        $data                = $this->modal_penandatangan();
        $data['aksi']        = $aksi;
        $data['form_action'] = site_url("{$this->controller}/daftar/{$id}/{$aksi}");

        $this->load->view('global/ttd_pamong', $data);
    }

    public function daftar($id = 0, $aksi = ''): void
    {
        $request = $this->input->post();

        $pembangunan = $this->pembangunan->find($id) ?? show_404();
        $dokumentasi = $this->dokumentasi->find_dokumentasi($pembangunan->id);

        $data['pembangunan']    = $pembangunan;
        $data['dokumentasi']    = $dokumentasi;
        $data['config']         = $this->header['desa'];
        $data['pamong_ttd']     = $this->pamong_model->get_data($request['pamong_ttd']);
        $data['pamong_ketahui'] = $this->pamong_model->get_data($request['pamong_ketahui']);
        $data['aksi']           = $aksi;
        $data['ekstensi']       = 'doc';
        $data['file']           = 'Laporan Pembangunan';
        $data['isi']            = ADMIN . '/pembangunan/cetak';

        $this->load->view('global/format_cetak', $data);
    }

    public function unlock($id): void
    {
        $this->pembangunan->unlock($id);

        $this->session->success = 1;

        redirect($this->controller);
    }

    public function lock($id): void
    {
        $this->redirect_hak_akses('u');
        $this->pembangunan->lock($id);

        $this->session->success = 1;

        redirect($this->controller);
    }

    // Dokumentasi Pembangunan
    public function dokumentasi($id = null)
    {
        $pembangunan                = $this->pembangunan->find($id) ?? show_404();
        $_SESSION['id_pembangunan'] = $id;

        if ($this->input->is_ajax_request()) {
            $start  = $this->input->post('start');
            $length = $this->input->post('length');
            $search = $this->input->post('search[value]');
            $order  = $this->dokumentasi::ORDER_ABLE[$this->input->post('order[0][column]')];
            $dir    = $this->input->post('order[0][dir]');

            $data = $this->dokumentasi->get_data($id, $search)->order_by($order, $dir)->limit($length, $start)->get()->result();
            $data = collect($data)->map(static function ($item) {
                $item->url_gambar = to_base64(LOKASI_GALERI . $item->gambar);

                return $item;
            })->toArray();

            return json([
                'draw'            => $this->input->post('draw'),
                'recordsTotal'    => $this->dokumentasi->get_data($id)->count_all_results(),
                'recordsFiltered' => $this->dokumentasi->get_data($id, $search)->count_all_results(),
                'data'            => $data,
            ]);
        }

        $this->render(ADMIN . '/pembangunan/dokumentasi/index', [
            'pembangunan' => $pembangunan,
        ]);
    }

    public function dokumentasi_form($id = ''): void
    {
        $this->redirect_hak_akses('u');
        $id_pembangunan = $this->session->id_pembangunan;

        if ($id) {
            $data['main']        = $this->dokumentasi->find($id) ?? show_404();
            $data['perubahan']   = $this->pembangunan->find($id_pembangunan)->perubahan_anggaran ?? show_404();
            $data['form_action'] = site_url("{$this->controller}/dokumentasi_update/{$id}/{$id_pembangunan}");
        } else {
            $data['main']        = null;
            $data['form_action'] = site_url("{$this->controller}/dokumentasi_insert/{$id_pembangunan}");
        }

        $data['id_pembangunan'] = $id_pembangunan;
        $data['persentase']     = $this->referensi_model->list_ref(STATUS_PEMBANGUNAN);

        $this->render(ADMIN . '/pembangunan/dokumentasi/form', $data);
    }

    public function dokumentasi_insert($id_pembangunan = ''): void
    {
        $this->redirect_hak_akses('u');
        $this->dokumentasi->insert($id_pembangunan);
        redirect("{$this->controller}/dokumentasi/{$id_pembangunan}");
    }

    public function dokumentasi_update($id = '', $id_pembangunan = ''): void
    {
        $this->redirect_hak_akses('u');
        $this->dokumentasi->update($id, $id_pembangunan);
        redirect("{$this->controller}/dokumentasi/{$id_pembangunan}");
    }

    public function dokumentasi_delete($id_pembangunan, $id): void
    {
        $this->redirect_hak_akses('h');
        $this->dokumentasi->delete($id);

        $this->session->success = $this->db->affected_rows() ? 4 : -4;

        redirect("{$this->controller}/dokumentasi/{$id_pembangunan}");
    }
}
