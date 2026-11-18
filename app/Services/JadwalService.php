<?php

namespace App\Services;

use App\Enums\StatusJadwal;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPenyaluran;
use App\Exceptions\BisnisException;
use App\Models\AlokasiBbm;
use App\Models\JadwalPembelian;
use App\Models\PengajuanPembelian;
use App\Models\User;
use App\Support\Otorisasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class JadwalService
{
    public function __construct(private NomorService $nomor)
    {
    }

    public function buat(PengajuanPembelian $pengajuan, Carbon|string $tanggalWaktu, ?string $keterangan, User $user): JadwalPembelian
    {
        Otorisasi::pastikan($user, 'jadwal.manage');

        return DB::transaction(function () use ($pengajuan, $tanggalWaktu, $keterangan, $user) {
            $p = PengajuanPembelian::withoutGlobalScopes()->lockForUpdate()->findOrFail($pengajuan->id);
            if ($p->status !== StatusPengajuan::Disetujui || $p->status_penyaluran === StatusPenyaluran::Selesai) {
                throw new BisnisException('Jadwal hanya dapat dibuat untuk pengajuan disetujui yang belum disalurkan.');
            }
            $aktif = JadwalPembelian::query()->withoutGlobalScopes()->where('pengajuan_id', $p->id)
                ->whereIn('status', [StatusJadwal::Terjadwal->value, StatusJadwal::Ditunda->value])->exists();
            if ($aktif) {
                throw new BisnisException('Pengajuan ini sudah memiliki jadwal aktif. Ubah atau tunda jadwal yang ada.');
            }

            $waktu = Carbon::parse($tanggalWaktu);
            $alokasi = AlokasiBbm::query()->where('pengajuan_id', $p->id)->firstOrFail();

            $j = JadwalPembelian::create([
                'nomor_jadwal' => $this->nomor->terbitkan('jadwal', $waktu),
                'pengajuan_id' => $p->id, 'tanggal_waktu' => $waktu, 'liter_disetujui' => $alokasi->liter_disetujui,
                'status' => StatusJadwal::Terjadwal->value, 'keterangan' => $keterangan, 'dibuat_oleh' => $user->id,
            ]);
            AuditService::catat('jadwal.dibuat', $j, null, ['pengajuan' => $p->nomor_pengajuan, 'waktu' => $waktu->toDateTimeString()], $j->nomor_jadwal, $user);

            return $j;
        });
    }

    public function ubah(JadwalPembelian $jadwal, Carbon|string $tanggalWaktu, ?string $keterangan, User $user): JadwalPembelian
    {
        Otorisasi::pastikan($user, 'jadwal.manage');
        $this->pastikanAktif($jadwal);
        $lama = $jadwal->tanggal_waktu->toDateTimeString();
        $jadwal->forceFill(['tanggal_waktu' => Carbon::parse($tanggalWaktu), 'keterangan' => $keterangan, 'status' => StatusJadwal::Terjadwal->value])->save();
        AuditService::catat('jadwal.diubah', $jadwal, ['waktu' => $lama], ['waktu' => $jadwal->tanggal_waktu->toDateTimeString()], $jadwal->nomor_jadwal, $user);

        return $jadwal;
    }

    public function tunda(JadwalPembelian $jadwal, string $alasan, User $user): JadwalPembelian
    {
        Otorisasi::pastikan($user, 'jadwal.manage');
        $this->pastikanAktif($jadwal);
        $jadwal->forceFill(['status' => StatusJadwal::Ditunda->value, 'keterangan' => trim($alasan) !== '' ? trim($alasan) : $jadwal->keterangan])->save();
        AuditService::catat('jadwal.ditunda', $jadwal, null, ['alasan' => $alasan], $jadwal->nomor_jadwal, $user);

        return $jadwal;
    }

    public function tandaiSelesai(JadwalPembelian $jadwal, User $user): JadwalPembelian
    {
        Otorisasi::pastikan($user, 'jadwal.manage');
        $this->pastikanAktif($jadwal);
        $jadwal->forceFill(['status' => StatusJadwal::Selesai->value])->save();
        AuditService::catat('jadwal.selesai', $jadwal, null, null, $jadwal->nomor_jadwal, $user);

        return $jadwal;
    }

    private function pastikanAktif(JadwalPembelian $jadwal): void
    {
        if (! in_array($jadwal->status, [StatusJadwal::Terjadwal, StatusJadwal::Ditunda], true)) {
            throw new BisnisException('Jadwal ini sudah selesai atau dibatalkan.');
        }
    }
}
