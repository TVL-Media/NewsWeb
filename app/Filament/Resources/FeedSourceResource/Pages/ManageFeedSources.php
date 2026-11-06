<?php

namespace App\Filament\Resources\FeedSourceResource\Pages;

use App\Filament\Resources\FeedSourceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFeedSources extends ManageRecords
{
    protected static string $resource = FeedSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Add feed')];
    }
}
