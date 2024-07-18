<?php  defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="container">
    <?php if($transparansi) $this->load->view($folder_themes .'/partials/apbdesa', $transparansi) ?>
</div>

<?php 
    $social_media = [
        'facebook' => [
            'color' => 'bg-blue-600',
            'icon' => 'fa-facebook-f'
        ],
        'twitter' => [
            'color' => 'bg-blue-400',
            'icon' => 'fa-twitter'
        ],
        'instagram' => [
            'color' => 'bg-pink-500',
            'icon' => 'fa-instagram'
        ],
        'telegram' => [
            'color' => 'bg-blue-500',
            'icon' => 'fa-telegram'
        ],
        'whatsapp' => [
            'color' => 'bg-green-500',
            'icon' => 'fa-whatsapp'
        ],
        'youtube' => [
            'color' => 'bg-red-500',
            'icon' => 'fa-youtube'
        ]
    ];
?>

<?php foreach($sosmed as $social) : ?>
    <?php if($social['link']) : ?>  
        <?php $social_media[strtolower($social['nama'])]['link'] = $social['link']; ?>
    <?php endif ?>
<?php endforeach ?>

<?php $this->load->view($folder_themes .'/commons/back_to_top') ?>

<footer class="container mx-auto lg:px-5 px-3 pt-5 footer">
    <div class="bg-zinc-700 text-white py-5 px-5 rounded-t-xl text-sm flex flex-col gap-3 lg:flex-row justify-between items-center text-center lg:text-left">
        <div class="lg:w-1/2">
            <h3 class="text-lg font-bold pb-2">Profil</h3>
            <p class="pb-3">Desa Ngaglik - Kecamatan Bulukerto<br>Kabupaten Wonogiri - Jawa Tengah</p>
            <p>Website desa dibangun sebagai media informasi resmi desa dan mempermudah pelayanan publik</p>
            <p>Pembuatan website ini diinisiasi oleh KKN 275 Universitas Sebelas Maret.</p>
        </div>
        <div class="lg:w-1/2">
            <h3 class="text-lg font-bold pb-2">Kontak Kami</h3>
            <p class="pb-2">Jalan Pringgondani No. 2, Dusun Bendo<br>Kode Pos 57697</p>
            <p><i class="fa fa-phone pb-2"></i> 082139836900<br><i class="fa fa-envelope pb-2"></i> desangaglikbulukerto@gmail.com</p>
        </div>
    </div>
    <div class="bg-zinc-800 text-white py-2 text-center text-xs">
        <p>Hak cipta 2024 © Pemerintah Desa Ngaglik.</p>
    </div>
    <div class="bg-zinc-800 text-white py-2 text-center">
        <ul class="inline-flex space-x-2">
            <?php foreach($social_media as $sm) : ?>
                <?php if($link = $sm['link']) : ?>
                <li><a href="<?= $link ?>" class="inline-flex items-center justify-center <?= $sm['color'] ?> h-8 w-8 rounded-full"><i class="fab fa-lg <?= $sm['icon'] ?>"></i></a></li>
                <?php endif ?>
            <?php endforeach ?>
        </ul>
    </div>
</footer>
