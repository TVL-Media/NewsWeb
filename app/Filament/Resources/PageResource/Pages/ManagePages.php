<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Support\PageCache;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePages extends ManageRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->after(fn () => PageCache::flush())];
    }
}
