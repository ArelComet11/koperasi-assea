<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Filament\Concerns\DenganIzin;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

/** Akun staf (admin & finance). Akun anggota dikelola lewat menu Anggota. */
class UserResource extends Resource
{
    use DenganIzin;

    protected static ?string $model = User::class;
    protected static ?string $slug = 'users';
    protected static string $izinLihat = 'user.manage';
    protected static ?string $izinBuat = 'user.manage';
    protected static ?string $izinUbah = 'user.manage';

    protected static ?string $modelLabel = 'Pengguna';
    protected static ?string $pluralModelLabel = 'Pengguna (Admin & Finance)';
    protected static ?string $navigationLabel = 'Pengguna';
    protected static string|\UnitEnum|null $navigationGroup = 'Sistem';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereDoesntHave('roles', fn ($q) => $q->where('name', 'anggota'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun')->schema([
                TextInput::make('name')->label('Nama')->required()->maxLength(150),
                TextInput::make('username')->required()->maxLength(50)->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('email')->email()->maxLength(150)->unique(ignoreRecord: true),
                TextInput::make('password')->label('Kata sandi')->password()->revealable()->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))->helperText('Saat mengubah, kosongkan bila tidak ingin mengganti.'),
                Toggle::make('is_active')->label('Akun aktif')->default(true)
                    ->disabled(fn (?User $record) => $record !== null && $record->id === Auth::id())
                    ->helperText(fn (?User $record) => $record !== null && $record->id === Auth::id() ? 'Anda tidak dapat menonaktifkan akun sendiri.' : null),
            ])->columns(2),
            Section::make('Peran dan izin')->description('Finance tidak dapat menyetujui pengajuan atau mengubah harga kecuali diberi izin tambahan di bawah.')->schema([
                Select::make('roles')->label('Peran')->relationship('roles', 'name', fn (Builder $query) => $query->whereIn('name', ['admin', 'finance']))->multiple()->preload()->required()
                    ->rule(fn (?User $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        if ($record === null || $record->id !== Auth::id() || ! $record->hasRole('admin')) {
                            return;
                        }
                        $adminRoleId = Role::query()->where('name', 'admin')->value('id');
                        $terpilih = array_map('intval', is_array($value) ? $value : []);
                        if ($adminRoleId && ! in_array((int) $adminRoleId, $terpilih, true)) {
                            $fail('Anda tidak dapat mencabut peran admin dari akun sendiri.');
                        }
                    }),
                CheckboxList::make('permissions')->label('Izin tambahan')
                    ->relationship('permissions', 'name', fn (Builder $query) => $query->where('name', '!=', 'pengajuan.create'))
                    ->columns(3)->searchable()->bulkToggleable(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('username')->searchable(),
                TextColumn::make('roles.name')->label('Peran')->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_login_at')->label('Login terakhir')->dateTime('d/m/Y H:i')->placeholder('-'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
