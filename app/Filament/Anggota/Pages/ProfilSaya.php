<?php

namespace App\Filament\Anggota\Pages;

use App\Services\AuditService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

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
        return (bool) auth()->user()?->hasRole('anggota');
    }

    protected function getViewData(): array
    {
        return ['anggota' => auth()->user()->anggota, 'user' => auth()->user()];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ubahPassword')->label('Ubah kata sandi')->icon('heroicon-o-key')
                ->schema([
                    TextInput::make('lama')->label('Kata sandi saat ini')->password()->revealable()->required(),
                    TextInput::make('baru')->label('Kata sandi baru')->password()->revealable()->required()->minLength(8)->different('lama'),
                    TextInput::make('baru_konfirmasi')->label('Ulangi kata sandi baru')->password()->revealable()->required()->same('baru'),
                ])
                ->action(function (array $data) {
                    $user = auth()->user();
                    if (! Hash::check($data['lama'], $user->password)) {
                        Notification::make()->title('Kata sandi saat ini tidak sesuai')->danger()->send();

                        return;
                    }
                    $user->forceFill(['password' => $data['baru']])->save();
                    AuditService::catat('auth.password_diubah', $user->anggota, null, null, 'Anggota mengubah kata sandi', $user);
                    Notification::make()->title('Kata sandi berhasil diubah')->success()->send();
                }),
        ];
    }
}
