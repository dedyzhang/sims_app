<?php

namespace App\Support;

/**
 * Referensi Capaian Pembelajaran (CP) standar Kurikulum Merdeka Fase D (SMP Kelas 7-9).
 * Sesuai Keputusan Kepala BSKAP Kemendikbudristek No. 032/H/KR/2024.
 * Membantu guru mengisi Elemen & Capaian Pembelajaran secara otomatis
 * tanpa perlu mengetik atau menyalin manual dokumen kurikulum yang panjang.
 */
final class StandardCurriculumCp
{
    /**
     * @return array<string, array{
     *     nama: string,
     *     kode: string,
     *     fase: string,
     *     elemen_utama: string,
     *     cp_utama: string,
     *     jp_default: int,
     *     elemen_list: list<array{nama: string, cp: string}>
     * }>
     */
    public static function all(): array
    {
        return [
            'ips' => [
                'nama' => 'Ilmu Pengetahuan Sosial',
                'kode' => 'IPS',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Pemahaman Konsep',
                'cp_utama' => 'Pada akhir Fase D, murid memiliki kemampuan menjelaskan keberagaman kondisi geografis Indonesia, konektivitas antarruang terhadap upaya pemanfaatan dan pelestarian potensi sumber daya alam, faktor aktivitas manusia terhadap perubahan iklim dan potensi bencana alam; memprediksi dampak perubahan iklim terhadap kehidupan ekonomi, sosial, budaya masyarakat serta merefleksikan pola adaptasi terhadap perubahan iklim dan upaya mitigasi bencana untuk menunjang sustainable development goals (SDGs) dalam konteks lokal, regional, dan global; mengidentifikasi upaya masyarakat dalam memenuhi kebutuhannya melalui kegiatan ekonomi, harga, pasar, lembaga keuangan, perdagangan internasional; menelaah peran masyarakat dan negara dalam mendorong pertumbuhan ekonomi di era digital, serta potensi Indonesia menjadi negara maju; mengelaborasi proses interaksi sosial, lembaga sosial, dinamika sosial dan perubahan sistem sosial budaya dalam masyarakat yang majemuk untuk mewujudkan integrasi bangsa dengan prinsip kebhinekaan; menjelaskan konsep dasar ilmu sejarah yaitu manusia, ruang, waktu, kronologi, perubahan; menganalisis keterhubungan antara masa lampau, masa kini, dan masa yang akan datang ketika mempelajari sejarah lokal dan toponimi wilayah serta berbagai peristiwa atau kejadian penting dalam lingkup lokal, nasional dan global terkait asal-usul nenek moyang bangsa Indonesia dan jalur rempah nusantara.',
                'jp_default' => 27,
                'elemen_list' => [
                    [
                        'nama' => 'Pemahaman Konsep',
                        'cp' => 'Pada akhir Fase D, murid memiliki kemampuan menjelaskan keberagaman kondisi geografis Indonesia, konektivitas antarruang terhadap upaya pemanfaatan dan pelestarian potensi sumber daya alam, faktor aktivitas manusia terhadap perubahan iklim dan potensi bencana alam; memprediksi dampak perubahan iklim terhadap kehidupan ekonomi, sosial, budaya masyarakat serta merefleksikan pola adaptasi terhadap perubahan iklim dan upaya mitigasi bencana untuk menunjang sustainable development goals (SDGs) dalam konteks lokal, regional, dan global; mengidentifikasi upaya masyarakat dalam memenuhi kebutuhannya melalui kegiatan ekonomi, harga, pasar, lembaga keuangan, perdagangan internasional; menelaah peran masyarakat dan negara dalam mendorong pertumbuhan ekonomi di era digital, serta potensi Indonesia menjadi negara maju; mengelaborasi proses interaksi sosial, lembaga sosial, dinamika sosial dan perubahan sistem sosial budaya dalam masyarakat yang majemuk untuk mewujudkan integrasi bangsa dengan prinsip kebhinekaan; menjelaskan konsep dasar ilmu sejarah yaitu manusia, ruang, waktu, kronologi, perubahan; menganalisis keterhubungan antara masa lampau, masa kini, dan masa yang akan datang ketika mempelajari sejarah lokal dan toponimi wilayah serta berbagai peristiwa atau kejadian penting dalam lingkup lokal, nasional dan global terkait asal-usul nenek moyang bangsa Indonesia dan jalur rempah nusantara.',
                    ],
                    [
                        'nama' => 'Keterampilan Proses',
                        'cp' => 'Pada akhir Fase D, murid mampu menerapkan pemahaman konsep melalui pendekatan keterampilan proses dalam mengamati fenomena sosial, merumuskan pertanyaan, mengumpulkan dan memverifikasi data informasi dari berbagai sumber sejarah dan geografi, mengorganisasikan dan menganalisis data, menarik simpulan, menyajikan laporan pemecahan masalah serta merefleksikan proses belajar dan merencanakan aksi mitigasi/solusi sosial berkelanjutan.',
                    ],
                ],
            ],
            'ipa' => [
                'nama' => 'Ilmu Pengetahuan Alam',
                'kode' => 'IPA',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Pemahaman IPA',
                'cp_utama' => 'Pada akhir Fase D, murid mampu memahami sistem pengukuran, klasifikasi makhluk hidup dan benda, sifat dan wujud zat, suhu, kalor, energi dan perubahannya, gerak dan gaya, tekanan, struktur dan fungsi tubuh makhluk hidup, sistem organisasi kehidupan, hereditas dan bioteknologi, ekologi dan keanekaragaman hayati Indonesia, keterkaitan struktur bumi dan antariksa serta mitigasi bencana, pemanfaatan zat aditif dan adiktif, listrik, magnet, gelombang dan cahaya dalam kehidupan sehari-hari.',
                'jp_default' => 30,
                'elemen_list' => [
                    [
                        'nama' => 'Pemahaman IPA',
                        'cp' => 'Pada akhir Fase D, murid mampu memahami sistem pengukuran, klasifikasi makhluk hidup dan benda, sifat dan wujud zat, suhu, kalor, energi dan perubahannya, gerak dan gaya, tekanan, struktur dan fungsi tubuh makhluk hidup, sistem organisasi kehidupan, hereditas dan bioteknologi, ekologi dan keanekaragaman hayati Indonesia, keterkaitan struktur bumi dan antariksa serta mitigasi bencana, pemanfaatan zat aditif dan adiktif, listrik, magnet, gelombang dan cahaya dalam kehidupan sehari-hari.',
                    ],
                    [
                        'nama' => 'Keterampilan Proses',
                        'cp' => 'Murid mampu mengamati gejala alam, merumuskan pertanyaan dan hipotesis, merancang dan melakukan penyelidikan mandiri/kelompok, memproses dan menganalisis data ilmiah, mengevaluasi bukti, serta mengomunikasikan hasil penyelidikan sains secara lisan dan tertulis.',
                    ],
                ],
            ],
            'bin' => [
                'nama' => 'Bahasa Indonesia',
                'kode' => 'BIN',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Menyimak, Membaca-Memirsa, Berbicara, Menulis',
                'cp_utama' => 'Pada akhir Fase D, murid memiliki kemampuan berbahasa untuk berkomunikasi dan bernalar sesuai dengan tujuan, konteks sosial, dan akademis. Murid mampu memahami, mengolah, dan menginterpretasi informasi paparan tentang topik yang beragam dan karya sastra. Murid mampu berpartisipasi aktif dalam diskusi, mempresentasikan, dan menanggapi informasi nonfiksi dan fiksi yang dipaparkan; murid menulis berbagai teks untuk menyampaikan pengamatan dan pengalamannya dengan lebih terstruktur, serta menuliskan tanggapannya terhadap paparan dan bacaan menggunakan pengalaman dan pengetahuannya.',
                'jp_default' => 30,
                'elemen_list' => [
                    [
                        'nama' => 'Membaca dan Memirsa',
                        'cp' => 'Murid mampu memahami informasi tersurat dan tersirat, menginterpretasi pesan teks deskripsi, narasi (cerpen, fabel, puisi), prosedur, laporan hasil observasi, berita, dan teks tanggapan, serta mengevaluasi efektivitas struktur dan kaidah kebahasaan teks.',
                    ],
                    [
                        'nama' => 'Menulis',
                        'cp' => 'Murid mampu merencanakan, menulis, merevisi, dan memublikasikan teks deskripsi, prosedur, eksposisi, ulasan, cerpen, dan surat resmi/pribadi secara logis, runtut, dan kreatif dengan kaidah ejaan Bahasa Indonesia yang benar.',
                    ],
                    [
                        'nama' => 'Menyimak',
                        'cp' => 'Murid mampu menganalisis dan mengevaluasi informasi berupa gagasan, pikiran, arahan, atau pesan yang akurat dari teks lisan, audiovisual, atau drama.',
                    ],
                    [
                        'nama' => 'Berbicara dan Mempresentasikan',
                        'cp' => 'Murid mampu menyampaikan gagasan, tanggapan, dan sanggahan secara lisan dengan santun, artikulatif, intonasi yang tepat, dan percaya diri dalam forum diskusi maupun presentasi multimodal.',
                    ],
                ],
            ],
            'mtk' => [
                'nama' => 'Matematika',
                'kode' => 'MTK',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Bilangan, Aljabar, Geometri, Analisis Data & Peluang',
                'cp_utama' => 'Pada akhir Fase D, murid dapat menyelesaikan masalah kontekstual menggunakan konsep bilangan bulat, pecahan, rasio, proporsi, bilangan berpangkat dan bentuk akar; mengenali pola aljabar, persamaan dan pertidaksamaan linear, relasi dan fungsi; mengukur dan menghitung luas bangun datar, luas permukaan dan volume bangun ruang sisi datar dan sisi lengkung; menerapkan teorema Pythagoras dan transformasi geometri; serta mengumpulkan, menyajikan, menganalisis data statistik dan menentukan peluang suatu kejadian.',
                'jp_default' => 30,
                'elemen_list' => [
                    [
                        'nama' => 'Bilangan',
                        'cp' => 'Murid dapat membaca, menulis, membandingkan, dan mengoperasikan bilangan bulat, pecahan, rasio, proporsi, bilangan berpangkat bulat, dan bentuk akar untuk menyelesaikan masalah numerik kontekstual.',
                    ],
                    [
                        'nama' => 'Aljabar',
                        'cp' => 'Murid dapat memodelkan fenomena matematis ke dalam bentuk aljabar, menyelesaikan persamaan dan pertidaksamaan linear satu dan dua variabel, serta memahami konsep relasi dan fungsi linear.',
                    ],
                    [
                        'nama' => 'Pengukuran dan Geometri',
                        'cp' => 'Murid dapat membuktikan dan menerapkan teorema Pythagoras, menghitung luas dan volume prisma, tabung, limas, kerucut, bola, serta menerapkan prinsip transformasi geometri (refleksi, translasi, rotasi, dilatasi).',
                    ],
                    [
                        'nama' => 'Analisis Data dan Peluang',
                        'cp' => 'Murid dapat mengorganisasikan data investigatif ke dalam tabel dan diagram frekuensi, menghitung ukuran pemusatan (mean, median, modus) dan penyebaran, serta menghitung frekuensi relatif dan peluang empirik-teoretik.',
                    ],
                ],
            ],
            'ing' => [
                'nama' => 'Bahasa Inggris',
                'kode' => 'ING',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Listening-Speaking, Reading-Viewing, Writing-Presenting',
                'cp_utama' => 'By the end of Phase D, students use English to interact and exchange information, ideas, and opinions in familiar and structured contexts. They comprehend main ideas and specific details in diverse multimodal texts (descriptive, recount, narrative, procedure, factual report) and produce coherent spoken and written texts using appropriate vocabulary, grammar, and mechanics.',
                'jp_default' => 24,
                'elemen_list' => [
                    [
                        'nama' => 'Listening and Speaking',
                        'cp' => 'Students use English to interact in daily conversations, express opinions, ask for clarification, and present simple discussions using accurate pronunciation and communicative strategies.',
                    ],
                    [
                        'nama' => 'Reading and Viewing',
                        'cp' => 'Students independently read and comprehend various text types (descriptive, narrative, recount, procedure, report) to identify purpose, main ideas, and infer implicit information.',
                    ],
                    [
                        'nama' => 'Writing and Presenting',
                        'cp' => 'Students plan, draft, and produce well-structured texts in English using appropriate cohesive devices, sentence varieties, and punctuation for specific communicative purposes.',
                    ],
                ],
            ],
            'pp' => [
                'nama' => 'Pendidikan Pancasila',
                'kode' => 'PP',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Pancasila & UUD NRI 1945',
                'cp_utama' => 'Pada akhir Fase D, murid memahami kedudukan Pancasila sebagai dasar negara, ideologi bangsa, dan pandangan hidup; mengkaji sejarah perumusan dan pengesahan UUD NRI 1945, norma hukum, hak dan kewajiban warga negara; mengapresiasi keberagaman suku, agama, ras, dan antargolongan dalam bingkai Bhinneka Tunggal Ika; serta menunjukkan komitmen menjaga keutuhan Negara Kesatuan Republik Indonesia (NKRI).',
                'jp_default' => 20,
                'elemen_list' => [
                    [
                        'nama' => 'Pancasila',
                        'cp' => 'Murid menganalisis nilai-nilai Pancasila sebagai dasar negara, ideologi bangsa, dan pandangan hidup serta membiasakan penerapannya dalam kehidupan bermasyarakat.',
                    ],
                    [
                        'nama' => 'Undang-Undang Dasar Negara Republik Indonesia Tahun 1945',
                        'cp' => 'Murid memahami tata urutan peraturan perundang-undangan, norma sosial, hukum tertulis, hak dan kewajiban asasi warga negara.',
                    ],
                    [
                        'nama' => 'Bhinneka Tunggal Ika & NKRI',
                        'cp' => 'Murid menumbuhkan sikap toleransi dan gotong royong di tengah kemajemukan serta berperan aktif mempertahankan kedaulatan dan keutuhan wilayah NKRI.',
                    ],
                ],
            ],
            'inf' => [
                'nama' => 'Informatika',
                'kode' => 'INF',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Berpikir Komputasional, TIK, SK, AP, DSI',
                'cp_utama' => 'Pada akhir Fase D, murid mampu menerapkan berpikir komputasional dalam menyelesaikan persoalan terstruktur; memanfaatkan aplikasi perkantoran dan komunikasi daring secara produktif dan aman; memahami prinsip kerja komponen komputer, jaringan lokal dan internet; merancang algoritma dan program visual/tekstual sederhana; menganalisis data informasi digital; serta bersikap kritis dan etis terhadap dampak sosial teknologi informatika.',
                'jp_default' => 20,
                'elemen_list' => [
                    [
                        'nama' => 'Berpikir Komputasional & Algoritma Pemrograman',
                        'cp' => 'Murid mampu menerapkan konsep dekomposisi, abstraksi, pengenalan pola, dan perancangan algoritma serta mengimplementasikannya dalam bahasa pemrograman visual (blok) atau Python sederhana.',
                    ],
                    [
                        'nama' => 'Teknologi Informasi, Komunikasi & Sistem Komputer',
                        'cp' => 'Murid mengoperasikan perangkat lunak pengolah kata, lembar sebar, presentasi terintegrasi, memahami jaringan komputer, serta menjaga keamanan privasi data.',
                    ],
                ],
            ],
            'sb' => [
                'nama' => 'Seni Budaya',
                'kode' => 'SB',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Mengalami, Menciptakan, Merefleksikan',
                'cp_utama' => 'Pada akhir Fase D, murid mampu mengeksplorasi dan mengaplikasikan unsur-unsur dasar, media, dan teknik seni (rupa, musik, tari, atau teater); menghasilkan karya seni kreatif yang mengekspresikan gagasan dan respons terhadap lingkungan sekitar; serta mengapresiasi dan merefleksikan nilai estetis dan budaya warisan lokal nusantara.',
                'jp_default' => 20,
                'elemen_list' => [
                    [
                        'nama' => 'Mengalami dan Menciptakan',
                        'cp' => 'Murid mampu memilih teknik, alat, dan bahan seni yang tepat untuk mewujudkan karya seni secara orisinal, ekspresif, dan bertahap.',
                    ],
                    [
                        'nama' => 'Merefleksikan dan Berdampak',
                        'cp' => 'Murid mengapresiasi karya seni sendiri dan orang lain serta menghubungkan makna seni dengan konteks sosial budaya kehidupan sehari-hari.',
                    ],
                ],
            ],
            'pjok' => [
                'nama' => 'Pendidikan Jasmani, Olahraga dan Kesehatan',
                'kode' => 'PJOK',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Keterampilan Gerak & Pola Hidup Sehat',
                'cp_utama' => 'Pada akhir Fase D, murid mempraktikkan keterampilan gerak spesifik dalam berbagai cabang olahraga permainan, bela diri, atletik, dan aktivitas senam; memahami konsep kebugaran jasmani dan gizi seimbang; mencegah penyalahgunaan obat terlarang dan penyakit; serta mengembangkan nilai karakter sportivitas, disiplin, kerja sama, dan tanggung jawab.',
                'jp_default' => 20,
                'elemen_list' => [
                    [
                        'nama' => 'Keterampilan dan Pengetahuan Gerak',
                        'cp' => 'Murid menerapkan variasi pola gerak spesifik permainan bola besar, bola kecil, atletik, bela diri, senam lantai, dan aktivitas gerak berirama.',
                    ],
                    [
                        'nama' => 'Pemanfaatan Gerak dan Perilaku Hidup Sehat',
                        'cp' => 'Murid memahami pengukuran derajat kebugaran jasmani, pola makan sehat, pemeliharaan kesehatan reproduksi, serta pembentukan karakter positif.',
                    ],
                ],
            ],
            'agm' => [
                'nama' => 'Pendidikan Agama dan Budi Pekerti',
                'kode' => 'AGM',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Sejarah, Kitab Suci, Ajaran Moralitas (Sila & Kebijaksanaan)',
                'cp_utama' => 'Pada akhir Fase D, murid memahami nilai-nilai spiritual, sejarah agama, ajaran moral, toleransi antarpemeluk agama, serta menginternalisasi budi pekerti luhur dalam pikiran, ucapan, dan perbuatan sehari-hari di lingkungan sekolah, keluarga, dan masyarakat.',
                'jp_default' => 20,
                'elemen_list' => [
                    [
                        'nama' => 'Pendidikan Agama Buddha',
                        'cp' => 'Murid memahami riwayat Buddha Gotama, ajaran Empat Kebenaran Mulia, Hukum Kamma, Tilakkhana, Paticcasamuppada, praktik Sila, Bhavana (meditasi ketenangan batin), serta mengamalkan cinta kasih (Metta) dan kasih sayang (Karuna) dalam kehidupan majemuk.',
                    ],
                    [
                        'nama' => 'Pendidikan Agama Islam',
                        'cp' => 'Murid memahami Al-Qur\'an dan Hadis, rukun iman, rukun Islam, fikih ibadah, sejarah peradaban Islam, serta akhlak terpuji terhadap sesama makhluk ciptaan Allah SWT.',
                    ],
                    [
                        'nama' => 'Pendidikan Agama Kristen / Katolik',
                        'cp' => 'Murid memahami karya keselamatan Allah melalui Yesus Kristus, nilai-nilai Kerajaan Allah, ajaran cinta kasih, doa, sakramen, dan keteladanan iman Kristiani.',
                    ],
                ],
            ],
            'bdm' => [
                'nama' => 'Muatan Lokal Budaya Melayu',
                'kode' => 'BDM',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Adat Istiadat, Sastra & Kearifan Lokal Melayu',
                'cp_utama' => 'Pada akhir Fase D, murid memahami sejarah kejayaan tamadun Melayu Riau-Lingga, toponimi wilayah kepulauan, adat resam, etika pergaulan tunjuk ajar Melayu (Gurindam Dua Belas, pantun, syair), busana tradisional, kuliner khas daerah, serta seni musik dan tari tradisi Melayu Kepulauan Riau.',
                'jp_default' => 20,
                'elemen_list' => [
                    [
                        'nama' => 'Tunjuk Ajar & Sastra Melayu',
                        'cp' => 'Murid mampu mendalami nilai filosofis Gurindam Dua Belas karya Raja Ali Haji, berbalas pantun adat, dan mengekspresikan sastra tutur Melayu.',
                    ],
                    [
                        'nama' => 'Adat Resam & Warisan Budaya',
                        'cp' => 'Murid mengidentifikasi tata krama pergaulan Melayu, upacara adat tepuk tepung tawar, ragam busana teluk belanga dan kebaya labuh, serta kuliner khas Kepulauan Riau.',
                    ],
                ],
            ],
            'mdr' => [
                'nama' => 'Muatan Lokal Bahasa Mandarin',
                'kode' => 'MDR',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Pinyin, Kosakata HSK 1, Percakapan & Guratan Hanzi',
                'cp_utama' => 'Pada akhir Fase D, murid mampu melafalkan sistem fonetik Hanyu Pinyin dan empat nada secara tepat; menguasai kosakata dasar setara HSK 1-2; menyimak dan melakukan dialog sederhana mengenai perkenalan diri, keluarga, waktu, aktivitas sekolah; serta membaca dan menulis karakter Hanzi dasar dengan urutan guratan yang benar.',
                'jp_default' => 27,
                'elemen_list' => [
                    [
                        'nama' => 'Menyimak dan Berbicara (听与说)',
                        'cp' => 'Murid mampu merespons instruksi lisan dalam bahasa Mandarin, mengucapkan salam, memperkenalkan diri dan hobi, serta melakukan dialog tanya-jawab sederhana sehari-hari.',
                    ],
                    [
                        'nama' => 'Membaca dan Menulis (读与写)',
                        'cp' => 'Murid mampu mengenali radikal Hanzi dasar, membaca teks pendek berpinyin, serta menulis karakter Hanzi dasar sesuai kaidah urutan guratan (笔顺).',
                    ],
                ],
            ],
            'kka' => [
                'nama' => 'Koding dan Kecerdasan Artificial',
                'kode' => 'KKA',
                'fase' => 'D/VII-IX',
                'elemen_utama' => 'Logika Pemrograman & Penerapan AI Cerdas',
                'cp_utama' => 'Pada akhir Fase D, murid memahami logika komputasional tingkat lanjut, pemrograman blok dan pengenalan sintaks teks, prinsip dasar pembelajaran mesin (machine learning), pemanfaatan alat bantu AI generatif secara produktif dan bertanggung jawab, serta etika digital pemanfaatan kecerdasan buatan.',
                'jp_default' => 20,
                'elemen_list' => [
                    [
                        'nama' => 'Logika Koding dan Pemrograman',
                        'cp' => 'Murid mampu merancang struktur algoritma dengan percabangan dan perulangan bersarang serta membuat proyek animasi/game interaktif sederhana.',
                    ],
                    [
                        'nama' => 'Kecerdasan Artifisial & Etika Digital',
                        'cp' => 'Murid memahami cara model AI mengenali pola data visual dan teks, merancang prompt efektif (prompt engineering), dan bersikap etis terhadap hak cipta digital.',
                    ],
                ],
            ],
        ];
    }

