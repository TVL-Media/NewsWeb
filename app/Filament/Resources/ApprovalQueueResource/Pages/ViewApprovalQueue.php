<?php

namespace App\Filament\Resources\ApprovalQueueResource\Pages;

use App\Filament\Resources\ApprovalQueueResource;
use App\Filament\Resources\StoryResource;
use App\Services\Newsroom\StoryPublisher;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Review screen: AI checks and draft on the left, source reports on the right. */
class ViewApprovalQueue extends ViewRecord
{
    protected static string $resource = ApprovalQueueResource::class;

    public function getTitle(): string
    {
        return 'Review: '.\Illuminate\Support\Str::limit($this->record->version('en')?->headline ?? 'draft', 80);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')->label('Approve')->icon('heroicon-o-check-circle')->color('success')
                ->requiresConfirmation()->modalHeading('Approve and publish?')
                ->action(function () {
                    app(StoryPublisher::class)->approve($this->record, auth('admin')->user());
                    Notification::make()->title('Approved and published')->success()->send();
                    $this->redirect(ApprovalQueueResource::getUrl('index'));
                }),
            Actions\Action::make('edit')->label('Edit then publish')->icon('heroicon-o-pencil-square')->color('gray')
                ->url(fn () => StoryResource::getUrl('edit', ['record' => $this->record])),
            Actions\Action::make('reject')->label('Reject')->icon('heroicon-o-x-circle')->color('danger')
                ->form(ApprovalQueueResource::rejectForm())
                ->action(function (array $data) {
                    app(StoryPublisher::class)->reject($this->record, auth('admin')->user(), $data['reason']);
                    Notification::make()->title('Rejected')->success()->send();
                    $this->redirect(ApprovalQueueResource::getUrl('index'));
                }),
        ];
    }
}
