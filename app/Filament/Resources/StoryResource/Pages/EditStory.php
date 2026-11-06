<?php

namespace App\Filament\Resources\StoryResource\Pages;

use App\Filament\Resources\StoryResource;
use App\Jobs\GenerateAudio;
use App\Models\Story;
use App\Services\Newsroom\StoryPublisher;
use App\Support\PageCache;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditStory extends EditRecord
{
    protected static string $resource = StoryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...StoryResource::versionState($this->record->load('versions'))];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        DB::transaction(function () use ($record, $data) {
            $record->update(collect($data)->except(['en', 'hi'])->all());
            StoryResource::saveVersions($record, $data);
        });

        // Live stories: refresh the audio and the cached pages after an edit.
        if (in_array($record->status, ['published', 'corrected'], true)) {
            GenerateAudio::dispatch($record->id);
            PageCache::flush();
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            // "Edit then publish" from the approval queue lands here.
            Actions\Action::make('saveAndPublish')->label('Save and publish')->icon('heroicon-o-arrow-up-circle')->color('success')
                ->visible(fn () => in_array($this->record->status, ['draft', 'pending_approval', 'rejected', 'removed'], true))
                ->action(function () {
                    $this->save(shouldRedirect: false);
                    app(StoryPublisher::class)->approve($this->record->refresh(), auth('admin')->user(), edited: true);
                    Notification::make()->title('Published')->success()->send();
                    $this->redirect(StoryResource::getUrl('index'));
                }),
            Actions\Action::make('unpublish')->color('danger')->icon('heroicon-o-eye-slash')->requiresConfirmation()
                ->visible(fn () => in_array($this->record->status, ['published', 'corrected'], true))
                ->action(function () {
                    app(StoryPublisher::class)->unpublish($this->record);
                    Notification::make()->title('Unpublished')->success()->send();
                    $this->refreshFormData(['status']);
                }),
            Actions\Action::make('open')->label('Open on site')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->visible(fn () => in_array($this->record->status, ['published', 'corrected'], true))
                ->url(fn () => route('story.show', $this->record), shouldOpenInNewTab: true),
        ];
    }
}