    /**
     * Cari referensi CP berdasarkan nama atau kode mapel.
     *
     * @return array{
     *     nama: string,
     *     kode: string,
     *     fase: string,
     *     elemen_utama: string,
     *     cp_utama: string,
     *     jp_default: int,
     *     elemen_list: list<array{nama: string, cp: string}>
     * }|null
     */
    public static function find(string $query): ?array
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        if ($q === '') {
            return null;
        }

        $all = self::all();

        // 1. Cocokkan key langsung
        if (isset($all[$q])) {
            return $all[$q];
        }

        // 2. Cocokkan kode persis
        foreach ($all as $item) {
            if (mb_strtolower($item['kode'], 'UTF-8') === $q) {
                return $item;
            }
        }

        // 3. Cocokkan substring pada nama
        foreach ($all as $item) {
            $nama = mb_strtolower($item['nama'], 'UTF-8');
            if (str_contains($nama, $q) || str_contains($q, $nama)) {
                return $item;
            }
        }

        // 4. Heuristik kata kunci
        $keywords = [
            'sosial' => 'ips',
            'alam' => 'ipa',
            'indo' => 'bin',
            'mate' => 'mtk',
            'ingg' => 'ing',
            'panca' => 'pp',
            'infor' => 'inf',
            'seni' => 'sb',
            'jasmani' => 'pjok',
            'olahraga' => 'pjok',
            'buddha' => 'agm',
            'agama' => 'agm',
            'melayu' => 'bdm',
            'mandarin' => 'mdr',
            'koding' => 'kka',
            'ai' => 'kka',
        ];

        foreach ($keywords as $kw => $key) {
            if (str_contains($q, $kw) && isset($all[$key])) {
                return $all[$key];
            }
        }

        return null;
    }
}
