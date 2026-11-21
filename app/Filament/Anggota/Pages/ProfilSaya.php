<?php

namespace App\Filament\Anggota\Pages;

use App\Services\AuditService;
use App\Support\Otorisasi;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProfilSaya extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Profil Saya';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Profil Saya';
    protected static ?string $slug = 'profil-saya';

    protected string $view = 'filament.pages.profil-saya';

    public static function canAccess(): bool
    {
        return (bool) Otorisasi::user()?->hasRole('anggota');
    }

    protected function getViewData(): array
    {
        $user = Otorisasi::userWajib();

        return ['anggota' => $user->anggota, 'user' => $user];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ubahPassword')->label('Ubah kata sandi')->icon('heroicon-o-key')
                ->schema([
                    TextInput::make('lama')->label('Kata sandi saat ini')->password()->revealable()->required()->rule('current_password'),
                    TextInput::make('baru')->label('Kata sandi baru')->password()->revealable()->required()->minLength(8)->different('lama'),
                    TextInput::make('baru_konfirmasi')->label('Ulangi kata sandi baru')->password()->revealable()->required()->same('baru'),
                ])
                ->action(function (array $data) {
                    $user = Otorisasi::userWajib();
                    $user->forceFill(['password' => $data['baru']])->save();
                    session()->put('password_hash_web', $user->getAuthPassword());
                    DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
                    AuditService::catat('auth.password_diubah', $user->anggota, null, null, 'Anggota mengubah kata sandi', $user);
                    Notification::make()->title('Kata sandi berhasil diubah')->success()->send();
                }),
        ];
    }
}
