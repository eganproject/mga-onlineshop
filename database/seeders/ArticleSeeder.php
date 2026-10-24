<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Artikel contoh (tanpa gambar sampul; unggah sendiri lewat Admin › Artikel). */
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect([
            ['tips-trik', 'Tips & Trik', 'Cara merawat display akrilik dan perlengkapan promosi supaya awet.'],
            ['panduan-belanja', 'Panduan Belanja', 'Bingung memilih? Panduan singkat sebelum membeli media promosi.'],
            ['kabar-toko', 'Kabar Toko', 'Promo, produk baru, dan info layanan MGA.'],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => ArticleCategory::updateOrCreate(['slug' => $c[0]], ['name' => $c[1], 'description' => $c[2], 'sort_order' => $i])]);

        $author = User::where('email', 'admin@mga.test')->first();

        $articles = [
            [
                'slug' => 'cara-merawat-akrilik-agar-tetap-bening',
                'category' => 'tips-trik',
                'title' => '6 Cara Merawat Display Akrilik Agar Tetap Bening',
                'excerpt' => 'Akrilik yang kusam dan penuh baret bikin promosi kurang menarik. Ini cara membersihkan dan merawat frame, brosur tempel, dan box akrilik.',
                'tags' => ['akrilik', 'perawatan', 'tips'],
                'products' => 'Akrilik',
                'days' => 2,
                'body' => '<p>Akrilik dipilih karena bening seperti kaca tetapi jauh lebih ringan dan tidak mudah pecah. Supaya tetap jernih bertahun-tahun, rawat dengan cara yang tepat.</p>'
                    .'<h2>1. Lepas plastik pelindung saat dipasang</h2><p>Akrilik baru dilapisi plastik pelindung di kedua sisi. Biarkan selama pengiriman dan pemasangan, lalu lepas saat display siap dipakai.</p>'
                    .'<h2>2. Pakai kain microfiber</h2><p>Lap dengan kain microfiber yang lembut. Tisu kering dan kain kasar bisa meninggalkan baret halus.</p>'
                    .'<h2>3. Cukup air sabun lembut</h2><p>Campur sedikit sabun cuci piring dengan air, usap perlahan, lalu keringkan. Hindari alkohol, thinner, dan pembersih kaca beramonia karena bisa membuat akrilik retak rambut.</p>'
                    .'<h3>Tanda akrilik salah dirawat</h3><ul><li>Muncul retak halus di sekitar lubang baut</li><li>Permukaan berkabut walau sudah dilap</li><li>Baret melingkar bekas lap kasar</li></ul>'
                    .'<blockquote><p>Simpan akrilik lembaran berdiri atau ditumpuk rata dengan alas kertas, jangan ditindih benda berat.</p></blockquote>'
                    .'<h2>4. Jauhkan dari panas langsung</h2><p>Frame akrilik di dekat lampu sorot atau kompor bisa melengkung. Beri jarak yang cukup dari sumber panas.</p>',
            ],
            [
                'slug' => 'panduan-memilih-stand-banner',
                'category' => 'panduan-belanja',
                'title' => 'X Banner, Tripod, atau Type A? Panduan Memilih Stand Banner',
                'excerpt' => 'Setiap stand banner punya kelebihan sendiri. Kenali bedanya supaya promosi di depan toko, kafe, atau event tampil maksimal.',
                'tags' => ['banner', 'panduan', 'promosi'],
                'products' => 'Banner',
                'days' => 6,
                'body' => '<p>Stand banner adalah media promosi paling praktis: mudah dipindah, cepat dipasang, dan posternya bisa diganti kapan saja.</p>'
                    .'<h2>X Banner & Y Banner</h2><p>Pilihan paling hemat untuk poster 60 × 160 cm. Rangkanya ringan dan cocok untuk event, bazar, atau promo musiman di dalam ruangan.</p>'
                    .'<h2>Tripod banner</h2><ol><li><strong>1 sisi</strong>: untuk poster berbingkai akrilik, menu, atau foto wedding.</li><li><strong>2 sisi</strong>: terlihat dari dua arah, pas di lorong mall atau pintu masuk.</li><li><strong>Type H miring</strong>: posisi poster miring sehingga nyaman dibaca, cocok untuk daftar menu.</li></ol>'
                    .'<h2>Type A / sidewalk</h2><p>Berdiri kokoh di trotoar depan toko dan bisa dilipat saat tutup. Pilih bahan aluminium supaya tidak berkarat walau sering terkena hujan.</p>'
                    .'<p>Butuh banyak untuk cabang atau event? Chat admin MGA untuk harga grosir.</p>',
            ],
            [
                'slug' => 'mga-kini-hadir-online',
                'category' => 'kabar-toko',
                'title' => 'MGA Kini Hadir Online: Belanja Perlengkapan Promosi Lebih Mudah',
                'excerpt' => 'Sekarang kamu bisa belanja tripod banner, frame akrilik, dan tempat brosur langsung dari website kami, lengkap dengan pembayaran aman dan lacak pesanan.',
                'tags' => ['info toko', 'promo'],
                'products' => null,
                'days' => 10,
                'body' => '<p>Kabar gembira! Toko online resmi Media Gudang Acc sudah bisa dipakai untuk berbelanja langsung dari rumah atau kantor.</p>'
                    .'<h2>Yang bisa kamu lakukan</h2><ul><li>Bayar lewat transfer bank, e-wallet, QRIS, atau kartu kredit</li><li>Pilih kurir favorit dan lihat ongkir sebelum bayar</li><li>Lacak pesanan langsung dari halaman akun</li><li>Simpan produk incaran di wishlist</li></ul>'
                    .'<p>Pesanan sebelum pukul 16.00 WIB dikirim di hari yang sama dari gudang kami. Pakai kode <strong>HEMAT10</strong> untuk diskon 10% di pesanan pertamamu!</p>',
            ],
        ];

        foreach ($articles as $data) {
            $article = Article::updateOrCreate(['slug' => $data['slug']], [
                'article_category_id' => $categories[$data['category']]->id,
                'title' => $data['title'],
                'excerpt' => $data['excerpt'],
                'body' => $data['body'],
                'tags' => $data['tags'],
                'status' => ArticleStatus::Published,
                'published_at' => now()->subDays($data['days'])->setTime(9, 0),
                'reading_minutes' => Article::readingMinutes($data['body']),
            ]);

            if ($author && ! $article->author_id) {
                $article->author()->associate($author)->save();
            }

            if ($data['products']) {
                $ids = Product::active()->where('name', 'like', '%'.$data['products'].'%')->take(4)->pluck('id');
                $article->products()->sync($ids->values()->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());
            }
        }
    }
}
