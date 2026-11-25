<?php

namespace App\Services;

use App\Enums\JenisMutasi;
use App\Enums\MetodePembayaran;
use App\Enums\StatusAktif;
use App\Enums\StatusAlokasi;
use App\Enums\StatusPembayaran;
use App\Enums\StatusPembayaranCatatan;
use App\Enums\StatusPenerimaan;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenyaluran;
use App\Enums\StatusTagihan;
use App\Exceptions\BisnisException;
use App\Models\Anggota;
use App\Models\Kapal;
use App\Models\PenerimaanBbm;
use App\Models\PengajuanPembelian;
use App\Models\PenyaluranBbm;
use App\Models\StokBbm;
use App\Models\SuratRekomendasi;
use App\Models\User;
use App\Support\Dec;
use App\Support\Format;
use App\Support\LencanaCache;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Spatie\Permission\Models\Role;

class LaporanPenjualanImportService
{
    private const BULAN_ID = [
        'jan' => 'Jan', 'januari' => 'Jan',
        'feb' => 'Feb', 'februari' => 'Feb',
        'mar' => 'Mar', 'maret' => 'Mar',
        'apr' => 'Apr', 'april' => 'Apr',
        'mei' => 'May', 'may' => 'May',
        'jun' => 'Jun', 'juni' => 'Jun',
        'jul' => 'Jul', 'juli' => 'Jul',
        'agu' => 'Aug', 'ags' => 'Aug', 'agustus' => 'Aug', 'aug' => 'Aug',
        'sep' => 'Sep', 'sept' => 'Sep', 'september' => 'Sep',
        'okt' => 'Oct', 'oktober' => 'Oct', 'oct' => 'Oct',
        'nov' => 'Nov', 'november' => 'Nov',
        'des' => 'Dec', 'desember' => 'Dec', 'dec' => 'Dec',
    ];

    private const BULAN_ANGKA = [
        'jan' => 1, 'januari' => 1, 'january' => 1,
        'feb' => 2, 'februari' => 2, 'february' => 2,
        'mar' => 3, 'maret' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4,
        'mei' => 5, 'may' => 5,
        'jun' => 6, 'juni' => 6, 'june' => 6,
        'jul' => 7, 'juli' => 7, 'july' => 7,
        'agu' => 8, 'ags' => 8, 'agus' => 8, 'agustus' => 8, 'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'okt' => 10, 'oktober' => 10, 'oct' => 10, 'october' => 10,
        'nop' => 11, 'nov' => 11, 'november' => 11,
        'des' => 12, 'desember' => 12, 'dec' => 12, 'december' => 12,
    ];

    private const PREFIX_NOMOR = [
        'pengajuan' => 'PGJ',
        'penerimaan' => 'TRM',
        'penyaluran' => 'SLR',
        'tagihan' => 'TGH',
        'pembayaran' => 'BYR',
    ];

    public function __construct(
        private HargaService $harga,
        private PengaturanService $pengaturan,
    ) {
    }

