<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PengurusResource\Pages\ManagePengurus;
use App\Models\Pengurus;
use Database\Seeders\PengurusSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MembuatData;
use Tests\TestCase;

class StrukturOrganisasiTest extends TestCase
{
    use MembuatData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDasar();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_seeder_pengurus_membuat_4_level_dan_idempoten(): void
    {
        $this->seed(PengurusSeeder::class);
        $this->seed(PengurusSeeder::class);

        $this->assertSame(11, Pengurus::query()->count());
        $struktur = Pengurus::strukturTerurut();
        $this->assertCount(1, $struktur->get(1));
        $this->assertCount(2, $struktur->get(2));
        $this->assertCount(3, $struktur->get(3));
        $this->assertCount(5, $struktur->get(4));
    }

    public function test_struktur_organisasi_tampil_dinamis_di_profil_dan_beranda(): void
    {
        $this->seed(PengurusSeeder::class);
        Pengurus::create([
            'nama' => 'Pengurus Nonaktif Uji',
            'jabatan' => 'Seksi Tersembunyi',
            'level' => 4,
            'tema_pangkat' => 'otomatis',
            'urutan' => 99,
            'tampil' => false,
        ]);

        foreach (['/', '/profil'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('STRUKTUR ORGANISASI')
                ->assertSee('H. Radian Salman, S.E., M.M.')
                ->assertSee('Bendahara Koperasi')
                ->assertSee('Manajer Usaha &amp; BBM', false)
                ->assertSee('Seksi Penyaluran')
                ->assertDontSee('Pengurus Nonaktif Uji');
        }
    }

    public function test_admin_dapat_mengelola_pengurus_dengan_level_dan_tema_pangkat(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ManagePengurus::class)
            ->assertSuccessful()
            ->callAction('create', data: [
                'nama' => 'Dr. Andi Pratama, M.M.',
                'jabatan' => 'Ketua Koperasi',
                'level' => 1,
                'tema_pangkat' => 'emas_eksklusif',
                'urutan' => 1,
                'foto_transparan' => true,
                'tampil' => true,
            ])
            ->assertHasNoActionErrors();

        $p = Pengurus::query()->where('nama', 'Dr. Andi Pratama, M.M.')->firstOrFail();
        $this->assertSame(1, $p->level);
        $this->assertSame('emas_eksklusif', $p->tema_pangkat);
        $this->assertTrue($p->foto_transparan);
        $this->assertTrue($p->isFotoTransparan());
    }
}
