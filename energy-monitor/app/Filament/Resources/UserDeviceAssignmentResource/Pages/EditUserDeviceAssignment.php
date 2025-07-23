<?php

namespace App\Filament\Resources\UserDeviceAssignmentResource\Pages;

use App\Filament\Resources\UserDeviceAssignmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserDeviceAssignment extends EditRecord
{
    protected static string $resource = UserDeviceAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
