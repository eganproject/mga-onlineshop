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
            ['tips-trik', 'Tips & Trik', 'Cara merawat dan memaksimalkan gadget sehari-hari.'],
            ['panduan-belanja', 'Panduan Belanja', 'Bingung memilih? Panduan singkat sebelum membeli aksesoris.'],
            ['kabar-toko', 'Kabar Toko', 'Promo, produk baru, dan info layanan MGA.'],
        ])->mapWithKeys(fn ($c, $i) => [$c[0] => ArticleCategory::updateOrCreate(['slug' => $c[0]], ['name' => $c[1], 'description' => $c[2], 'sort_order' => $i])]);

        $author = User::where('email', 'admin@mga.test')->first();

        $articles = [
            [
                'slug' => 'cara-merawat-baterai-hp-agar-awet',
                'category' => 'tips-trik',
                'title' => '7 Cara Merawat Baterai HP Agar Awet Bertahun-tahun',
                'excerpt' => 'Baterai cepat drop? Kebiasaan kecil saat mengisi daya ternyata sangat berpengaruh. Ini tujuh kebiasaan yang bikin baterai lebih awet.',
                'tags' => ['baterai', 'charger', 'tips'],
                'products' => 'charger',
                'days' => 2,
                'body' => '<p>Baterai lithium-ion di HP modern dirancang untuk ratusan siklus pengisian. Tapi cara kita mengisi daya setiap hari menentukan seberapa cepat kapasitasnya turun.</p>'
                    .'<h2>1. Jaga di kisaran 20–80%</h2><p>Mengisi penuh sampai 100% lalu membiarkannya turun ke 0% membuat sel baterai bekerja paling berat. Usahakan mengisi saat tersisa sekitar 20% dan cabut di kisaran 80%.</p>'
                    .'<h2>2. Pakai charger yang tepat</h2><p>Gunakan charger original atau bersertifikat yang mendukung protokol fast charging HP-mu (PD, QC, atau PPS). Charger abal-abal sering tidak stabil dan membuat baterai cepat panas.</p>'
                    .'<h2>3. Hindari panas berlebih</h2><p>Panas adalah musuh utama baterai. Jangan mengisi daya sambil main game berat, dan lepas casing tebal bila HP terasa panas saat dicas.</p>'
                    .'<h3>Tanda baterai mulai bermasalah</h3><ul><li>Persentase turun drastis dalam waktu singkat</li><li>HP mati sendiri padahal masih 15–20%</li><li>Bodi HP menggembung</li></ul>'
                    .'<blockquote><p>Bila baterai sudah menggembung, segera hentikan pemakaian dan ganti di servis resmi.</p></blockquote>'
                    .'<h2>4. Kabel juga berpengaruh</h2><p>Kabel data yang rusak atau tipis bisa membuat arus tidak stabil. Pilih kabel dengan dukungan daya sesuai charger, misalnya 60W atau 100W untuk USB-C.</p>',
            ],
            [
                'slug' => 'panduan-memilih-power-bank',
                'category' => 'panduan-belanja',
                'title' => 'Panduan Memilih Power Bank: Kapasitas, Output, dan Keamanan',
                'excerpt' => 'Angka mAh bukan satu-satunya patokan. Kenali output, fitur fast charging, dan sertifikasi sebelum membeli power bank.',
                'tags' => ['power bank', 'panduan'],
                'products' => 'power',
                'days' => 6,
                'body' => '<p>Power bank adalah teman wajib saat bepergian. Supaya tidak salah pilih, perhatikan beberapa hal berikut.</p>'
                    .'<h2>Kapasitas sesuai kebutuhan</h2><p>Untuk HP saja, 10.000 mAh sudah cukup untuk 1–2 kali pengisian penuh. Bila juga ingin mengisi tablet atau laptop, pilih 20.000 mAh ke atas dengan output USB-C PD.</p>'
                    .'<h2>Perhatikan output</h2><ol><li><strong>18–20W</strong>: cukup untuk sebagian besar HP.</li><li><strong>30–45W</strong>: fast charging HP flagship dan tablet.</li><li><strong>65W+</strong>: bisa untuk laptop USB-C.</li></ol>'
                    .'<h2>Keamanan</h2><p>Pilih power bank dengan proteksi arus pendek, suhu, dan pengisian berlebih. Untuk dibawa naik pesawat, kapasitas maksimal umumnya 100Wh (sekitar 27.000 mAh).</p>',
            ],
            [
                'slug' => 'mga-kini-hadir-online',
                'category' => 'kabar-toko',
                'title' => 'MGA Kini Hadir Online: Belanja Aksesoris Lebih Mudah',
                'excerpt' => 'Sekarang kamu bisa belanja aksesoris original langsung dari website kami, lengkap dengan pembayaran aman dan lacak pesanan.',
                'tags' => ['info toko', 'promo'],
                'products' => null,
                'days' => 10,
                'body' => '<p>Kabar gembira! Toko online resmi kami sudah bisa dipakai untuk berbelanja langsung dari rumah.</p>'
                    .'<h2>Yang bisa kamu lakukan</h2><ul><li>Bayar lewat transfer bank, e-wallet, QRIS, atau kartu kredit</li><li>Pilih kurir favorit dan lihat ongkir sebelum bayar</li><li>Lacak pesanan langsung dari halaman akun</li><li>Simpan produk incaran di wishlist</li></ul>'
                    .'<p>Pakai kode <strong>HEMAT10</strong> untuk diskon 10% di pesanan pertamamu. Selamat berbelanja!</p>',
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
