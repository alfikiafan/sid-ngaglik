<?php defined('BASEPATH') || exit('No direct script access allowed'); ?>

<nav role="navigation" aria-label="navigation" class="breadcrumb">
    <ol>
        <li><a href="<?= site_url() ?>">Beranda</a></li>
        <li aria-current="page"><?= ucwords(setting('sebutan_pemerintah_desa')) ?></li>
    </ol>
</nav>
<h1 class="text-h2"><?= ucwords(setting('sebutan_pemerintah_desa')) ?></h1>
<?php if ($pemerintah) : ?>
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5 py-5">
        <?php foreach ($pemerintah as $data) : ?>
            <div class="space-y-3">
                <img class="h-44 w-66 object-cover object-center bg-gray-300 dark:bg-gray-600" src="<?= $data['foto'] ?>" alt="Foto <?= $data['nama'] ?>" />
                <div class="space-y-1 text-sm text-center z-10">
                    <span class="text-h6"><?= $data['nama'] ?></span>
                    <span class="block"><?= $data['jabatan'] ?></span>
                    <?php if ($data['pamong_niap']) : ?>
                        <span class="block"><?= $this->setting->sebutan_nip_desa ?> : <?= $data['pamong_niap'] ?></span>
                    <?php endif ?>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php else : ?>
    <p class="py-3"><?= ucwords(setting('sebutan_pemerintah_desa')) ?> tidak tersedia.</p>
<?php endif ?>