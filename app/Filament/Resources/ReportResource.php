<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\ReportResource\Pages;
use App\Models\Comment;
use App\Models\Poll;
use App\Models\Report;
use App\Models\Story;
use App\Services\Newsroom\StoryPublisher;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Moderation › Reports from readers on stories, comments and polls. */
class ReportResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['editor', 'chief', 'admin'];

    protected static ?string $model = Report::class;
    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationGroup = 'Moderation';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Report::where('status', 'open')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('reporter'))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('target_type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('content')->label('Reported content')->wrap()
                    ->getStateUsing(fn (Report $r) => self::preview($r)),
                Tables\Columns\TextColumn::make('reason')->badge()->color('warning'),
                Tables\Columns\TextColumn::make('reporter.name')->label('Reported by')->placeholder('Reader'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'open' => 'danger', 'actioned' => 'success', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['open' => 'Open', 'actioned' => 'Actioned', 'dismissed' => 'Dismissed'])->default('open'),
                Tables\Filters\SelectFilter::make('target_type')->label('Type')->options(['story' => 'Story', 'comment' => 'Comment', 'poll' => 'Poll']),
            ])
            ->actions([
                Tables\Actions\Action::make('remove')->label('Take down')->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn (Report $r) => $r->status === 'open')
                    ->requiresConfirmation()
                    ->modalDescription('Comments are hidden, stories unpublished and polls rejected. All open reports on it are closed.')
                    ->action(function (Report $r) {
                        $target = $r->target();
                        match (true) {
                            $target instanceof Comment => CommentResource::setStatus($target, 'hidden'),
                            $target instanceof Story => app(StoryPublisher::class)->unpublish($target),
                            $target instanceof Poll => $target->update(['status' => 'rejected']),
                            default => null,
                        };
                        Report::where('target_type', $r->target_type)->where('target_id', $r->target_id)->where('status', 'open')->update(['status' => 'actioned']);
                        Notification::make()->title('Taken down')->success()->send();
                    }),
                Tables\Actions\Action::make('dismiss')->icon('heroicon-o-x-mark')->color('gray')
                    ->visible(fn (Report $r) => $r->status === 'open')
                    ->action(fn (Report $r) => $r->update(['status' => 'dismissed'])),
                Tables\Actions\Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Report $r) => self::link($r), shouldOpenInNewTab: true)->visible(fn (Report $r) => (bool) self::link($r)),
            ]);
    }

    private static function preview(Report $r): string
    {
        $t = $r->target();

        return match (true) {
            $t instanceof Comment => '“'.Str::limit($t->body, 140).'”',
            $t instanceof Story => Str::limit((string) $t->version('en')?->headline, 140),
            $t instanceof Poll => 'Poll: '.Str::limit($t->question, 120),
            default => '(deleted)',
        };
    }

    private static function link(Report $r): ?string
    {
        $t = $r->target();

        return match (true) {
            $t instanceof Comment => $t->story ? route('story.show', $t->story).'#comments-title' : null,
            $t instanceof Story => route('story.show', $t),
            default => null,
        };
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageReports::route('/')];
    }
}
