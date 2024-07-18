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

class Kategori extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('web_kategori_model');
        $this->modul_ini     = 'admin-web';
        $this->sub_modul_ini = 'menu';
    }

    public function clear(): void
    {
        unset($_SESSION['cari'], $_SESSION['filter']);

        $_SESSION['per_page'] = 20;
        redirect('kategori');
    }

    public function index($p = 1, $o = 0): void
    {
        $data['p']   = $p;
        $data['o']   = $o;
        $data['tip'] = 2;

        $data['cari'] = $_SESSION['cari'] ?? '';

        $data['filter'] = $_SESSION['filter'] ?? '';

        if (isset($_POST['per_page'])) {
            $_SESSION['per_page'] = $_POST['per_page'];
        }
        $data['per_page'] = $_SESSION['per_page'];

        $data['paging']  = $this->web_kategori_model->paging($p, $o);
        $data['main']    = $this->web_kategori_model->list_data($o, $data['paging']->offset, $data['paging']->per_page);
        $data['keyword'] = $this->web_kategori_model->autocomplete();

        $this->render('kategori/table', $data);
    }

    public function form($id = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $data['tip'] = 2;
        if ($id) {
            $data['kategori']    = $this->web_kategori_model->get_kategori($id) ?? show_404();
            $data['form_action'] = site_url("kategori/update/{$id}");
        } else {
            $data['kategori']    = null;
            $data['form_action'] = site_url('kategori/insert');
        }

        $this->render('kategori/form', $data);
    }

    public function sub_kategori($kategori = 1): void
    {
        $data['subkategori'] = $this->web_kategori_model->list_sub_kategori($kategori) ?? show_404();
        $data['kategori']    = $kategori;

        $this->render('kategori/sub_kategori_table', $data);
    }

    public function ajax_add_sub_kategori($kategori = '', $id = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $data['kategori'] = $kategori;
        $data['link']     = $this->web_kategori_model->list_link();

        if ($id) {
            $data['subkategori'] = $this->web_kategori_model->get_kategori($id) ?? show_404();
            $data['form_action'] = site_url("kategori/update_sub_kategori/{$kategori}/{$id}");
        } else {
            $data['subkategori'] = null;
            $data['form_action'] = site_url("kategori/insert_sub_kategori/{$kategori}");
        }

        $this->load->view('kategori/ajax_add_sub_kategori_form', $data);
    }

    public function search(): void
    {
        $cari = $this->input->post('cari');
        if ($cari != '') {
            $_SESSION['cari'] = $cari;
        } else {
            unset($_SESSION['cari']);
        }
        redirect('kategori/index');
    }

    public function filter(): void
    {
        $filter = $this->input->post('filter');
        if ($filter != 0) {
            $_SESSION['filter'] = $filter;
        } else {
            unset($_SESSION['filter']);
        }
        redirect('kategori');
    }

    public function insert(): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->insert($tip);
        redirect('kategori/index');
    }

    public function update($id = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->update($id);
        redirect('kategori/index');
    }

    public function delete($id = ''): void
    {
        $this->redirect_hak_akses('h', 'kategori/index');
        $this->web_kategori_model->delete($id);
        redirect('kategori/index');
    }

    public function delete_all($p = 1, $o = 0): void
    {
        $this->redirect_hak_akses('h', "kategori/index/{$p}/{$o}");
        $this->web_kategori_model->delete_all();
        redirect("kategori/index/{$p}/{$o}");
    }

    public function kategori_lock($id = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->kategori_lock($id, 1);
        redirect("kategori/index/{$p}/{$o}");
    }

    public function kategori_unlock($id = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->kategori_lock($id, 2);
        redirect("kategori/index/{$p}/{$o}");
    }

    public function insert_sub_kategori($kategori = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->insert_sub_kategori($kategori);
        redirect("kategori/sub_kategori/{$kategori}");
    }

    public function update_sub_kategori($kategori = '', $id = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->update_sub_kategori($id);
        redirect("kategori/sub_kategori/{$kategori}");
    }

    public function delete_sub_kategori($kategori = '', $id = 0): void
    {
        $this->redirect_hak_akses('h', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->delete_sub($id);
        redirect("kategori/sub_kategori/{$kategori}");
    }

    public function delete_all_sub_kategori($kategori = ''): void
    {
        $this->redirect_hak_akses('h', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->delete_all();
        redirect("kategori/sub_kategori/{$kategori}");
    }

    public function kategori_lock_sub_kategori($kategori = '', $id = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->kategori_lock($id, 1);
        redirect("kategori/sub_kategori/{$kategori}");
    }

    public function kategori_unlock_sub_kategori($kategori = '', $id = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->kategori_lock($id, 2);
        redirect("kategori/sub_kategori/{$kategori}");
    }

    public function urut($id = 0, $arah = 0, $kategori = ''): void
    {
        $this->redirect_hak_akses('u', $_SERVER['HTTP_REFERER']);
        $this->web_kategori_model->urut($id, $arah, $kategori);
        if ($kategori != '') {
            redirect("kategori/sub_kategori/{$kategori}");
        } else {
            redirect('kategori/index');
        }
    }
}