    /**
     * Mengimpor data Laporan Penjualan (format Lampiran 6 .xlsx / .xls / .csv) secara massal.
     *
     * @return array{
     *   penjualan:int,
     *   liter_penjualan:string,
     *   penerimaan:int,
     *   liter_penerimaan:string,
     *   duplikat_dilewati:int,
     *   anggota_baru:int,
     *   kapal_baru:int,
     *   surat_baru:int,
     *   gagal:array<int, array{sheet:string, baris:int, alasan:string}>
     * }
     */
    public function impor(
        string $fullPath,
        User $oleh,
        bool $imporPenerimaan = true,
        bool $buatMasterOtomatis = true,
        bool $lewatiDuplikat = true,
    ): array {
        Otorisasi::pastikan($oleh, 'penyaluran.manage');

        if (! is_file($fullPath)) {
            throw new BisnisException('File Excel/CSV tidak ditemukan.');
        }

        @set_time_limit(300);

        $parsed = $this->bacaFile($fullPath);
        $penerimaanMap = $parsed['penerimaan'];
        $penjualanList = $parsed['penjualan'];
        $gagal = $parsed['gagal'];

        if ($penjualanList === [] && $penerimaanMap === []) {
            throw new BisnisException('Tidak ada baris data penjualan, penerimaan, atau cek rekom yang terbaca. Pastikan file mengikuti format Laporan Penjualan (Lampiran 6), Laporan Stock Produk Solar, atau Cek Rekom / Rekap Penjualan.');
        }

        // Bila file hanya berisi data Laporan Stock Produk / Penerimaan (tanpa baris penjualan),
        // selalu aktifkan impor penerimaan agar update stok berjalan sesuai maksud unggahan.
        $efektifImporPenerimaan = $imporPenerimaan || ($penjualanList === [] && $penerimaanMap !== []);

        usort($penjualanList, fn (array $a, array $b) => [$a['tanggal'], $a['urutan']] <=> [$b['tanggal'], $b['urutan']]);
        ksort($penerimaanMap);

        return DB::transaction(function () use ($penerimaanMap, $penjualanList, $gagal, $oleh, $efektifImporPenerimaan, $buatMasterOtomatis, $lewatiDuplikat) {
            DB::table('stok_bbm')->insertOrIgnore([
                'id' => 1, 'stok_fisik' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $stokRow = StokBbm::query()->lockForUpdate()->findOrFail(1);
            $runningSaldo = Dec::n($stokRow->stok_fisik);

            // 1. Pre-load cache master data (tanpa N+1)
            $anggotaByNama = [];
            foreach (Anggota::withTrashed()->get(['id', 'nama_lengkap', 'kode_anggota']) as $a) {
                $k = self::normNama($a->nama_lengkap);
                if ($k !== '' && ! isset($anggotaByNama[$k])) {
                    $anggotaByNama[$k] = (int) $a->id;
                }
            }
            $urutAnggota = (int) (Anggota::withTrashed()->max('id') ?? 0);

            $kapalByKey = [];
            $kapalGt = [];
            $kapalPk = [];
            $kapalByAnggota = [];
            foreach (Kapal::withTrashed()->get(['id', 'anggota_id', 'nama_kapal', 'ukuran_gt', 'daya_pk']) as $k) {
                $nk = self::normNama($k->nama_kapal);
                $kapalByKey[$k->anggota_id . '|' . $nk] = (int) $k->id;
                $kapalGt[(int) $k->id] = $k->ukuran_gt;
                $kapalPk[(int) $k->id] = $k->daya_pk;
                $kapalByAnggota[(int) $k->anggota_id][] = (int) $k->id;
            }
            $urutKapal = (int) (Kapal::withTrashed()->max('id') ?? 0);

            $suratByKapalBase = [];
            $suratByAnggotaBase = [];
            $suratNomorTerpakai = [];
            $suratBerlaku = [];
            foreach (SuratRekomendasi::withTrashed()->orderBy('id')->get(['id', 'anggota_id', 'kapal_id', 'nomor_surat', 'berlaku_mulai', 'berlaku_sampai', 'kuota_bulanan_liter']) as $s) {
                $rawUpper = mb_strtoupper(trim((string) $s->nomor_surat));
                $baseUpper = self::baseNomorSurat($s->nomor_surat);
                $kuotaNorm = Dec::n($s->kuota_bulanan_liter);
                $suratNomorTerpakai[$rawUpper] = true;
                $suratByKapalBase[$s->kapal_id . '|' . $baseUpper][] = [
                    'id' => (int) $s->id,
                    'kuota' => $kuotaNorm,
                ];
                $suratByAnggotaBase[$s->anggota_id . '|' . $baseUpper][] = [
                    'id' => (int) $s->id,
                    'kapal_id' => (int) $s->kapal_id,
                ];
                $suratBerlaku[(int) $s->id] = [
                    'mulai' => $s->berlaku_mulai->toDateString(),
                    'sampai' => $s->berlaku_sampai->toDateString(),
                    'kuota' => $kuotaNorm,
                ];
            }

            $existingPenerimaanDates = PenerimaanBbm::query()
                ->where('status', StatusPenerimaan::Terkonfirmasi->value)
                ->pluck('tanggal_penerimaan')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->flip()
                ->all();

            // Multiset transaksi yang sudah ada (tanggal|surat_id|liter) agar import ulang 100% idempoten
            $existingSalesCount = [];
            if ($lewatiDuplikat && $penjualanList !== []) {
                $minDate = $penjualanList[0]['tanggal'] . ' 00:00:00';
                $maxDate = end($penjualanList)['tanggal'] . ' 23:59:59';
                $rowsAda = DB::table('penyaluran_bbm as slr')
                    ->join('pengajuan_pembelian as pgj', 'pgj.id', '=', 'slr.pengajuan_id')
                    ->whereBetween('slr.tanggal_penyaluran', [$minDate, $maxDate])
                    ->get(['slr.tanggal_penyaluran', 'pgj.surat_rekomendasi_id', 'slr.liter_aktual']);

                foreach ($rowsAda as $ra) {
                    $fp = Carbon::parse($ra->tanggal_penyaluran)->toDateString() . '|' . (int) $ra->surat_rekomendasi_id . '|' . Dec::n($ra->liter_aktual);
                    $existingSalesCount[$fp] = ($existingSalesCount[$fp] ?? 0) + 1;
                }
            }

            // 2. Siapkan counter penomoran per (jenis, YYYYMM)
            $counters = [];
            $countersDirty = [];
            $nextNomor = function (string $jenis, string $ym) use (&$counters, &$countersDirty): string {
                $key = $jenis . '|' . $ym;
                if (! isset($counters[$key])) {
                    DB::table('penomoran')->insertOrIgnore([
                        'jenis' => $jenis, 'periode' => $ym, 'nomor_terakhir' => 0,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $val = DB::table('penomoran')->where('jenis', $jenis)->where('periode', $ym)->lockForUpdate()->value('nomor_terakhir');
                    $counters[$key] = (int) $val;
                }
                $counters[$key]++;
                $countersDirty[$key] = $counters[$key];

                return sprintf('%s-%s-%04d', self::PREFIX_NOMOR[$jenis], $ym, $counters[$key]);
            };

            // 3. Kumpulkan penerimaan baru yang belum ada di database
            $penerimaanBaru = [];
            $penerimaanDilewati = 0;
            $totalLiterPenerimaanBaru = '0.00';
            if ($efektifImporPenerimaan) {
                foreach ($penerimaanMap as $tgl => $liter) {
                    if (isset($existingPenerimaanDates[$tgl])) {
                        $penerimaanDilewati++;
                        continue;
                    }
                    $l = Dec::n($liter);
                    if (Dec::gt($l, 0)) {
                        $penerimaanBaru[$tgl] = $l;
                        $totalLiterPenerimaanBaru = Dec::add($totalLiterPenerimaanBaru, $l);
                    }
                }
            }

            // 4. Resolusi master & filter duplikat terlebih dahulu (sekalian hitung total liter penjualan baru)
            $roleAnggotaId = Role::query()->where('name', 'anggota')->value('id');
            $userMorphClass = (new User())->getMorphClass();
            $hashedDefaultPw = null;
            $existingUsernames = null;

            $anggotaBaru = 0;
            $kapalBaru = 0;
            $suratBaru = 0;
            $duplikatDilewati = 0;
            $siapImpor = [];
            $kuotaNol = []; // "surat_id|periode" => vol_rekom untuk baris Cek Rekom berpenjualan 0 L
            $totalLiterPenjualanBaru = '0.00';
            $nowTs = now();

            foreach ($penjualanList as $item) {
                $namaPemilik = $item['nama_pemilik'];
                $namaKapalAsli = $item['nama_kapal'];
                $rekomRaw = $item['rekomendasi'];
                $volRekom = $item['vol_rekom'];
                $gt = $item['gt'];
                $pk = $item['pk'] ?? null;
                $liter = Dec::n($item['penjualan']);
                $tgl = $item['tanggal'];

                $namaPemilikEfektif = $namaPemilik !== '' ? $namaPemilik : $namaKapalAsli;
                $normPemilik = self::normNama($namaPemilikEfektif);

                // Resolve Anggota
                $anggotaId = $anggotaByNama[$normPemilik] ?? null;
                if ($anggotaId === null) {
                    if (! $buatMasterOtomatis) {
                        $gagal[] = ['sheet' => $item['sheet'], 'baris' => $item['baris'], 'alasan' => "Anggota '{$namaPemilikEfektif}' belum terdaftar."];
                        continue;
                    }

                    if ($hashedDefaultPw === null) {
                        $hashedDefaultPw = Hash::make('Assea12345');
                        $existingUsernames = DB::table('users')->pluck('id', 'username')->all();
                    }

                    $baseUser = Str::slug($namaPemilikEfektif, '_') ?: 'anggota';
                    $baseUser = mb_substr($baseUser, 0, 40);
                    $username = $baseUser;
                    $suffix = 1;
                    while (isset($existingUsernames[$username])) {
                        $suffix++;
                        $username = mb_substr($baseUser, 0, 40) . '_' . $suffix;
                    }
                    $existingUsernames[$username] = true;

                    $userId = (int) DB::table('users')->insertGetId([
                        'username' => $username,
                        'name' => mb_substr($namaPemilikEfektif, 0, 150),
                        'email' => null,
                        'password' => $hashedDefaultPw,
                        'is_active' => true,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ]);

                    if ($roleAnggotaId) {
                        DB::table('model_has_roles')->insertOrIgnore([
                            'role_id' => $roleAnggotaId,
                            'model_type' => $userMorphClass,
                            'model_id' => $userId,
                        ]);
                    }

                    do {
                        $urutAnggota++;
                        $kodeAng = 'ANG-' . str_pad((string) $urutAnggota, 4, '0', STR_PAD_LEFT);
                    } while (DB::table('anggota')->where('kode_anggota', $kodeAng)->exists());

                    $anggotaId = (int) DB::table('anggota')->insertGetId([
                        'user_id' => $userId,
                        'kode_anggota' => $kodeAng,
                        'nama_lengkap' => mb_substr($namaPemilikEfektif, 0, 150),
                        'status' => StatusAktif::Aktif->value,
                        'tanggal_bergabung' => $tgl,
                        'keterangan' => 'Dibuat otomatis saat import laporan penjualan',
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ]);

                    $anggotaByNama[$normPemilik] = $anggotaId;
                    $anggotaBaru++;
                }

                // Resolve Kapal
                $namaKapalEfektif = self::isPlaceholderKapal($namaKapalAsli) ? $namaPemilikEfektif : $namaKapalAsli;
                $normKapal = self::normNama($namaKapalEfektif);
                $kapalId = $kapalByKey[$anggotaId . '|' . $normKapal] ?? null;

                // Bila nama kapal asli berupa '-'/'o'/kosong, coba cocokkan lewat surat rekomendasi milik anggota yang sama
                if ($kapalId === null && self::isPlaceholderKapal($namaKapalAsli) && $rekomRaw !== '') {
                    $baseRekom = self::baseNomorSurat($rekomRaw);
                    $kandidat = $suratByAnggotaBase[$anggotaId . '|' . $baseRekom] ?? [];
                    if (count($kandidat) === 1) {
                        $kapalId = $kandidat[0]['kapal_id'];
                    }
                }
                if ($kapalId === null && self::isPlaceholderKapal($namaKapalAsli) && isset($kapalByAnggota[$anggotaId]) && count($kapalByAnggota[$anggotaId]) === 1) {
                    $kapalId = $kapalByAnggota[$anggotaId][0];
                }

                if ($kapalId === null) {
                    if (! $buatMasterOtomatis) {
                        $gagal[] = ['sheet' => $item['sheet'], 'baris' => $item['baris'], 'alasan' => "Kapal '{$namaKapalEfektif}' milik '{$namaPemilikEfektif}' belum terdaftar."];
                        continue;
                    }

                    do {
                        $urutKapal++;
                        $kodeKap = 'KPL-' . str_pad((string) $urutKapal, 4, '0', STR_PAD_LEFT);
                    } while (DB::table('kapal')->where('kode_kapal', $kodeKap)->exists());

                    $kapalId = (int) DB::table('kapal')->insertGetId([
                        'kode_kapal' => $kodeKap,
                        'anggota_id' => $anggotaId,
                        'nama_kapal' => mb_substr($namaKapalEfektif, 0, 150),
                        'nama_pemilik' => mb_substr($namaPemilikEfektif, 0, 150),
                        'ukuran_gt' => $gt !== null && $gt > 0 ? Dec::n($gt) : null,
                        'daya_pk' => $pk !== null && $pk > 0 ? Dec::n($pk) : null,
                        'status' => StatusAktif::Aktif->value,
                        'keterangan' => 'Dibuat otomatis saat import laporan penjualan',
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ]);

                    $kapalByKey[$anggotaId . '|' . $normKapal] = $kapalId;
                    $kapalGt[$kapalId] = $gt !== null && $gt > 0 ? Dec::n($gt) : null;
                    $kapalPk[$kapalId] = $pk !== null && $pk > 0 ? Dec::n($pk) : null;
                    $kapalByAnggota[$anggotaId][] = $kapalId;
                    $kapalBaru++;
                } else {
                    $updKapal = [];
                    if ($gt !== null && $gt > 0 && ($kapalGt[$kapalId] === null || (float) $kapalGt[$kapalId] <= 0)) {
                        $updKapal['ukuran_gt'] = Dec::n($gt);
                        $kapalGt[$kapalId] = $updKapal['ukuran_gt'];
                    }
                    if ($pk !== null && $pk > 0 && (($kapalPk[$kapalId] ?? null) === null || (float) $kapalPk[$kapalId] <= 0)) {
                        $updKapal['daya_pk'] = Dec::n($pk);
                        $kapalPk[$kapalId] = $updKapal['daya_pk'];
                    }
                    if ($updKapal !== []) {
                        $updKapal['updated_at'] = $nowTs;
                        DB::table('kapal')->where('id', $kapalId)->update($updKapal);
                    }
                }

                // Resolve Surat Rekomendasi (dukung >1 surat per kapal+nomor rekom bila berbeda Vol Rekom atau baris duplikat di matriks)
                $rekomNomor = $rekomRaw !== '' ? mb_substr($rekomRaw, 0, 72) : ('IMP-' . substr($tgl, 0, 4) . '-' . $kapalId);
                $baseRekom = self::baseNomorSurat($rekomNomor);
                $kandidatSurat = $suratByKapalBase[$kapalId . '|' . $baseRekom] ?? [];
                $slotMatriks = isset($item['slot']) ? (int) $item['slot'] : null;
                $volRekomNorm = ($volRekom !== null && $volRekom > 0) ? Dec::n($volRekom) : null;
                $suratId = null;

                if ($kandidatSurat !== []) {
                    if ($slotMatriks !== null) {
                        // Mode matriks: bila ada beberapa baris identik pada bulan yang sama, cocokkan berdasar (vol_rekom, urutan slot)
                        if ($volRekomNorm !== null) {
                            $cocokKuota = array_values(array_filter(
                                $kandidatSurat,
                                fn (array $k) => Dec::eq($k['kuota'], $volRekomNorm)
                            ));
                            if (isset($cocokKuota[$slotMatriks])) {
                                $suratId = $cocokKuota[$slotMatriks]['id'];
                            }
                        } elseif (isset($kandidatSurat[$slotMatriks])) {
                            $suratId = $kandidatSurat[$slotMatriks]['id'];
                        }
                    } else {
                        // Mode Lampiran 6: bila ada Vol Rekom eksplisit, cocokkan dengan surat yang kuotanya sama agar 5000 L & 7000 L tidak tergabung
                        if ($volRekomNorm !== null) {
                            foreach ($kandidatSurat as $k) {
                                if (Dec::eq($k['kuota'], $volRekomNorm)) {
                                    $suratId = $k['id'];
                                    break;
                                }
                            }
                        } else {
                            $suratId = $kandidatSurat[0]['id'];
                        }
                    }
                }

                if ($suratId === null) {
                    if (! $buatMasterOtomatis) {
                        $gagal[] = ['sheet' => $item['sheet'], 'baris' => $item['baris'], 'alasan' => "Surat rekomendasi '{$rekomNomor}' untuk kapal '{$namaKapalEfektif}' belum terdaftar."];
                        continue;
                    }

                    $nomorUnik = $rekomNomor;
                    $upperCand = mb_strtoupper($nomorUnik);
                    if (isset($suratNomorTerpakai[$upperCand])) {
                        $n = 1;
                        do {
                            $nomorUnik = mb_substr($baseRekom, 0, 74) . '#' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                            $upperCand = mb_strtoupper($nomorUnik);
                            $n++;
                        } while (isset($suratNomorTerpakai[$upperCand]));
                    }
                    $suratNomorTerpakai[$upperCand] = true;

                    $tglCarbon = Carbon::parse($tgl);
                    $mulai = $tglCarbon->copy()->startOfMonth()->toDateString();
                    $sampai = $tglCarbon->copy()->endOfYear()->toDateString();
                    $kuotaLiter = $volRekomNorm ?? '1000.00';

                    $suratId = (int) DB::table('surat_rekomendasi')->insertGetId([
                        'nomor_surat' => $nomorUnik,
                        'anggota_id' => $anggotaId,
                        'kapal_id' => $kapalId,
                        'tanggal_surat' => $mulai,
                        'berlaku_mulai' => $mulai,
                        'berlaku_sampai' => $sampai,
                        'kuota_bulanan_liter' => $kuotaLiter,
                        'status' => StatusAktif::Aktif->value,
                        'keterangan' => 'Dibuat otomatis saat import laporan penjualan',
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ]);

                    $suratByKapalBase[$kapalId . '|' . $baseRekom][] = ['id' => $suratId, 'kuota' => $kuotaLiter];
                    $suratByAnggotaBase[$anggotaId . '|' . $baseRekom][] = ['id' => $suratId, 'kapal_id' => $kapalId];
                    $suratBerlaku[$suratId] = ['mulai' => $mulai, 'sampai' => $sampai, 'kuota' => $kuotaLiter];
                    $suratBaru++;
                } elseif (isset($suratBerlaku[$suratId])) {
                    // Perluas masa berlaku bila transaksi berada di luar rentang surat yang ada
                    $upd = [];
                    if ($tgl < $suratBerlaku[$suratId]['mulai']) {
                        $upd['berlaku_mulai'] = Carbon::parse($tgl)->startOfMonth()->toDateString();
                        $suratBerlaku[$suratId]['mulai'] = $upd['berlaku_mulai'];
                    }
                    if ($tgl > $suratBerlaku[$suratId]['sampai']) {
                        $upd['berlaku_sampai'] = Carbon::parse($tgl)->endOfMonth()->toDateString();
                        $suratBerlaku[$suratId]['sampai'] = $upd['berlaku_sampai'];
                    }
                    if ($upd !== []) {
                        $upd['updated_at'] = $nowTs;
                        DB::table('surat_rekomendasi')->where('id', $suratId)->update($upd);
                    }
                }

                // Baris khusus master (mis. Cek Rekom dengan penjualan bulan ini = 0): daftarkan master & catat kuota bulanan 0 L agar tampil di export bulan terkait
                if (! Dec::gt($liter, 0)) {
                    $periodeNol = Carbon::parse($tgl)->startOfMonth()->toDateString();
                    $kuotaNol[$suratId . '|' . $periodeNol] = $volRekomNorm ?? ($suratBerlaku[$suratId]['kuota'] ?? '1000.00');
                    continue;
                }

                // Cek duplikat (multiset: tanggal|surat_id|liter)
                if ($lewatiDuplikat) {
                    $fp = $tgl . '|' . $suratId . '|' . $liter;
                    if (($existingSalesCount[$fp] ?? 0) > 0) {
                        $existingSalesCount[$fp]--;
                        $duplikatDilewati++;
                        continue;
                    }
                }

                $siapImpor[] = [
                    'tanggal' => $tgl,
                    'anggota_id' => $anggotaId,
                    'kapal_id' => $kapalId,
                    'surat_id' => $suratId,
                    'liter' => $liter,
                    'vol_rekom' => $volRekom !== null && $volRekom > 0 ? Dec::n($volRekom) : ($suratBerlaku[$suratId]['kuota'] ?? '1000.00'),
                    'tujuan' => 'Melaut',
                ];
                $totalLiterPenjualanBaru = Dec::add($totalLiterPenjualanBaru, $liter);
            }

            // 5. Pastikan saldo stok fisik akhir tidak negatif
            $stokSetelahTerima = Dec::add($runningSaldo, $totalLiterPenerimaanBaru);
            if (Dec::lt(Dec::sub($stokSetelahTerima, $totalLiterPenjualanBaru), 0)) {
                throw new BisnisException(sprintf(
                    'Stok fisik tidak mencukupi untuk mengimpor penjualan sebesar %s (stok tersedia termasuk penerimaan baru: %s). Aktifkan opsi impor penerimaan atau catat Penerimaan BBM terlebih dahulu.',
                    Format::liter($totalLiterPenjualanBaru),
                    Format::liter($stokSetelahTerima)
                ));
            }

            // 6. Simpan Penerimaan BBM baru + kumpulkan mutasi stok penerimaan
            $mutasiPending = [];
            $pasokanDelta = []; // periode => ['diterima' => ..., 'disalurkan' => ...]
            $penerimaanMorph = (new PenerimaanBbm())->getMorphClass();

            foreach ($penerimaanBaru as $tgl => $liter) {
                $tglC = Carbon::parse($tgl);
                $ym = $tglC->format('Ym');
                $periode = $tglC->copy()->startOfMonth()->toDateString();
                $nomorTrm = $nextNomor('penerimaan', $ym);

                $trmId = (int) DB::table('penerimaan_bbm')->insertGetId([
                    'nomor_penerimaan' => $nomorTrm,
                    'tanggal_penerimaan' => $tgl,
                    'periode' => $periode,
                    'nama_pemasok' => 'PT Pertamina Patra Niaga',
                    'liter' => $liter,
                    'status' => StatusPenerimaan::Terkonfirmasi->value,
                    'override_pasokan' => false,
                    'dicatat_oleh' => $oleh->id,
                    'keterangan' => 'Import laporan penjualan',
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ]);

                $mutasiPending[] = [
                    '_urut' => 0,
                    'tanggal' => $tgl . ' 07:00:00',
                    'jenis' => JenisMutasi::Penerimaan->value,
                    'delta_fisik' => $liter,
                    'referensi_type' => $penerimaanMorph,
                    'referensi_id' => $trmId,
                    'keterangan' => "Penerimaan {$nomorTrm} (import laporan)",
                    'dibuat_oleh' => $oleh->id,
                    'created_at' => $nowTs,
                ];

                $pasokanDelta[$periode]['diterima'] = Dec::add($pasokanDelta[$periode]['diterima'] ?? '0.00', $liter);
            }

            // 7. Simpan Penjualan lengkap (pengajuan -> alokasi -> penyaluran -> tagihan -> pembayaran -> mutasi_stok)
            $hargaAktif = $this->harga->aktif();
            $penyaluranMorph = (new PenyaluranBbm())->getMorphClass();
            $kuotaDelta = []; // "surat_id|periode" => ['terpakai' => ..., 'batas' => ...]

            foreach ($siapImpor as $row) {
                $tgl = $row['tanggal'];
                $tglC = Carbon::parse($tgl);
                $ym = $tglC->format('Ym');
                $periode = $tglC->copy()->startOfMonth()->toDateString();
                $dt = $tgl . ' 12:00:00';
                $liter = $row['liter'];
                $totalRp = Dec::mul($liter, $hargaAktif);

                $noPgj = $nextNomor('pengajuan', $ym);
                $noSlr = $nextNomor('penyaluran', $ym);
                $noTgh = $nextNomor('tagihan', $ym);
                $noByr = $nextNomor('pembayaran', $ym);

                $pgjId = (int) DB::table('pengajuan_pembelian')->insertGetId([
                    'nomor_pengajuan' => $noPgj,
                    'anggota_id' => $row['anggota_id'],
                    'kapal_id' => $row['kapal_id'],
                    'surat_rekomendasi_id' => $row['surat_id'],
                    'liter_diminta' => $liter,
                    'harga_estimasi_per_liter' => $hargaAktif,
                    'total_estimasi' => $totalRp,
                    'tanggal_rencana' => $tgl,
                    'tujuan' => $row['tujuan'],
                    'catatan' => 'Import laporan penjualan',
                    'status' => StatusPengajuan::Selesai->value,
                    'status_pembayaran' => StatusPembayaran::Lunas->value,
                    'status_penyaluran' => StatusPenyaluran::Selesai->value,
                    'harga_per_liter' => $hargaAktif,
                    'total_tagihan' => $totalRp,
                    'periode_kuota' => $periode,
                    'disetujui_oleh' => $oleh->id,
                    'disetujui_pada' => $dt,
                    'selesai_pada' => $dt,
                    'created_at' => $dt,
                    'updated_at' => $dt,
                ]);

                $aloId = (int) DB::table('alokasi_bbm')->insertGetId([
                    'pengajuan_id' => $pgjId,
                    'periode' => $periode,
                    'liter_disetujui' => $liter,
                    'liter_tersalurkan' => $liter,
                    'liter_dilepas' => '0.00',
                    'status' => StatusAlokasi::Selesai->value,
                    'created_at' => $dt,
                    'updated_at' => $dt,
                ]);

                $slrId = (int) DB::table('penyaluran_bbm')->insertGetId([
                    'nomor_penyaluran' => $noSlr,
                    'pengajuan_id' => $pgjId,
                    'alokasi_id' => $aloId,
                    'liter_disetujui' => $liter,
                    'liter_aktual' => $liter,
                    'tanggal_penyaluran' => $dt,
                    'petugas_id' => $oleh->id,
                    'keterangan' => 'Import laporan penjualan',
                    'created_at' => $dt,
                    'updated_at' => $dt,
                ]);

                $tghId = (int) DB::table('tagihan')->insertGetId([
                    'nomor_tagihan' => $noTgh,
                    'pengajuan_id' => $pgjId,
                    'anggota_id' => $row['anggota_id'],
                    'liter' => $liter,
                    'harga_per_liter' => $hargaAktif,
                    'total' => $totalRp,
                    'status' => StatusTagihan::Lunas->value,
                    'tanggal_tagihan' => $tgl,
                    'created_at' => $dt,
                    'updated_at' => $dt,
                ]);

                DB::table('pembayaran')->insert([
                    'nomor_pembayaran' => $noByr,
                    'tagihan_id' => $tghId,
                    'jumlah' => $totalRp,
                    'tanggal_pembayaran' => $dt,
                    'metode' => MetodePembayaran::Tunai->value,
                    'nomor_bukti' => 'IMP-' . $noSlr,
                    'status' => StatusPembayaranCatatan::Sah->value,
                    'tagihan_aktif_id' => $tghId,
                    'diterima_oleh' => $oleh->id,
                    'created_at' => $dt,
                    'updated_at' => $dt,
                ]);

                $mutasiPending[] = [
                    '_urut' => 1,
                    'tanggal' => $dt,
                    'jenis' => JenisMutasi::Penyaluran->value,
                    'delta_fisik' => Dec::sub('0', $liter),
                    'referensi_type' => $penyaluranMorph,
                    'referensi_id' => $slrId,
                    'keterangan' => "Penyaluran {$noSlr} (import laporan)",
                    'dibuat_oleh' => $oleh->id,
                    'created_at' => $nowTs,
                ];

                $kKey = $row['surat_id'] . '|' . $periode;
                $kuotaDelta[$kKey]['terpakai'] = Dec::add($kuotaDelta[$kKey]['terpakai'] ?? '0.00', $liter);
                $kuotaDelta[$kKey]['batas'] = Dec::gt($row['vol_rekom'], $kuotaDelta[$kKey]['batas'] ?? '0.00')
                    ? $row['vol_rekom']
                    : ($kuotaDelta[$kKey]['batas'] ?? $row['vol_rekom']);

                $pasokanDelta[$periode]['disalurkan'] = Dec::add($pasokanDelta[$periode]['disalurkan'] ?? '0.00', $liter);
            }

            // Simpan mutasi_stok secara kronologis agar saldo_fisik harian selalu akurat
            usort($mutasiPending, fn (array $a, array $b) => [$a['tanggal'], $a['_urut'], $a['referensi_id']] <=> [$b['tanggal'], $b['_urut'], $b['referensi_id']]);
            $mutasiBatch = [];
            foreach ($mutasiPending as $mp) {
                $runningSaldo = Dec::add($runningSaldo, $mp['delta_fisik']);
                unset($mp['_urut']);
                $mp['saldo_fisik'] = $runningSaldo;
                $mutasiBatch[] = $mp;
                if (count($mutasiBatch) >= 500) {
                    DB::table('mutasi_stok')->insert($mutasiBatch);
                    $mutasiBatch = [];
                }
            }
            if ($mutasiBatch !== []) {
                DB::table('mutasi_stok')->insert($mutasiBatch);
            }

            // 8. Perbarui saldo stok fisik akhir (konsisten penuh dengan mutasi_stok)
            $stokRow->forceFill(['stok_fisik' => $runningSaldo])->save();

            // 9. Perbarui penomoran dokumen
            foreach ($countersDirty as $key => $lastVal) {
                [$jenis, $ym] = explode('|', $key, 2);
                DB::table('penomoran')
                    ->where('jenis', $jenis)
                    ->where('periode', $ym)
                    ->update(['nomor_terakhir' => $lastVal, 'updated_at' => $nowTs]);
            }

            // 10. Perbarui kuota_rekomendasi_bulanan (memastikan CHECK constraint batas_liter selalu terpenuhi)
            foreach ($kuotaDelta as $kKey => $delta) {
                [$suratIdStr, $periode] = explode('|', $kKey, 2);
                $suratId = (int) $suratIdStr;
                $existing = DB::table('kuota_rekomendasi_bulanan')
                    ->where('surat_rekomendasi_id', $suratId)
                    ->where('periode', $periode)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $terpakaiBaru = Dec::add($existing->liter_terpakai, $delta['terpakai']);
                    $minBatas = Dec::add($existing->liter_dialokasikan, $terpakaiBaru);
                    $batasBaru = Dec::gt($existing->batas_liter, $delta['batas']) ? Dec::n($existing->batas_liter) : $delta['batas'];
                    if (Dec::lt($batasBaru, $minBatas)) {
                        $batasBaru = $minBatas;
                    }
                    DB::table('kuota_rekomendasi_bulanan')
                        ->where('id', $existing->id)
                        ->update([
                            'batas_liter' => $batasBaru,
                            'liter_terpakai' => $terpakaiBaru,
                            'updated_at' => $nowTs,
                        ]);
                } else {
                    $batas = Dec::gt($delta['batas'], $delta['terpakai']) ? $delta['batas'] : $delta['terpakai'];
                    DB::table('kuota_rekomendasi_bulanan')->insert([
                        'surat_rekomendasi_id' => $suratId,
                        'periode' => $periode,
                        'batas_liter' => $batas,
                        'liter_dialokasikan' => '0.00',
                        'liter_terpakai' => $delta['terpakai'],
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ]);
                }
            }

            // 10b. Pastikan baris Cek Rekom dengan penyaluran 0 L memiliki entri kuota_rekomendasi_bulanan agar tetap tampil pada export bulan terkait
            foreach ($kuotaNol as $kKey => $batasNol) {
                if (isset($kuotaDelta[$kKey])) {
                    continue;
                }
                [$suratIdStr, $periode] = explode('|', $kKey, 2);
                DB::table('kuota_rekomendasi_bulanan')->insertOrIgnore([
                    'surat_rekomendasi_id' => (int) $suratIdStr,
                    'periode' => $periode,
                    'batas_liter' => $batasNol,
                    'liter_dialokasikan' => '0.00',
                    'liter_terpakai' => '0.00',
                    'created_at' => $nowTs,
                    'updated_at' => $nowTs,
                ]);
            }

            // 11. Perbarui kuota_pasokan_bulanan (memastikan CHECK constraint batas_liter selalu terpenuhi)
            $defaultPasokan = $this->pengaturan->angka('pasokan.batas_bulanan');
            foreach ($pasokanDelta as $periode => $delta) {
                $terimaDelta = $delta['diterima'] ?? '0.00';
                $salurDelta = $delta['disalurkan'] ?? '0.00';
                $existing = DB::table('kuota_pasokan_bulanan')
                    ->where('periode', $periode)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $diterimaBaru = Dec::add($existing->total_diterima, $terimaDelta);
                    $disalurkanBaru = Dec::add($existing->total_disalurkan, $salurDelta);
                    $minBatas = Dec::add($existing->total_dialokasikan, $disalurkanBaru);
                    $batasBaru = Dec::gt($existing->batas_liter, $minBatas) ? Dec::n($existing->batas_liter) : $minBatas;
                    DB::table('kuota_pasokan_bulanan')
                        ->where('id', $existing->id)
                        ->update([
                            'batas_liter' => $batasBaru,
                            'total_diterima' => $diterimaBaru,
                            'total_disalurkan' => $disalurkanBaru,
                            'updated_at' => $nowTs,
                        ]);
                } else {
                    $batas = Dec::gt($defaultPasokan, $salurDelta) ? $defaultPasokan : $salurDelta;
                    DB::table('kuota_pasokan_bulanan')->insert([
                        'periode' => $periode,
                        'batas_liter' => $batas,
                        'total_diterima' => $terimaDelta,
                        'total_dialokasikan' => '0.00',
                        'total_disalurkan' => $salurDelta,
                        'created_at' => $nowTs,
                        'updated_at' => $nowTs,
                    ]);
                }
            }

            $jmlPenjualan = count($siapImpor);
            $jmlPenerimaan = count($penerimaanBaru);

            if ($jmlPenjualan > 0 || $jmlPenerimaan > 0 || $anggotaBaru > 0 || $kapalBaru > 0 || $suratBaru > 0) {
                if ($jmlPenjualan > 0) {
                    $ringkasAudit = "Import Laporan Penjualan ({$jmlPenjualan} transaksi / " . Format::liter($totalLiterPenjualanBaru) . ')';
                } elseif ($jmlPenerimaan > 0) {
                    $ringkasAudit = "Import Laporan Stok/Penerimaan ({$jmlPenerimaan} hari / " . Format::liter($totalLiterPenerimaanBaru) . ')';
                } else {
                    $ringkasAudit = "Import Master/Rekomendasi ({$anggotaBaru} anggota, {$kapalBaru} kapal, {$suratBaru} surat)";
                }

                AuditService::catat(
                    'laporan.diimpor',
                    null,
                    null,
                    [
                        'penjualan' => $jmlPenjualan,
                        'liter_penjualan' => $totalLiterPenjualanBaru,
                        'penerimaan' => $jmlPenerimaan,
                        'liter_penerimaan' => $totalLiterPenerimaanBaru,
                        'penerimaan_dilewati' => $penerimaanDilewati,
                        'duplikat_dilewati' => $duplikatDilewati,
                        'anggota_baru' => $anggotaBaru,
                        'kapal_baru' => $kapalBaru,
                        'surat_baru' => $suratBaru,
                    ],
                    $ringkasAudit,
                    $oleh
                );
                LencanaCache::hapus();
            }

            return [
                'penjualan' => $jmlPenjualan,
                'liter_penjualan' => $totalLiterPenjualanBaru,
                'penerimaan' => $jmlPenerimaan,
                'liter_penerimaan' => $totalLiterPenerimaanBaru,
                'penerimaan_dilewati' => $penerimaanDilewati,
                'duplikat_dilewati' => $duplikatDilewati,
                'anggota_baru' => $anggotaBaru,
                'kapal_baru' => $kapalBaru,
                'surat_baru' => $suratBaru,
                'gagal' => $gagal,
            ];
        });
    }

    /**
     * Membaca seluruh sheet file Lampiran 6 (.xlsx/.xls/.csv) hemat memori.
     *
     * @return array{
     *   penerimaan:array<string, string>,
     *   penjualan:array<int, array<string, mixed>>,
     *   gagal:array<int, array{sheet:string, baris:int, alasan:string}>
     * }
     */
    private function bacaFile(string $fullPath): array
    {
        try {
            $reader = IOFactory::createReaderForFile($fullPath);
            if ($reader instanceof BaseReader) {
                $reader->setReadDataOnly(true);
                $reader->setReadEmptyCells(false);
            }
            $spreadsheet = $reader->load($fullPath);
        } catch (\Throwable $e) {
            throw new BisnisException('Gagal membaca file Excel/CSV. Pastikan format file valid (.xlsx, .xls, atau .csv).');
        }

        $penerimaan = [];
        $penjualan = [];
        $gagal = [];
        $urutan = 0;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();
            $sheetMeta = self::deteksiBulanTahun($sheetName);
            $blockBulan = $sheetMeta['bulan'];
            $blockTahun = $sheetMeta['tahun'];
            $mode = null; // 'penjualan' (Lampiran 6) | 'stok' (Stock Produk) | 'matriks' (Cek Rekom / Rekap Penjualan 1-31)
            $peta = null;
            $dayCols = [];
            $matrixSlotCount = [];
            $currentDate = null;
            $currentDateHasSale = false;
            $lastNo = null;
            $inFooter = false;

            $highestColIdx = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 52);
            $maxColLetter = Coordinate::stringFromColumnIndex(max($highestColIdx, 1));

            foreach ($sheet->getRowIterator() as $row) {
                $rowIdx = $row->getRowIndex();
                $cellIter = $row->getCellIterator('A', $maxColLetter);
                $cellIter->setIterateOnlyExistingCells(true);

                $vals = [];
                $colIdxMap = [];
                foreach ($cellIter as $colLetter => $cell) {
                    try {
                        $v = $cell->getCalculatedValue();
                    } catch (\Throwable) {
                        $v = $cell->getValue();
                    }
                    $vals[$colLetter] = $v;
                    $colIdxMap[$colLetter] = trim((string) $v);
                }

                if ($vals === []) {
                    continue;
                }

                $gabungUpper = mb_strtoupper(implode(' ', $colIdxMap));

                // 1) Deteksi baris header Lampiran 6 (Laporan Penjualan Produk)
                if (str_contains($gabungUpper, 'NAMA KAPAL') && str_contains($gabungUpper, 'NAMA PEMILIK') && str_contains($gabungUpper, 'PENJUALAN')) {
                    $mode = 'penjualan';
                    $peta = $this->petakanKolom($vals);
                    $dayCols = [];
                    $currentDate = null;
                    $currentDateHasSale = false;
                    $lastNo = null;
                    $inFooter = false;
                    continue;
                }

                // 2) Deteksi baris header Matriks Cek Rekom / Laporan Surat Rekomendasi / Rekap Penjualan Sektor Perikanan
                if (
                    (str_contains($gabungUpper, 'NO REKOM') || str_contains($gabungUpper, 'NO. REKOM'))
                    && (str_contains($gabungUpper, 'NAMA KAPAL') || str_contains($gabungUpper, 'VOL REKOM') || str_contains($gabungUpper, 'PENYALURAN') || str_contains($gabungUpper, 'TANGGAL PENJUALAN'))
                ) {
                    $mode = 'matriks';
                    $peta = $this->petakanKolomMatriks($vals);
                    $dayCols = [];
                    $matrixSlotCount = [];
                    $currentDate = null;
                    $currentDateHasSale = false;
                    $lastNo = null;
                    $inFooter = false;
                    continue;
                }

                // 3) Deteksi baris header Laporan Stock Produk Solar / Penerimaan BBM
                if (
                    str_contains($gabungUpper, 'TANGGAL')
                    && (str_contains($gabungUpper, 'PENERIMAAN') || str_contains($gabungUpper, 'PENERIAMAN'))
                    && ! str_contains($gabungUpper, 'NAMA KAPAL')
                ) {
                    $mode = 'stok';
                    $peta = $this->petakanKolomStok($vals);
                    $dayCols = [];
                    $currentDate = null;
                    $currentDateHasSale = false;
                    $lastNo = null;
                    $inFooter = false;
                    continue;
                }

                // Tangkap nama bulan/tahun dari baris judul blok (saat di luar isi tabel agar nama anggota 'Januari' tidak salah baca)
                if ($mode === null || $inFooter) {
                    $metaBaris = self::deteksiBulanTahun($gabungUpper);
                    if ($metaBaris['bulan'] !== null) {
                        $blockBulan = $metaBaris['bulan'];
                    }
                    if ($metaBaris['tahun'] !== null) {
                        $blockTahun = $metaBaris['tahun'];
                    }
                }

                // Deteksi kop / tanda tangan
                if (
                    str_contains($gabungUpper, 'LAPORAN PENJUALAN PRODUK')
                    || str_contains($gabungUpper, 'LAPORAN STOCK PRODUK')
                    || str_contains($gabungUpper, 'LAPORAN STOK PRODUK')
                    || str_contains($gabungUpper, 'PANGKALAN BRANDAN')
                    || str_contains($gabungUpper, 'HORMAT KAMI')
                    || trim($gabungUpper) === 'KETUA'
                    || trim($gabungUpper) === 'SYAFRIYANI'
                ) {
                    $inFooter = true;
                    continue;
                }

                if ($peta === null || $inFooter) {
                    continue;
                }

                // Mode Matriks Cek Rekom / Rekap Penjualan (kolom 1..31)
                if ($mode === 'matriks') {
                    if ($dayCols === []) {
                        $kandidatHari = [];
                        foreach ($vals as $colLetter => $v) {
                            if (is_numeric($v) && (float) $v == (float) (int) $v) {
                                $iv = (int) $v;
                                if ($iv >= 1 && $iv <= 31) {
                                    $kandidatHari[$colLetter] = $iv;
                                }
                            }
                        }
                        if (count($kandidatHari) >= 15) {
                            $dayCols = $kandidatHari;
                        }
                        continue;
                    }

                    $noUpper = mb_strtoupper(trim((string) ($vals[$peta['no']] ?? '')));
                    $namaUpper = $peta['nama'] !== null ? mb_strtoupper(trim((string) ($vals[$peta['nama']] ?? ''))) : '';
                    $kapalUpper = mb_strtoupper(trim((string) ($vals[$peta['nama_kapal']] ?? '')));
                    if (
                        in_array($noUpper, ['TOTAL', 'JUMLAH'], true)
                        || in_array($namaUpper, ['TOTAL', 'JUMLAH'], true)
                        || in_array($kapalUpper, ['TOTAL', 'JUMLAH'], true)
                    ) {
                        $inFooter = true;
                        continue;
                    }

                    $namaKapal = self::bersihkanTeks($vals[$peta['nama_kapal']] ?? null);
                    $namaPemilik = $peta['nama'] !== null ? self::bersihkanTeks($vals[$peta['nama']] ?? null) : '';
                    $rekom = self::bersihkanTeks($vals[$peta['rekomendasi']] ?? null);
                    $volRekom = $peta['vol_rekom'] !== null ? self::parseAngka($vals[$peta['vol_rekom']] ?? null) : null;

                    if ($namaPemilik === '' && $namaKapal === '' && $rekom === '') {
                        continue;
                    }
                    if ($namaPemilik === '') {
                        $namaPemilik = $namaKapal;
                    }

                    $bulan = $blockBulan ?? (int) now()->month;
                    $tahun = $blockTahun;
                    if ($tahun === null && preg_match('/\/(20\d{2})\b/', $rekom, $ymMatch) === 1) {
                        $tahun = (int) $ymMatch[1];
                    }
                    $tahun ??= (int) now()->year;

                    // Catat urutan kemunculan baris dengan (pemilik|kapal|no_rekom|vol_rekom) identik dalam 1 bulan agar 2 baris identik tidak tergabung
                    $slotKey = self::normNama($namaPemilik) . '|' . self::normNama($namaKapal) . '|' . self::baseNomorSurat($rekom) . '|' . ($volRekom !== null && $volRekom > 0 ? Dec::n($volRekom) : '');
                    $slotIdx = $matrixSlotCount[$slotKey] ?? 0;
                    $matrixSlotCount[$slotKey] = $slotIdx + 1;

                    $adaPenjualanBaris = false;
                    foreach ($dayCols as $colLetter => $hari) {
                        $jualHari = self::parseAngka($vals[$colLetter] ?? null);
                        if ($jualHari === null || $jualHari <= 0) {
                            continue;
                        }
                        if (! checkdate($bulan, $hari, $tahun)) {
                            continue;
                        }
                        $adaPenjualanBaris = true;
                        $penjualan[] = [
                            'urutan' => ++$urutan,
                            'sheet' => $sheetName,
                            'baris' => $rowIdx,
                            'tanggal' => sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari),
                            'nama_kapal' => $namaKapal,
                            'nama_pemilik' => $namaPemilik,
                            'gt' => null,
                            'rekomendasi' => $rekom,
                            'vol_rekom' => $volRekom,
                            'penjualan' => $jualHari,
                            'slot' => $slotIdx,
                        ];
                    }

                    // Jika baris Cek Rekom bulan ini belum ada penyaluran (total = 0), tetap daftarkan master Anggota/Kapal/Surat + kuota bulan terkait
                    if (! $adaPenjualanBaris && ($namaPemilik !== '' || $namaKapal !== '')) {
                        $penjualan[] = [
                            'urutan' => ++$urutan,
                            'sheet' => $sheetName,
                            'baris' => $rowIdx,
                            'tanggal' => sprintf('%04d-%02d-01', $tahun, $bulan),
                            'nama_kapal' => $namaKapal,
                            'nama_pemilik' => $namaPemilik,
                            'gt' => null,
                            'rekomendasi' => $rekom,
                            'vol_rekom' => $volRekom,
                            'penjualan' => 0.0,
                            'slot' => $slotIdx,
                        ];
                    }

                    continue;
                }

                // Mode Laporan Stock Produk Solar / Penerimaan BBM (6 kolom: NO, TANGGAL, STOCK AWAL, PENERIMAAN, PENJUALAN, STOCK AKHIR)
                if ($mode === 'stok') {
                    $rawTgl = $vals[$peta['tanggal']] ?? null;
                    $adaTanggal = $rawTgl !== null && trim((string) $rawTgl) !== '';
                    if (! $adaTanggal) {
                        // Baris total bulanan (,,,160000,160000,) atau tanggal kosong (29,,,,,) dilewati
                        continue;
                    }

                    $parsedDate = self::parseTanggal($rawTgl);
                    if ($parsedDate === null) {
                        continue;
                    }

                    $terimaVal = self::parseAngka($vals[$peta['penerimaan']] ?? null);
                    if ($terimaVal !== null && $terimaVal > 0) {
                        $penerimaan[$parsedDate] = Dec::add($penerimaan[$parsedDate] ?? '0.00', Dec::n($terimaVal));
                    }

                    continue;
                }

                $rawNo = $vals[$peta['no']] ?? null;
                $rawTgl = $vals[$peta['tanggal']] ?? null;
                $namaKapal = self::bersihkanTeks($vals[$peta['nama_kapal']] ?? null);
                $namaPemilik = self::bersihkanTeks($vals[$peta['nama_pemilik']] ?? null);
                $gt = self::parseAngka($vals[$peta['gt']] ?? null);
                $pk = self::parseAngka($vals[$peta['pk']] ?? null);
                $rekom = self::bersihkanTeks($vals[$peta['rekomendasi']] ?? null);
                $volRekom = self::parseAngka($vals[$peta['vol_rekom']] ?? null);
                $terimaVal = self::parseAngka($vals[$peta['penerimaan']] ?? null);
                $jualVal = self::parseAngka($vals[$peta['penjualan']] ?? null);

                $noInt = is_numeric($rawNo) ? (int) $rawNo : null;
                $adaTanggal = $rawTgl !== null && trim((string) $rawTgl) !== '';

                // Tangkap baris template sisa di bawah tabel (No melompat jauh / mundur tanpa tanggal)
                if (! $adaTanggal && $noInt !== null && $lastNo !== null && ($noInt > $lastNo + 5 || $noInt < $lastNo)) {
                    $inFooter = true;
                    continue;
                }

                if ($adaTanggal) {
                    $parsedDate = self::parseTanggal($rawTgl);
                    if ($parsedDate === null) {
                        $gagal[] = ['sheet' => $sheetName, 'baris' => $rowIdx, 'alasan' => 'Format tanggal tidak dikenali: ' . trim((string) $rawTgl)];
                        continue;
                    }
                    $currentDate = $parsedDate;
                    $currentDateHasSale = $jualVal !== null && $jualVal > 0;
                    if ($noInt !== null) {
                        $lastNo = $noInt;
                    }
                } else {
                    if ($currentDate === null) {
                        continue;
                    }
                    // Baris lanjutan setelah tanggal yang kosong (tidak ada penjualan) = baris sisa di luar tabel
                    if (! $currentDateHasSale && $jualVal !== null && $jualVal > 0) {
                        continue;
                    }
                    if ($noInt !== null && ($lastNo === null || ($noInt >= $lastNo && $noInt <= $lastNo + 5))) {
                        $lastNo = $noInt;
                    }
                }

                if ($terimaVal !== null && $terimaVal > 0) {
                    $penerimaan[$currentDate] = Dec::add($penerimaan[$currentDate] ?? '0.00', Dec::n($terimaVal));
                }

                if ($jualVal !== null && $jualVal > 0) {
                    if ($namaPemilik === '' && $namaKapal === '') {
                        continue;
                    }
                    $penjualan[] = [
                        'urutan' => ++$urutan,
                        'sheet' => $sheetName,
                        'baris' => $rowIdx,
                        'tanggal' => $currentDate,
                        'nama_kapal' => $namaKapal,
                        'nama_pemilik' => $namaPemilik,
                        'gt' => $gt,
                        'pk' => $pk,
                        'rekomendasi' => $rekom,
                        'vol_rekom' => $volRekom,
                        'penjualan' => $jualVal,
                    ];
                }
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return [
            'penerimaan' => $penerimaan,
            'penjualan' => $penjualan,
            'gagal' => $gagal,
        ];
    }

    /** @return array<string, string> */
    private function petakanKolom(array $headerVals): array
    {
        $peta = [
            'no' => 'A',
            'tanggal' => 'B',
            'nama_kapal' => 'C',
            'nama_pemilik' => 'D',
            'gt' => 'E',
            'pk' => 'F',
            'rekomendasi' => 'G',
            'vol_rekom' => 'H',
            'penerimaan' => 'J',
            'penjualan' => 'K',
            'tujuan' => 'M',
        ];

        foreach ($headerVals as $col => $raw) {
            $h = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $raw)));
            if ($h === '') {
                continue;
            }
            if ($h === 'no' || $h === 'no.') {
                $peta['no'] = $col;
            } elseif (str_contains($h, 'tanggal') || $h === 'tgl') {
                $peta['tanggal'] = $col;
            } elseif (str_contains($h, 'nama kapal')) {
                $peta['nama_kapal'] = $col;
            } elseif (str_contains($h, 'nama pemilik')) {
                $peta['nama_pemilik'] = $col;
            } elseif ($h === 'gt') {
                $peta['gt'] = $col;
            } elseif ($h === 'pk') {
                $peta['pk'] = $col;
            } elseif (str_contains($h, 'rekomendasi') && ! str_contains($h, 'jml') && ! str_contains($h, 'vol') && ! str_contains($h, 'direkomendasikan')) {
                $peta['rekomendasi'] = $col;
            } elseif (str_contains($h, 'direkomendasikan') || str_contains($h, 'vol rekom')) {
                $peta['vol_rekom'] = $col;
            } elseif (str_contains($h, 'penerimaan') || str_contains($h, 'peneriaman')) {
                $peta['penerimaan'] = $col;
            } elseif (str_contains($h, 'penjualan')) {
                $peta['penjualan'] = $col;
            } elseif (str_contains($h, 'tujuan')) {
                $peta['tujuan'] = $col;
            }
        }

        return $peta;
    }

    /** @return array<string, string> */
    private function petakanKolomStok(array $headerVals): array
    {
        $peta = [
            'no' => 'A',
            'tanggal' => 'B',
            'penerimaan' => 'D',
        ];

        foreach ($headerVals as $col => $raw) {
            $h = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $raw)));
            if ($h === '') {
                continue;
            }
            if ($h === 'no' || $h === 'no.') {
                $peta['no'] = $col;
            } elseif (str_contains($h, 'tanggal') || $h === 'tgl') {
                $peta['tanggal'] = $col;
            } elseif (str_contains($h, 'penerimaan') || str_contains($h, 'peneriaman')) {
                $peta['penerimaan'] = $col;
            }
        }

        return $peta;
    }

    /** @return array{no:string, nama:?string, nama_kapal:string, rekomendasi:string, vol_rekom:?string} */
    private function petakanKolomMatriks(array $headerVals): array
    {
        $peta = [
            'no' => 'B',
            'nama' => null,
            'nama_kapal' => 'C',
            'rekomendasi' => 'D',
            'vol_rekom' => null,
        ];

        foreach ($headerVals as $col => $raw) {
            $h = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', (string) $raw)));
            if ($h === '') {
                continue;
            }
            if ($h === 'no' || $h === 'no.') {
                $peta['no'] = $col;
            } elseif ($h === 'nama' || str_contains($h, 'nama pemilik')) {
                $peta['nama'] = $col;
            } elseif (str_contains($h, 'nama kapal')) {
                $peta['nama_kapal'] = $col;
            } elseif (str_contains($h, 'no rekom') || str_contains($h, 'no. rekom') || str_contains($h, 'nomor rekom')) {
                $peta['rekomendasi'] = $col;
            } elseif (str_contains($h, 'vol rekom')) {
                $peta['vol_rekom'] = $col;
            }
        }

        return $peta;
    }

    /** @return array{bulan:?int, tahun:?int} */
    private static function deteksiBulanTahun(string $teks): array
    {
        $bulan = null;
        if (preg_match_all('/[A-Za-z]+/', $teks, $m) > 0) {
            foreach ($m[0] as $kata) {
                $low = strtolower($kata);
                if (isset(self::BULAN_ANGKA[$low])) {
                    $bulan = self::BULAN_ANGKA[$low];
                    break;
                }
            }
        }

        $tahun = null;
        if (preg_match('/\b(20\d{2})\b/', $teks, $ym) === 1) {
            $tahun = (int) $ym[1];
        }

        return ['bulan' => $bulan, 'tahun' => $tahun];
    }

    private static function parseTanggal(mixed $nilai): ?string
    {
        if (is_numeric($nilai)) {
            $num = (float) $nilai;
            if ($num > 20000 && $num < 80000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($num))->toDateString();
            }

            return null;
        }

        $teks = trim((string) $nilai);
        if ($teks === '') {
            return null;
        }

        // Ganti nama/singkatan bulan Indonesia -> Inggris agar Carbon::parse akurat
        $norm = preg_replace_callback('/[A-Za-z]+/', function (array $m) {
            $low = strtolower($m[0]);

            return self::BULAN_ID[$low] ?? $m[0];
        }, $teks);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'j-M-y', 'd-M-y', 'j-M-Y', 'd-M-Y', 'Y/m/d'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, (string) $norm);
                if ($dt !== false) {
                    return $dt->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse((string) $norm)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseAngka(mixed $nilai): ?float
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai;
        }
        $teks = trim((string) $nilai);
        if ($teks === '' || $teks === '-') {
            return null;
        }
        // Tangani format angka dengan pemisah ribuan koma (16,000) atau titik (16.000)
        $clean = str_replace(' ', '', $teks);
        if (preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $clean) === 1) {
            $clean = str_replace(',', '', $clean);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $clean) === 1) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } elseif (str_contains($clean, ',') && ! str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private static function bersihkanTeks(mixed $nilai): string
    {
        if ($nilai === null) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', (string) $nilai));
    }

    private static function normNama(string $teks): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $teks)));
    }

    private static function baseNomorSurat(string $nomor): string
    {
        return mb_strtoupper(trim((string) preg_replace('/#\d+$/', '', trim($nomor))));
    }

    private static function isPlaceholderKapal(string $nama): bool
    {
        $low = mb_strtolower(trim($nama));

        return $low === '' || in_array($low, ['-', 'o', '0', 'nn', 'tanpa nama', 'tanpa nama (nn)'], true);
    }
}
