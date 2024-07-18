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

use App\Libraries\TinyMCE;
use App\Models\FormatSurat;
use App\Models\PermohonanSurat;

defined('BASEPATH') || exit('No direct script access allowed');

class Permohonan_surat_admin extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model(['permohonan_surat_model', 'penduduk_model', 'surat_model', 'keluarga_model', 'mailbox_model', 'surat_master_model']);
        $this->modul_ini     = 'layanan-surat';
        $this->sub_modul_ini = 'permohonan-surat';
    }

    public function index()
    {
        return view('admin.permohonan_surat.index', [
            'list_status_permohonan' => PermohonanSurat::STATUS_PERMOHONAN,
        ]);
    }

    public function datatables()
    {
        if ($this->input->is_ajax_request()) {
            return datatables(PermohonanSurat::status((string) $this->input->get('status')))
                ->addIndexColumn()
                ->addColumn('aksi', static function ($row): string {
                    $aksi = '';

                    if (can('u')) {
                        if ($row->status == PermohonanSurat::BELUM_LENGKAP) {
                            $aksi .= '<a class="btn btn-social bg-navy btn-sm btn-proses" title="Surat Belum Lengkap" style="width: 170px"><i class="fa fa-info-circle"></i> ' . PermohonanSurat::STATUS_PERMOHONAN[PermohonanSurat::BELUM_LENGKAP] . '</a> ';
                        } elseif ($row->status == PermohonanSurat::SEDANG_DIPERIKSA) {
                            $aksi .= '<a href="' . route('permohonan_surat_admin/periksa', $row->id) . '" class="btn btn-social btn-info btn-sm pesan-hover" title="Klik untuk memeriksa" style="width: 170px"><i class="fa fa-spinner"></i>' . PermohonanSurat::STATUS_PERMOHONAN[PermohonanSurat::SEDANG_DIPERIKSA] . '</a> ';
                        } elseif ($row->status == PermohonanSurat::MENUNGGU_TANDA_TANGAN) {
                            if (in_array($row->surat->jenis, FormatSurat::TINYMCE) && (setting('verifikasi_sekdes') || setting('verifikasi_kades'))) {
                                $aksi .= '<a class="btn btn-social bg-purple btn-sm btn-proses" title="Surat Menunggu Tandatangan" style="width: 170px"><i class="fa fa-edit"></i>' . PermohonanSurat::STATUS_PERMOHONAN[PermohonanSurat::MENUNGGU_TANDA_TANGAN] . '</a> ';
                            } else {
                                $aksi .= '<a href="' . route("permohonan_surat_admin/proses/{$row->id}/3") . '" class="btn btn-social bg-purple btn-sm" title="Surat Menunggu Tandatangan" style="width: 170px"><i class="fa fa-edit"></i>' . PermohonanSurat::STATUS_PERMOHONAN[PermohonanSurat::MENUNGGU_TANDA_TANGAN] . '</a> ';
                            }
                        } elseif ($row->status == PermohonanSurat::SIAP_DIAMBIL) {
                            $aksi .= '<a href="' . route("permohonan_surat_admin/proses/{$row->id}/4") . '" class="btn btn-social bg-orange btn-sm pesan-hover" title="Klik jika telah diambil" style="width: 170px"><i class="fa fa-thumbs-o-up"></i>' . PermohonanSurat::STATUS_PERMOHONAN[PermohonanSurat::SIAP_DIAMBIL] . '</a> ';
                        } elseif ($row->status == PermohonanSurat::SUDAH_DIAMBIL) {
                            $aksi .= '<a class="btn btn-social btn-success btn-sm btn-proses" title="Surat Sudah Diambil" style="width: 170px"><i class="fa fa-check"></i>' . PermohonanSurat::STATUS_PERMOHONAN[PermohonanSurat::SUDAH_DIAMBIL] . '</a> ';
                        } else {
                            $aksi .= '<a class="btn btn-social btn-danger btn-sm btn-proses" title="Surat Dibatalkan" style="width: 170px"><i class="fa fa-times"></i>' . PermohonanSurat::STATUS_PERMOHONAN[PermohonanSurat::DIBATALKAN] . '</a> ';

                            if (can('h') && auth()->id == super_admin()) {
                                $aksi .= '<a href="#" data-href="' . route('permohonan_surat_admin.delete', $row->id) . '" class="btn bg-maroon btn-sm"  title="Hapus Data" data-toggle="modal" data-target="#confirm-delete"><i class="fa fa-trash"></i></a> ';
                            }
                        }
                    }

                    return $aksi;
                })
                ->editColumn('no_antrian', static fn ($row) => get_antrian($row->no_antrian))
                ->editColumn('created_at', static fn ($row) => tgl_indo2($row->created_at))
                ->rawColumns(['aksi'])
                ->make();
        }

        return show_404();
    }

    public function periksa($id = ''): void
    {
        // Cek hanya status = 1 (sedang diperiksa) yg boleh di proses
        $periksa = PermohonanSurat::whereStatus(PermohonanSurat::SEDANG_DIPERIKSA)->find($id);

        if (! $id || ! $periksa) {
            show_404();
        }
        $url = $periksa->surat->url_surat;

        $data['periksa']      = $periksa;
        $data['surat']        = $periksa->surat;
        $data['url']          = $url;
        $data['list_dokumen'] = $this->penduduk_model->list_dokumen($periksa->id_pemohon);
        $data['individu']     = $this->surat_model->get_penduduk($periksa->id_pemohon);

        $this->get_data_untuk_form($url, $data);
        $data['isian_form']        = json_encode($this->ambil_isi_form($data['periksa']['isian_form']), JSON_THROW_ON_ERROR);
        $data['surat_url']         = rtrim($_SERVER['REQUEST_URI'], '/clear');
        $data['syarat_permohonan'] = $this->permohonan_surat_model->get_syarat_permohonan($id);
        $data['form_action']       = site_url("surat/periksa_doc/{$id}/{$url}");
        $data['form_surat']        = 'surat/form_surat.php';

        $data_form = $this->surat_model->get_data_form($url);
        if (is_file($data_form)) {
            include $data_form;
        }

        if (in_array($data['surat']['jenis'], FormatSurat::TINYMCE)) {
            $data['list_dokumen'] = empty($_POST['nik']) ? null : $this->penduduk_model->list_dokumen($data['individu']['id']);
            $data['form_action']  = route("surat/pratinjau/{$url}/{$id}");
            $data['form_surat']   = 'surat/form_surat_tinymce.php';
        }

        $pesan   = 'Permohonan Surat - ' . $periksa->surat->nama . ' - sedang dalam proses oleh operator';
        $judul   = 'Permohonan Surat - ' . $periksa->surat->nama . ' - sedang dalam proses';
        $payload = '/layanan';
        // kirim notifikasi fcm
        $this->kirim_notifikasi_penduduk($periksa->id_pemohon, $pesan, $judul, $payload);

        $this->render('mandiri/periksa_surat', $data);
    }

    public function proses($id = '', $status = ''): void
    {
        $this->permohonan_surat_model->proses($id, $status);

        redirect('permohonan_surat_admin');
    }

    // TODO:: Duplikasi dengan kode yang ada di donjo-app/controllers/Surat.php
    private function get_data_untuk_form($url, array &$data): void
    {
        // RTF
        if (in_array($data['surat']['jenis'], FormatSurat::RTF)) {
            $data['config']    = $data['lokasi'] = $this->header['desa'];
            $data['perempuan'] = $this->surat_model->list_penduduk_perempuan();
            $data['anggota']   = $this->keluarga_model->list_anggota($data['individu']['id_kk']);
        }

        // Panggil 1 penduduk berdasarkan datanya sendiri
        $data['penduduk'] = [$data['periksa']['penduduk']];

        $data['surat_terakhir']     = $this->surat_model->get_last_nosurat_log($url);
        $data['input']              = $this->input->post();
        $data['input']['nomor']     = $data['surat_terakhir']['no_surat_berikutnya'];
        $data['format_nomor_surat'] = $this->penomoran_surat_model->format_penomoran_surat($data);

        $tinymce           = new TinyMCE();
        $penandatangan     = $tinymce->formPenandatangan();
        $data['pamong']    = $penandatangan['penandatangan'];
        $data['atas_nama'] = $penandatangan['atas_nama'];
    }

    private function ambil_isi_form($isian_form)
    {
        $hapus = ['url_surat', 'url_remote', 'nik', 'id_surat', 'nomor', 'pilih_atas_nama', 'pamong', 'pamong_nip', 'jabatan', 'pamong_id'];

        foreach ($hapus as $kolom) {
            unset($isian_form[$kolom]);
        }

        return $isian_form;
    }

    public function konfirmasi($id_permohonan = 0, $tipe = 0): void
    {
        $data['form_action'] = site_url("permohonan_surat_admin/kirim_pesan/{$id_permohonan}/{$tipe}");

        $this->load->view('surat/form/konfirmasi_permohonan', $data);
    }

    public function kirim_pesan($id_permohonan = 0, $tipe = 0): void
    {
        $periksa = $this->permohonan_surat_model->get_permohonan(['id' => $id_permohonan, 'status' => 1]);
        $pemohon = $this->surat_model->get_penduduk($periksa['id_pemohon']);
        $post    = $this->input->post();
        $judul   = ($tipe == 0) ? 'Perlu Dilengkapi' : 'Dibatalkan';
        $data    = [
            'subjek'     => 'Permohonan Surat ' . $surat['nama'] . ' ' . $judul,
            'komentar'   => $post['pesan'],
            'owner'      => $pemohon['nama'], // TODO : Gunakan id_pend
            'email'      => $pemohon['nik'], // TODO : Gunakan id_pend
            'permohonan' => $id_permohonan, // Menyimpan id_permohonan untuk link
            'tipe'       => 2,
            'status'     => 2,
        ];

        $this->mailbox_model->insert($data);
        $this->proses($id_permohonan, $tipe);

        redirect('permohonan_surat_admin');
    }

    public function delete($id = ''): void
    {
        $this->redirect_hak_akses('h', '', '', true);

        $delete = PermohonanSurat::where('status', PermohonanSurat::DIBATALKAN)->find($id) ?? show_404();

        if ($delete->delete()) {
            redirect_with('success', 'Berhasil Hapus Data');
        }

        redirect_with('error', 'Gagal Hapus Data');
    }

    public function tampilkan($id_dokumen, $id_pend = 0): void
    {
        $this->load->model('Web_dokumen_model');
        $berkas = $this->web_dokumen_model->get_nama_berkas($id_dokumen, $id_pend);

        if (! $id_dokumen || ! $id_pend || ! $berkas || ! file_exists(LOKASI_DOKUMEN . $berkas)) {
            $data['link_berkas'] = null;
        } else {
            $data = [
                'link_berkas' => site_url("{$this->controller}/tampilkan_berkas/{$id_dokumen}/{$id_pend}"),
                'tipe'        => get_extension($berkas),
                'link_unduh'  => site_url("{$this->controller}/unduh_berkas/{$id_dokumen}/{$id_pend}"),
            ];
        }
        $this->load->view('global/tampilkan', $data);
    }

    /**
     * Unduh berkas berdasarkan kolom dokumen.id
     *
     * @param int        $id_dokumen Id berkas pada koloam dokumen.id
     * @param mixed|null $id_pend
     * @param mixed      $tampil
     */
    public function unduh_berkas($id_dokumen, $id_pend = null, $tampil = false): void
    {
        // Ambil nama berkas dari database
        $data = $this->web_dokumen_model->get_dokumen($id_dokumen, $id_pend);
        ambilBerkas($data['satuan'], $this->controller, null, LOKASI_DOKUMEN, $tampil);
    }

    public function tampilkan_berkas($id_dokumen, $id_pend = null): void
    {
        $this->unduh_berkas($id_dokumen, $id_pend, $tampil = true);
    }
}
