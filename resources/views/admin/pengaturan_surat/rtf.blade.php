<div class="tab-pane" id="template-surat">

    @include('admin.pengaturan_surat.kembali')
    <div class="box-body">
        <div>
            @if (can('u'))
                <input type="text" class="hidden" name="url_surat" value="{{ $suratMaster->url_surat }}" />
                <label class="control-label" for="nama">Unggah Template Format Surat</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="file_path" name="surat">
                    <input type="file" class="hidden" id="file" name="surat">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-info" id="file_browser"><i class="fa fa-search"></i>&nbsp;Browse</button>
                        @if ($suratMaster->url_surat_sistem)
                            <a href="{{ base_url($suratMaster->url_surat_sistem) }}" class="btn btn-success" title="Unduh Template Sistem" target="_blank"><i class="fa fa-download"></i>&nbsp;Template Sistem</a>
                        @endif
                        @if ($suratMaster->url_surat_desa)
                            <a href="{{ base_url($suratMaster->url_surat_desa) }}" class="btn btn-warning" title="{{ SebutanDesa('Unduh Template [Desa]') }} " target="_blank"><i class="fa fa-download"></i>&nbsp;{{ SebutanDesa('Template [Desa]') }} </a>
                            <a href="#" data-href="{{ route('surat_master.delete_template_desa', $suratMaster->url_surat) }}" class="btn bg-maroon btn-flat btn-sm" title="{{ SebutanDesa('Hapus Template [Desa]') }}" data-toggle="modal" data-target="#confirm-delete"><i
                                    class="fa fa-trash-o"></i></a>
                        @endif
                    </span>
                </div>
            @endif
        </div>
    </div>
</div>
<div class="tab-pane" id="form-isian">

    @include('admin.pengaturan_surat.kembali')

    <div class="box-body">
        <h5><i class="fa fa-info-circle"></i> <strong>Kode Isian Format Surat {{ $suratMaster->nama }}</strong></h5>
        <hr>
        <div class="row">
            <div class="col-sm-7">
                <p>
                    Kode isian pada tabel di bawah dapat dimasukkan ke dalam template/Format RTF Ekspor Dok untuk jenis
                    surat ini.
                </p>
                <p>
                    Pada waktu mencetak surat Ekspor Dok memakai template itu, kode isian di bawah akan diganti dengan
                    data yang diisi pada form isian untuk jenis surat ini.
                </p>
            </div>
            <div class="col-sm-5">
                <table class="table table-bordered table-hover">
                    <thead class="bg-gray disabled color-palette">
                        <tr>
                            <th>KODE</th>
                            <th>DATA DI FORM ISIAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suratMaster->kode_isian as $kode => $keterangan)
                            <tr>
                                <td style="padding-top : 10px;padding-bottom : 10px; ">[form_{{ $kode }}]</td>
                                <td>{{ $keterangan }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="padat" colspan="2">Data tidak tersedia</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel"><i class="fa fa-exclamation-triangle text-red"></i> Konfirmasi</h4>
            </div>
            <div class="modal-body btn-info">
                Apakah Anda yakin ingin menghapus data ini?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-social btn-warning btn-sm" data-dismiss="modal"><i class="fa fa-sign-out"></i> Tutup</button>
                <a class="btn-ok">
                    <a href="{{ route('surat_master.delete_template_desa', $suratMaster->url_surat) }}" class="btn btn-social btn-danger btn-sm" id="ok-delete"><i class="fa fa-trash-o"></i> Hapus</a>
                </a>
            </div>
        </div>
    </div>
</div>
