<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<string, mixed> */
    private array $sebelum = [];

    protected function beforeSave(): void
    {
        $record = $this->getRecord();
        if ($record instanceof User) {
            $this->sebelum = [
                'is_active' => $record->is_active,
                'roles' => $record->roles()->pluck('name')->sort()->values()->all(),
                'permissions' => $record->permissions()->pluck('name')->sort()->values()->all(),
            ];
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->getRecord()->getKey() === Auth::id()) {
            $data['is_active'] = true;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $record = $this->getRecord();
        $sesudah = $record instanceof User ? [
            'is_active' => $record->is_active,
            'roles' => $record->roles()->pluck('name')->sort()->values()->all(),
            'permissions' => $record->permissions()->pluck('name')->sort()->values()->all(),
        ] : ['is_active' => $record->is_active];

        AuditService::catat('user.diubah', $record, $this->sebelum ?: null, $sesudah, $record->name);
    }
}
