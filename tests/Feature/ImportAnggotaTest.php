<?php

namespace Tests\Feature;

use App\Exports\TemplateAnggotaExport;
use App\Filament\Admin\Resources\AnggotaResource\Pages\ListAnggota;
use App\Models\Anggota;
use App\Models\User;
use App\Services\AnggotaImportService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class ImportAnggotaTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_unduh_template_excel_hanya_untuk_admin(): void
    {
        $admin = $this->admin();
        $finance = $this->finance();
        [, , , $anggotaUser] = $this->buatAnggota('IMP-01');

        $this->actingAs($finance)->get(route('admin.anggota.template-import'))->assertForbidden();
        $this->actingAs($anggotaUser)->get(route('admin.anggota.template-import'))->assertForbidden();

        $res = $this->actingAs($admin)->get(route('admin.anggota.template-import'));
        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));
    }

    public function test_impor_excel_membuat_banyak_anggota_otomatis_dan_melaporkan_baris_gagal(): void
    {
        $admin = $this->admin();

        $rows = [
            ['kode_anggota', 'nama_lengkap', 'username', 'password', 'telepon', 'email', 'tanggal_bergabung', 'alamat', 'keterangan'],
            ['ANG-2001', 'Ahmad Nelayan', 'ahmad_nelayan', 'Rahasia123', '0811111111', 'ahmad@test.id', '2026-11-01', 'Jl. Pantai 1', 'Aktif'],
            ['', 'Rahmat Laut', 'rahmat_laut', '', '0822222222', '', '15/11/2026', 'Jl. Pantai 2', 'Kode & sandi otomatis'],
            ['', '', '', '', '', '', '', '', ''], // baris kosong harus dilewati
            ['ANG-2003', 'Duplikat User', 'ahmad_nelayan', 'Rahasia123', '', '', '', '', 'Harus gagal duplikat'],
            ['ANG-2004', 'Email Salah', 'email_salah', 'Rahasia123', '', 'bukan-email', '', '', 'Harus gagal email'],
        ];

        $tmpPath = storage_path('framework/testing/test-import-anggota.xlsx');
        @mkdir(dirname($tmpPath), 0777, true);
        $raw = Excel::raw(new class($rows) implements FromArray {
            public function __construct(private array $rows) {}
            public function array(): array { return $this->rows; }
        }, \Maatwebsite\Excel\Excel::XLSX);
        file_put_contents($tmpPath, $raw);

        try {
            $hasil = app(AnggotaImportService::class)->impor($tmpPath, $admin, 'Bawaan1234');
        } finally {
            @unlink($tmpPath);
        }

        $this->assertSame(2, $hasil['berhasil']);
        $this->assertCount(2, $hasil['gagal']);
        $this->assertSame(5, $hasil['gagal'][0]['baris']);
        $this->assertSame(6, $hasil['gagal'][1]['baris']);

        $ahmad = Anggota::query()->where('kode_anggota', 'ANG-2001')->firstOrFail();
        $this->assertSame('Ahmad Nelayan', $ahmad->nama_lengkap);
        $this->assertTrue($ahmad->user->hasRole('anggota'));
        $this->assertTrue(Hash::check('Rahasia123', $ahmad->user->password));

        $rahmatUser = User::query()->where('username', 'rahmat_laut')->firstOrFail();
        $this->assertTrue(Hash::check('Bawaan1234', $rahmatUser->password));
        $this->assertStringStartsWith('ANG-', $rahmatUser->anggota->kode_anggota);
        $this->assertSame('2026-11-15', $rahmatUser->anggota->tanggal_bergabung->toDateString());
    }

    public function test_tombol_import_dan_template_tampil_di_list_anggota_untuk_admin(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ListAnggota::class)
            ->assertSuccessful()
            ->assertActionVisible('importExcel')
            ->assertActionVisible('unduhTemplate');
    }
}
