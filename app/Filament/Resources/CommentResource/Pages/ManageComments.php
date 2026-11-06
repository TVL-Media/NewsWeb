<?php

namespace App\Filament\Resources\CommentResource\Pages;

use App\Filament\Resources\CommentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageComments extends ManageRecords
{
    protected static string $resource = CommentResource::class;
}
