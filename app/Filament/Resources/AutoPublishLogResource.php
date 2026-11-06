<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\AutoPublishLogResource\Pages;
use App\Models\AiCheck;
use App\Models\AutoPublishSetting;
use App\Services\Newsroom\StoryPublisher;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** What was published automatically, and why (every check result is recorded). */
class AutoPublishLogResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['editor', 'chief', 'admin'];

    protected static ?string $model = AiCheck::class;
    protected static ?string $slug = 'auto-publish-log';
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationGroup = 'Newsroom';
    protected static ?string $navigationLabel = 'Auto-publish log';
    protected static ?string $pluralModelLabel = 'Auto-publish log';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $settings = AutoPublishSetting::current();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['story.versions']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Checked')->since()->sortable(),
                Tables\Columns\TextColumn::make('headline')->wrap()->limit(90)
                    ->getStateUsing(fn (AiCheck $r) => $r->story?->version('en')?->headline),
                Tables\Columns\TextColumn::make('decision')->badge()->color(fn ($state) => $state === 'auto' ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state) => $state === 'auto' ? 'Auto-published' : 'Sent to editors'),
                Tables\Columns\TextColumn::make('risk_level')->label('Risk')->badge()->color(fn ($state) => $state === 'sensitive' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('confidence')->numeric(2)->sortable(),
                Tables\Columns\TextColumn::make('copy_score')->label('Copy')->numeric(2)->sortable(),
                Tables\Columns\TextColumn::make('why')->label('Why')->wrap()
                    ->getStateUsing(fn (AiCheck $r) => $r->decision === 'auto'
                        ? sprintf('All checks passed: low risk, facts matched, copy %.2f < %.2f, confidence %.2f ≥ %.2f, trusted source',
                            $r->copy_score, $settings->copy_threshold, $r->confidence, $settings->confidence_threshold)
                        : implode(' · ', $r->reasons ?? [])),
                Tables\Columns\TextColumn::make('story.status')->label('Now')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('decision')->options(['auto' => 'Auto-published', 'approval' => 'Sent to editors'])->default('auto'),
                Tables\Filters\SelectFilter::make('risk_level')->options(['low' => 'Low', 'sensitive' => 'Sensitive']),
            ])
            ->actions([
                Tables\Actions\Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (AiCheck $r) => in_array($r->story?->status, ['published', 'corrected'], true))
                    ->url(fn (AiCheck $r) => route('story.show', $r->story), shouldOpenInNewTab: true),
                Tables\Actions\Action::make('unpublish')->color('danger')->icon('heroicon-o-eye-slash')->requiresConfirmation()
                    ->visible(fn (AiCheck $r) => in_array($r->story?->status, ['published', 'corrected'], true))
                    ->action(function (AiCheck $r) {
                        app(StoryPublisher::class)->unpublish($r->story);
                        Notification::make()->title('Unpublished')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAutoPublishLog::route('/')];
    }
}
