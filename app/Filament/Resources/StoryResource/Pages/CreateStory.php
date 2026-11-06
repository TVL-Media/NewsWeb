<?php

namespace App\Filament\Resources\StoryResource\Pages;

use App\Filament\Resources\StoryResource;
use App\Models\Story;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Editor-written story. Saved as a draft; use Publish to put it live. */
class CreateStory extends CreateRecord
{
    protected static string $resource = StoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $base = Str::limit(Str::slug($data['en']['headline'] ?? 'story'), 90, '') ?: 'story';
            $slug = $base;
            for ($i = 2; Story::where('slug', $slug)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }

            $story = Story::create([
                ...collect($data)->except(['en', 'hi'])->all(),
                'slug' => $slug,
                'status' => in_array($data['status'] ?? 'draft', ['draft', 'pending_approval'], true) ? $data['status'] : 'draft',
                'publish_mode' => 'manual',
            ]);
            StoryResource::saveVersions($story, $data);

            return $story;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
