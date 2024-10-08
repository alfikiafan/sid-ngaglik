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

class Mailbox extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('web_komentar_model');
        $this->load->model('mandiri_model');
        $this->load->model('mailbox_model');
        $this->modul_ini     = 'layanan-mandiri';
        $this->sub_modul_ini = 'kotak-pesan';
    }

    public function clear($kat = 1, $p = 1, $o = 0): void
    {
        unset($_SESSION['cari'], $_SESSION['filter_status'], $_SESSION['filter_nik'], $_SESSION['filter_archived']);

        redirect("mailbox/index/{$kat}/{$p}/{$o}");
    }

    public function index($kat = 1, $p = 1, $o = 0): void
    {
        $data['p']   = $p;
        $data['o']   = $o;
        $data['kat'] = $kat;

        $list_session = ['cari', 'filter_status', 'filter_nik', 'filter_archived'];

        foreach ($list_session as $session) {
            $data[$session] = $this->session->userdata($session) ?: '';
        }

        if ($nik = $this->session->userdata('filter_nik')) {
            $data['individu'] = $this->mandiri_model->get_pendaftar_mandiri($nik);
        }

        if ($per_page = $this->input->post('per_page')) {
            $this->session->set_userdata('per_page', $per_page);
        }

        $data['per_page']    = $_SESSION['per_page'];
        $data['paging']      = $this->web_komentar_model->paging($p, $o, $kat);
        $data['main']        = $this->web_komentar_model->list_data($o, $data['paging']->offset, $data['paging']->per_page, $kat);
        $data['owner']       = $kat == 1 ? 'Pengirim' : 'Penerima';
        $data['keyword']     = $this->web_komentar_model->autocomplete();
        $data['submenu']     = $this->mailbox_model->list_menu();
        $_SESSION['submenu'] = $kat;

        $this->render('mailbox/table', $data);
    }

    public function form(): void
    {
        $this->redirect_hak_akses('h');

        if (! empty($nik = $this->input->post('nik'))) {
            $data['individu'] = $this->mandiri_model->get_pendaftar_mandiri($nik);
        }

        if (! empty($subjek = $this->input->post('subjek'))) {
            $data['subjek'] = $subjek;
        }

        $data['form_action'] = site_url('mailbox/kirim_pesan');

        $this->render('mailbox/form', $data);
    }

    public function kirim_pesan(): void
    {
        $this->redirect_hak_akses('u');
        $post           = $this->input->post();
        $post['tipe']   = 2;
        $post['status'] = 2;
        $this->mailbox_model->insert($post);
        redirect('mailbox/index/' . $post['tipe']);
    }

    public function baca_pesan($kat, $id): void
    {
        if ($kat == 1) {
            $this->web_komentar_model->komentar_lock($id, 1);
            unset($_SESSION['success']);
        }

        $data['kat']          = $kat;
        $data['owner']        = $kat == 1 ? 'Pengirim' : 'Penerima';
        $data['pesan']        = $this->web_komentar_model->get_komentar($id) ?? show_404();
        $data['tipe_mailbox'] = $this->mailbox_model->get_kat_nama($kat);

        $this->render('mailbox/detail', $data);
    }

    public function search($kat = 1): void
    {
        $cari = $this->input->post('cari');
        if ($cari != '') {
            $this->session->cari = $cari;
        } else {
            unset($this->session->cari);
        }
        redirect("mailbox/index/{$kat}");
    }

    public function filter_status($kat = 1): void
    {
        $status = $this->input->post('status');
        if ($status != 0) {
            if ($status == 3) {
                $this->session->filter_archived = true;
                unset($this->session->filter_status);
            } else {
                $this->session->filter_status = $status;
                unset($this->session->filter_archived);
            }
        } else {
            unset($_SESSION['filter_status'], $_SESSION['filter_archived']);
        }
        redirect("mailbox/index/{$kat}");
    }

    public function filter_nik($kat = 1): void
    {
        $nik = $this->input->post('nik');
        if (! empty($nik) && $nik != 0) {
            $this->session->filter_nik = $nik;
        } else {
            unset($this->session->filter_nik);
        }
        redirect("mailbox/index/{$kat}");
    }

    public function list_pendaftar_mandiri_ajax(): void
    {
        $cari                   = $this->input->get('q');
        $page                   = $this->input->get('page');
        $list_pendaftar_mandiri = $this->mandiri_model->list_data_ajax($cari, $page);
        echo json_encode($list_pendaftar_mandiri, JSON_THROW_ON_ERROR);
    }

    public function archive($kat = 1, $p = 1, $o = 0, $id = ''): void
    {
        $this->redirect_hak_akses('h');
        $this->web_komentar_model->archive($id);
        redirect("mailbox/index/{$kat}/{$p}/{$o}");
    }

    public function archive_all($kat = 1, $p = 1, $o = 0): void
    {
        $this->redirect_hak_akses('h');
        $this->web_komentar_model->archive_all();
        redirect("mailbox/index/{$kat}/{$p}/{$o}");
    }

    public function pesan_read($id = ''): void
    {
        $this->redirect_hak_akses('u');
        $this->web_komentar_model->komentar_lock($id, 1);
        redirect('mailbox');
    }

    public function pesan_unread($id = ''): void
    {
        $this->redirect_hak_akses('u');
        $this->web_komentar_model->komentar_lock($id, 2);
        redirect('mailbox');
    }
}
