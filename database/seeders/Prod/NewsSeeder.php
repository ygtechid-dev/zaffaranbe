<?php

namespace Database\Seeders\Prod;

use Illuminate\Database\Seeder;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class NewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seed production news articles and categories for Zafaran Spa.
     */
    public function run(): void
    {
        // Get first admin as author
        $author = User::whereIn('role', ['super_admin', 'admin', 'owner'])->first();
        if (!$author) {
            $author = User::first();
        }

        if (!$author) {
            $this->command->warn('No user found. Skipping NewsSeeder.');
            return;
        }

        $branches = Branch::all();

        // ============================================================
        // 1. Seed News Categories
        // ============================================================
        $categories = [
            ['name' => 'Berita', 'is_global' => true],
            ['name' => 'Tips & Kecantikan', 'is_global' => true],
            ['name' => 'Promo', 'is_global' => true],
            ['name' => 'Update Layanan', 'is_global' => true],
            ['name' => 'Event', 'is_global' => true],
        ];

        foreach ($categories as $cat) {
            NewsCategory::updateOrCreate(
                ['name' => $cat['name']],
                ['is_global' => $cat['is_global']]
            );
        }

        $this->command->info('✅ News categories seeded.');

        // ============================================================
        // 2. Global News (Visible to all branches)
        // ============================================================
        $globalNews = [
            [
                'slug' => 'selamat-datang-di-zafaran-spa',
                'title' => 'Selamat Datang di Zafaran Beauty & Spa',
                'category' => 'Berita',
                'content' => "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\nSelamat datang di Zafaran Beauty & Spa! 🌿\n\nKami hadir untuk memberikan pengalaman perawatan terbaik bagi Anda. Dengan terapis profesional bersertifikat dan produk perawatan berkualitas tinggi, kami berkomitmen untuk membantu Anda mencapai keseimbangan antara kecantikan luar dan kesehatan dalam.\n\nLayanan unggulan kami meliputi:\n• Traditional Javanese Massage\n• Body Scrub & Lulur\n• Facial Treatment\n• Reflexology & Akupresur\n• Hair Spa & Creambath\n• Paket Perawatan Pengantin\n\nKunjungi cabang terdekat kami dan rasakan perbedaannya.\n\nSalam hangat,\nZafaran Beauty & Spa",
                'image_url' => 'https://images.unsplash.com/photo-1600334089648-b0d9d302427f?w=800&q=80',
                'published_at' => Carbon::now()->subDays(30),
                'views' => 250,
            ],
            [
                'slug' => 'tips-menjaga-kulit-wajah-sehat',
                'title' => '7 Tips Menjaga Kulit Wajah Tetap Sehat & Bercahaya',
                'category' => 'Tips & Kecantikan',
                'content' => "Kulit wajah yang sehat dan bercahaya adalah dambaan setiap orang. Berikut 7 tips yang bisa Anda terapkan sehari-hari:\n\n1. **Bersihkan Wajah 2x Sehari**\nGunakan pembersih yang sesuai dengan jenis kulit Anda, pagi dan malam hari.\n\n2. **Gunakan Sunscreen Setiap Hari**\nPaparan sinar UV adalah penyebab utama penuaan dini. Gunakan minimal SPF 30.\n\n3. **Minum Air Putih yang Cukup**\nMinimal 8 gelas per hari untuk menjaga kelembapan kulit dari dalam.\n\n4. **Rutin Facial Treatment**\nLakukan facial minimal sebulan sekali untuk membersihkan pori-pori secara mendalam.\n\n5. **Tidur yang Cukup**\nTidur 7-8 jam sehari agar kulit memiliki waktu regenerasi yang optimal.\n\n6. **Konsumsi Makanan Bergizi**\nPerbanyak buah, sayur, dan makanan kaya antioksidan.\n\n7. **Kelola Stres dengan Baik**\nStres berlebihan dapat memicu masalah kulit. Lakukan relaksasi rutin seperti massage.\n\nKunjungi Zafaran Spa untuk konsultasi perawatan kulit terbaik yang sesuai untuk Anda! 💆‍♀️",
                'image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=800&q=80',
                'published_at' => Carbon::now()->subDays(25),
                'views' => 180,
            ],
            [
                'slug' => 'manfaat-massage-untuk-kesehatan',
                'title' => '5 Manfaat Massage yang Wajib Anda Ketahui',
                'category' => 'Tips & Kecantikan',
                'content' => "Massage bukan sekadar kegiatan relaksasi, tetapi juga memiliki banyak manfaat untuk kesehatan tubuh. Berikut 5 manfaat utama massage:\n\n1. **Mengurangi Stres & Kecemasan**\nMassage terbukti secara ilmiah menurunkan kadar hormon kortisol (hormon stres) dan meningkatkan produksi serotonin serta dopamin.\n\n2. **Melancarkan Peredaran Darah**\nTekanan pada otot membantu melancarkan aliran darah ke seluruh tubuh, sehingga oksigen dan nutrisi tersampaikan dengan lebih baik.\n\n3. **Meredakan Nyeri Otot & Sendi**\nSangat efektif untuk mengatasi nyeri punggung, leher kaku, dan pegal-pegal akibat aktivitas sehari-hari.\n\n4. **Meningkatkan Kualitas Tidur**\nRelaksasi yang didapat dari massage membantu tubuh lebih mudah tidur nyenyak dan berkualitas.\n\n5. **Meningkatkan Sistem Imun**\nPenelitian menunjukkan bahwa massage rutin dapat meningkatkan jumlah sel darah putih yang berperan dalam melawan infeksi.\n\nJadwalkan sesi massage Anda sekarang di Zafaran Spa! 🧖‍♀️",
                'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=800&q=80',
                'published_at' => Carbon::now()->subDays(20),
                'views' => 145,
            ],
            [
                'slug' => 'panduan-memilih-perawatan-kulit',
                'title' => 'Panduan Memilih Perawatan Kulit Sesuai Jenis Kulit Anda',
                'category' => 'Tips & Kecantikan',
                'content' => "Setiap jenis kulit membutuhkan perawatan yang berbeda. Berikut panduan singkatnya:\n\n🔹 **Kulit Normal**\nPerawatan dasar dengan pembersih lembut dan pelembap ringan sudah cukup. Lakukan facial treatment untuk menjaga keseimbangan kulit.\n\n🔹 **Kulit Berminyak**\nGunakan pembersih berbasis gel dan hindari produk berbahan minyak. Facial dengan deep cleansing sangat direkomendasikan.\n\n🔹 **Kulit Kering**\nPilih pembersih berbasis krim dan pelembap yang kaya nutrisi. Body butter dan lulur dapat membantu mengatasi kulit kering.\n\n🔹 **Kulit Sensitif**\nHindari produk dengan kandungan alkohol dan parfum. Pilih perawatan berbahan alami yang lembut.\n\n🔹 **Kulit Kombinasi**\nGunakan produk yang berbeda untuk area berminyak (T-zone) dan area kering. Konsultasikan dengan terapis kami untuk perawatan yang tepat.\n\nBelum yakin dengan jenis kulit Anda? Kunjungi Zafaran Spa untuk konsultasi gratis bersama terapis profesional kami! ✨",
                'image_url' => 'https://images.unsplash.com/photo-1487412912498-0447578fcca8?w=800&q=80',
                'published_at' => Carbon::now()->subDays(15),
                'views' => 120,
            ],
            [
                'slug' => 'promo-member-zafaran-spa',
                'title' => 'Promo Spesial untuk Member Baru Zafaran Spa!',
                'category' => 'Promo',
                'content' => "🎉 PROMO SPESIAL MEMBER BARU! 🎉\n\nDaftar sebagai member Zafaran Spa dan nikmati berbagai keuntungan eksklusif:\n\n✅ Diskon 15% untuk kunjungan pertama\n✅ Poin loyalitas setiap transaksi\n✅ Akses prioritas booking online\n✅ Birthday treat spesial\n✅ Undangan event & workshop eksklusif\n✅ Harga khusus member untuk paket premium\n\n📱 Daftar melalui aplikasi Zafaran Spa dan gunakan kode NEWMEMBER untuk mengaktifkan promo!\n\nSyarat dan Ketentuan:\n• Berlaku untuk pendaftaran member baru\n• Tidak dapat digabung dengan promo lainnya\n• Berlaku di semua cabang Zafaran Spa\n\nJangan lewatkan kesempatan ini! Segera daftar dan rasakan pengalaman perawatan terbaik bersama kami. 💆‍♀️",
                'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?w=800&q=80',
                'published_at' => Carbon::now()->subDays(10),
                'views' => 200,
            ],
            [
                'slug' => 'pentingnya-self-care-rutinitas',
                'title' => 'Pentingnya Self-Care dalam Rutinitas Harian Anda',
                'category' => 'Tips & Kecantikan',
                'content' => "Di tengah kesibukan sehari-hari, banyak orang melupakan pentingnya merawat diri sendiri. Self-care bukan berarti egois — justru ini adalah investasi untuk kesehatan fisik dan mental Anda.\n\n🌸 **Apa itu Self-Care?**\nSelf-care adalah tindakan sadar untuk merawat kesehatan fisik, mental, dan emosional Anda.\n\n🌸 **Mengapa Self-Care Penting?**\n• Mengurangi risiko burnout\n• Meningkatkan produktivitas\n• Menjaga keseimbangan emosi\n• Meningkatkan kepercayaan diri\n• Memperkuat hubungan dengan orang lain\n\n🌸 **Ide Self-Care yang Bisa Anda Coba:**\n1. Luangkan 15 menit untuk meditasi pagi\n2. Jadwalkan sesi spa minimal 2x sebulan\n3. Lakukan olahraga ringan secara rutin\n4. Baca buku favorit sebelum tidur\n5. Manjakan kulit dengan masker wajah\n6. Nikmati aromatherapy massage di akhir pekan\n\nZafaran Spa hadir sebagai partner self-care Anda. Book jadwal perawatan Anda sekarang! 🌿",
                'image_url' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?w=800&q=80',
                'published_at' => Carbon::now()->subDays(5),
                'views' => 95,
            ],
            [
                'slug' => 'jadwal-operasional-ramadhan',
                'title' => 'Informasi Jadwal Operasional Selama Bulan Ramadhan',
                'category' => 'Berita',
                'content' => "Assalamu'alaikum Pelanggan Setia Zafaran Spa,\n\nMenyambut bulan suci Ramadhan, kami informasikan penyesuaian jadwal operasional di seluruh cabang Zafaran Spa:\n\n🕐 **Jadwal Ramadhan:**\n• Senin - Jumat: 10.00 - 20.00 WIB\n• Sabtu - Minggu: 09.00 - 21.00 WIB\n• Khusus Jumat: Tutup pukul 11.30 - 13.30 WIB\n\n🌙 **Paket Spesial Ramadhan:**\nNikmati paket \"Refresh After Fasting\" dengan harga spesial selama bulan Ramadhan. Paket ini dirancang khusus untuk memulihkan energi setelah berpuasa seharian.\n\n📞 Untuk reservasi, silakan hubungi cabang terdekat atau booking melalui aplikasi kami.\n\nSemoga ibadah puasa kita semua lancar dan penuh berkah. 🤲\n\nWassaalam,\nManajemen Zafaran Beauty & Spa",
                'image_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=800&q=80',
                'published_at' => Carbon::now()->subDays(2),
                'views' => 310,
            ],
        ];

        foreach ($globalNews as $newsData) {
            News::updateOrCreate(
                ['slug' => $newsData['slug']],
                array_merge($newsData, [
                    'status' => 'published',
                    'author_id' => $author->id,
                    'is_global' => true,
                    'branch_id' => null,
                ])
            );
        }

        $this->command->info('✅ ' . count($globalNews) . ' global news articles seeded.');

        // ============================================================
        // 3. Branch-Specific News (for first 3 branches if exist)
        // ============================================================
        if ($branches->isNotEmpty()) {
            $branchNewsTemplates = [
                [
                    'title_template' => 'Grand Opening Cabang Baru %s!',
                    'category' => 'Event',
                    'content_template' => "🎊 GRAND OPENING! 🎊\n\nDengan bangga kami mengumumkan pembukaan cabang baru Zafaran Spa di %s!\n\nNikmati promo pembukaan spesial:\n✅ Diskon 25%% untuk semua layanan\n✅ Free welcome drink\n✅ Door prize menarik\n\nPromo berlaku selama 2 minggu pertama. Jangan lewatkan kesempatan ini!\n\n📍 Lokasi: %s\n📱 Reservasi melalui aplikasi atau hubungi kami langsung.\n\nSampai jumpa di cabang baru kami! 🌿",
                    'image_url' => 'https://images.unsplash.com/photo-1560750588-73207b1ef5b8?w=800&q=80',
                    'days_ago' => 7,
                    'views' => 150,
                ],
                [
                    'title_template' => 'Layanan Baru di %s: Hot Stone Massage',
                    'category' => 'Update Layanan',
                    'content_template' => "✨ LAYANAN BARU! ✨\n\nKami dengan senang hati memperkenalkan layanan terbaru di %s:\n\n🪨 **Hot Stone Massage**\nRasakan sensasi relaksasi mendalam dengan batu vulkanik hangat yang ditempatkan di titik-titik energi tubuh.\n\nManfaat:\n• Meredakan ketegangan otot\n• Melancarkan sirkulasi darah\n• Mengurangi stres dan kecemasan\n• Meningkatkan fleksibilitas tubuh\n\n⏱ Durasi: 90 menit\n💰 Harga: Mulai dari Rp 350.000\n\nBook sekarang melalui aplikasi dan dapatkan diskon 10%% untuk booking pertama! 🧖‍♀️",
                    'image_url' => 'https://images.unsplash.com/photo-1600334129128-685c5582fd35?w=800&q=80',
                    'days_ago' => 3,
                    'views' => 75,
                ],
            ];

            foreach ($branches->take(3) as $branch) {
                foreach ($branchNewsTemplates as $template) {
                    $slug = Str::slug(sprintf($template['title_template'], $branch->name));

                    News::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'title' => sprintf($template['title_template'], $branch->name),
                            'category' => $template['category'],
                            'status' => 'published',
                            'content' => sprintf($template['content_template'], $branch->name, $branch->address ?? $branch->name),
                            'image_url' => $template['image_url'],
                            'author_id' => $author->id,
                            'is_global' => false,
                            'branch_id' => $branch->id,
                            'published_at' => Carbon::now()->subDays($template['days_ago']),
                            'views' => $template['views'],
                        ]
                    );
                }

                // Sync branch pivot
                $branchNews = News::where('branch_id', $branch->id)->get();
                foreach ($branchNews as $bn) {
                    if (!$bn->branches->contains($branch->id)) {
                        $bn->branches()->syncWithoutDetaching([$branch->id]);
                    }
                }
            }

            $this->command->info('✅ Branch-specific news seeded for ' . min(3, $branches->count()) . ' branches.');
        }

        // ============================================================
        // 4. Additional Testing News (for Infinite Scroll Testing)
        // ============================================================
        $testingCategories = ['Berita', 'Tips & Kecantikan', 'Promo', 'Update Layanan', 'Event'];
        $testingImages = [
            'https://images.unsplash.com/photo-1540555700478-4be289fbecee?w=800&q=80',
            'https://images.unsplash.com/photo-1512290923902-8a9f81dc2069?w=800&q=80',
            'https://images.unsplash.com/photo-1591343395582-99bf4de990dc?w=800&q=80',
            'https://images.unsplash.com/photo-1516238840914-94dfc0c172e5?w=800&q=80',
            'https://images.unsplash.com/photo-1515377905703-c4788e51af15?w=800&q=80',
            'https://images.unsplash.com/photo-1596178065887-1198b6148b2b?w=800&q=80',
            'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=800&q=80',
            'https://images.unsplash.com/photo-1507652313519-d4c9174996dd?w=800&q=80',
        ];

        $testingTitles = [
            'Rahasia Kecantikan Alami dari Dalam',
            'Cara Memilih Masker Wajah yang Tepat',
            'Mengapa Anda Perlu Detox Tubuh Secara Rutin',
            'Inovasi Terbaru Perawatan Rambut di Zafaran',
            'Promo Hemat Akhir Pekan Menanti Anda',
            'Testimoni Pelanggan Setia Zafaran Spa',
            'Mengenal Teknik Accupressure untuk Relaksasi',
            'Pentingnya Me-Time di Tengah Kesibukan',
            'Tips Menghilangkan Lelah Setelah Bekerja',
            'Workshop Yoga & Meditasi Gratis',
            'Eksfoliasi: Kunci Kulit Halus dan Lembut',
            'Manfaat Mandi Susu untuk Peremajaan Kulit',
            'Tren Warna Kuku Musim Ini',
            'Cara Alami Mengatasi Mata Panda',
            'Perawatan Pasca Melahirkan yang Aman',
            'Diskon Spesial Hari Kartini',
            'Peluncuran Paket Hemat Group Treatment',
            'Tips Menggunakan Essential Oil di Rumah',
            'Zafaran Mengadakan Bakti Sosial',
            'Sejarah Singkat Spa dan Tradisi Kecantikan',
        ];

        foreach ($testingTitles as $index => $title) {
            $slug = Str::slug($title . '-' . ($index + 1));
            News::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'category' => $testingCategories[array_rand($testingCategories)],
                    'status' => 'published',
                    'content' => "Ini adalah artikel testing ke-" . ($index + 1) . " untuk keperluan pengujian fitur infinite scroll pada aplikasi Zafaran.\n\n" .
                        "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.\n\n" .
                        "Kami terus berkomitmen untuk memberikan informasi yang bermanfaat bagi Anda.",
                    'image_url' => $testingImages[$index % count($testingImages)],
                    'author_id' => $author->id,
                    'is_global' => true,
                    'branch_id' => null,
                    'published_at' => Carbon::now()->subDays($index + 40), // Older than initial ones to test sorting
                    'views' => rand(50, 500),
                ]
            );
        }

        $this->command->info('✅ 20 additional testing news articles seeded.');

        $this->command->info('🎉 NewsSeeder completed! Total: ' . News::count() . ' articles.');
    }
}
