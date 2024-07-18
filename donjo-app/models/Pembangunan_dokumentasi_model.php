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

class Pembangunan_dokumentasi_model extends MY_Model
{
    public const ORDER_ABLE = [
        3 => 'CAST(d.persentase as UNSIGNED INTEGER)',
        4 => 'd.keterangan',
        5 => 'd.created_at',
    ];

    protected $table = 'pembangunan_ref_dokumentasi';

    public function get_data($id, string $search = '')
    {
        $this->config_id('d');

        $this->db->select('d.*')
            ->from("{$this->table} d")
            ->join('pembangunan p', sprintf('d.id_pembangunan = p.id and p.config_id = %s', identitas('id')))
            ->where('d.id_pembangunan', $id);

        if ($search !== '' && $search !== '0') {
            $this->db
                ->group_start()
                ->like('d.keterangan', $search)
                ->group_end();
        }

        return $this->db;
    }

    public function insert($id_pembangunan = 0): void
    {
        $post = $this->input->post();

        $data['id_pembangunan'] = $id_pembangunan;
        $data['gambar']         = $this->upload_gambar_pembangunan('gambar');
        $data['persentase']     = $post['persentase'] ?: $post['id_persentase'];
        $data['keterangan']     = $post['keterangan'];
        $data['config_id']      = identitas('id');
        $data['created_at']     = date('Y-m-d H:i:s');
        $data['updated_at']     = date('Y-m-d H:i:s');

        if (empty($data['gambar'])) {
            unset($data['gambar']);
        }

        unset($data['file_gambar'], $data['old_gambar']);

        if ($outp = $this->db->insert($this->table, $data)) {
            $outp = $outp && $this->perubahan_anggaran($id_pembangunan, $data['persentase'], bilangan($this->input->post('perubahan_anggaran')));
        }

        status_sukses($outp);
    }

    public function update($id = 0, $id_pembangunan = 0): void
    {
        $post = $this->input->post();

        $data['id_pembangunan'] = $id_pembangunan;
        $data['gambar']         = $this->upload_gambar_pembangunan('gambar', $id);
        $data['persentase']     = $post['persentase'] ?: $post['id_persentase'];
        $data['keterangan']     = $post['keterangan'];
        $data['updated_at']     = date('Y-m-d H:i:s');

        if (empty($data['gambar'])) {
            unset($data['gambar']);
        }

        unset($data['file_gambar'], $data['old_gambar']);

        $this->config_id();

        if ($outp = $this->db->where('id', $id)->update($this->table, $data)) {
            $outp = $outp && $this->perubahan_anggaran($id_pembangunan, $data['persentase'], bilangan($this->input->post('perubahan_anggaran')));
        }

        status_sukses($outp);
    }

    private function upload_gambar_pembangunan(string $jenis, $id = null)
    {
        // Inisialisasi library 'upload'
        $this->load->library('MY_Upload', null, 'upload');
        $this->uploadConfig = [
            'upload_path'   => LOKASI_GALERI,
            'allowed_types' => 'jpg|jpeg|png',
            'max_size'      => 1024, // 1 MB
        ];
        $this->upload->initialize($this->uploadConfig);

        $uploadData = null;
        // Adakah berkas yang disertakan?
        $adaBerkas = ! empty($_FILES[$jenis]['name']);
        if (! $adaBerkas) {
            return null;
        }

        // Upload sukses
        if ($this->upload->do_upload($jenis)) {
            $uploadData = $this->upload->data();
            // Buat nama file unik agar url file susah ditebak dari browser
            $namaFileUnik = tambahSuffixUniqueKeNamaFile($uploadData['file_name']);
            // Ganti nama file asli dengan nama unik untuk mencegah akses langsung dari browser
            $fileRenamed = rename(
                $this->uploadConfig['upload_path'] . $uploadData['file_name'],
                $this->uploadConfig['upload_path'] . $namaFileUnik
            );
            // Ganti nama di array upload jika file berhasil di-rename --
            // jika rename gagal, fallback ke nama asli
            $uploadData['file_name'] = $fileRenamed ? $namaFileUnik : $uploadData['file_name'];

            // Hapus file lama
            unlink(LOKASI_GALERI . $this->input->post('old_foto'));
        }
        // Upload gagal
        else {
            session_error($this->upload->display_errors(null, null));

            return redirect("admin_pembangunan/dokumentasi_form/{$id}");
        }

        return (empty($uploadData)) ? null : $uploadData['file_name'];
    }

    public function delete($id): void
    {
        $data = $this->find($id);

        $this->config_id();

        if ($outp = $this->db->where('id', $id)->delete($this->table)) {
            // Hapus file
            unlink(LOKASI_GALERI . $data->gambar);
            $outp = $outp && $this->perubahan_anggaran($data->id_pembangunan, $data->persentase, 0);
        }

        status_sukses($outp);
    }

    public function find($id)
    {
        $this->config_id();

        return $this->db->where('id', $id)
            ->get($this->table)
            ->row();
    }

    public function find_dokumentasi($id_pembangunan)
    {
        $this->config_id();

        return $this->db->where('id_pembangunan', $id_pembangunan)
            ->order_by('CAST(persentase as UNSIGNED INTEGER)')
            ->get($this->table)
            ->result();
    }

    public function perubahan_anggaran($id_pembangunan = 0, $persentase = 0, $perubahan_anggaran = 0)
    {
        if (in_array($persentase, ['100', '100%'])) {
            $this->config_id();

            return $this->db
                ->where('id', $id_pembangunan)
                ->update('pembangunan', [
                    'perubahan_anggaran' => $perubahan_anggaran,
                ]);
        }

        return true;
    }
}
