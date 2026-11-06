<?php

namespace App\Filament\Resources\BriefingResource\Pages;

use App\Filament\Resources\BriefingResource;
use App\Jobs\BuildBriefingAudio;
use App\Models\Briefing;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBriefings extends ManageRecords
{
    protected static string $resource = BriefingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New briefing')
                ->mutateFormDataUsing(fn (array $data) => BriefingResource::normaliseIds($data))
                ->after(fn (Briefing $record) => BuildBriefingAudio::dispatch($record->id)),
        ];
    }
}
