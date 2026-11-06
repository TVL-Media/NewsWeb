<?php

namespace App\Filament\Resources\PollResource\Pages;

use App\Filament\Resources\PollResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePolls extends ManageRecords
{
    protected static string $resource = PollResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('New editorial poll')];
    }
}
