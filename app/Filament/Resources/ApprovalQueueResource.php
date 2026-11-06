<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\ApprovalQueueResource\Pages;
use App\Models\Story;
use App\Services\Newsroom\StoryPublisher;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Stories that failed an automatic check (sensitive, low confidence, copy, facts) and need an editor. */
class ApprovalQueueResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['editor', 'chief', 'admin'];

    protected static ?string $model = Story::class;
    protected static ?string $slug = 'approval-queues';
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationGroup = 'Newsroom';
    protected static ?string $navigationLabel = 'Approval queue';
    protected static ?string $modelLabel = 'story for approval';
    protected static ?string $pluralModelLabel = 'Approval queue';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('status', 'pending_approval')->with(['versions', 'category', 'aiCheck']);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Story::where('status', 'pending_approval')->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('Nothing waiting')
            ->emptyStateDescription('Every new draft passed the checks or has been reviewed.')
            ->columns([
                Tables\Columns\TextColumn::make('headline')->wrap()->limit(100)
                    ->getStateUsing(fn (Story $r) => $r->version('en')?->headline),
                Tables\Columns\TextColumn::make('category.name_en')->label('Category')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('aiCheck.risk_level')->label('Risk')->badge()
                    ->color(fn ($state) => $state === 'sensitive' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('aiCheck.confidence')->label('Confidence')->numeric(2),
                Tables\Columns\TextColumn::make('aiCheck.copy_score')->label('Copy')->numeric(2),
                Tables\Columns\TextColumn::make('why')->label('Why it is here')->wrap()->limit(140)
                    ->getStateUsing(fn (Story $r) => implode(' · ', $r->aiCheck?->reasons ?? [])),
                Tables\Columns\TextColumn::make('updated_at')->label('Waiting')->since(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Review'),
                self::approveTableAction(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $version = fn (string $lang) => Infolists\Components\Section::make($lang === 'en' ? 'Draft · English' : 'Draft · हिंदी')
            ->collapsible()->collapsed($lang === 'hi')
            ->schema([
                Infolists\Components\TextEntry::make("{$lang}_headline")->label('Headline')->weight('bold')->size('lg')
                    ->getStateUsing(fn (Story $r) => $r->version($lang)?->headline),
                Infolists\Components\TextEntry::make("{$lang}_card")->label('Card text')
                    ->getStateUsing(fn (Story $r) => $r->version($lang)?->card_text),
                Infolists\Components\TextEntry::make("{$lang}_points")->label('Key points')->listWithLineBreaks()->bulleted()
                    ->getStateUsing(fn (Story $r) => $r->version($lang)?->key_points),
                Infolists\Components\TextEntry::make("{$lang}_why")->label('Why it matters')
                    ->getStateUsing(fn (Story $r) => $r->version($lang)?->why_it_matters),
                Infolists\Components\TextEntry::make("{$lang}_full")->label('Full story')->prose()
                    ->getStateUsing(fn (Story $r) => nl2br(e((string) $r->version($lang)?->full_text)))->html(),
            ]);

        return $infolist->schema([
            Infolists\Components\Grid::make(['default' => 1, 'lg' => 2])->schema([
                // Left: AI checks + draft
                Infolists\Components\Group::make([
                    Infolists\Components\Section::make('AI checks')->schema([
                        Infolists\Components\Grid::make(4)->schema([
                            Infolists\Components\TextEntry::make('aiCheck.risk_level')->label('Risk')->badge()
                                ->color(fn ($state) => $state === 'sensitive' ? 'danger' : 'success'),
                            Infolists\Components\TextEntry::make('aiCheck.confidence')->label('Confidence')->numeric(2),
                            Infolists\Components\TextEntry::make('aiCheck.copy_score')->label('Copy score')->numeric(2),
                            Infolists\Components\TextEntry::make('importance'),
                        ]),
                        Infolists\Components\TextEntry::make('aiCheck.reasons')->label('Why it needs an editor')->listWithLineBreaks()->bulleted()->color('danger'),
                        Infolists\Components\TextEntry::make('aiCheck.sensitive_reasons')->label('Sensitive because')->listWithLineBreaks()->bulleted()
                            ->placeholder('Not sensitive'),
                        Infolists\Components\TextEntry::make('mismatches')->label('Facts not found in the sources')->listWithLineBreaks()->bulleted()
                            ->getStateUsing(fn (Story $r) => collect($r->aiCheck?->fact_mismatches ?? [])->map(fn ($m) => ucfirst($m['type']).': '.$m['value'])->all())
                            ->placeholder('All names, numbers and dates were found'),
                    ]),
                    $version('en'),
                    $version('hi'),
                ]),
                // Right: the sources, side by side
                Infolists\Components\Section::make('Sources')->schema([
                    Infolists\Components\RepeatableEntry::make('cluster.items')->label('')->schema([
                        Infolists\Components\TextEntry::make('feedSource.name')->label('Outlet')->badge(),
                        Infolists\Components\TextEntry::make('title')->weight('bold'),
                        Infolists\Components\TextEntry::make('published_at')->dateTime(),
                        Infolists\Components\TextEntry::make('url')->url(fn ($state) => $state, shouldOpenInNewTab: true)->limit(60)->color('primary'),
                        Infolists\Components\TextEntry::make('body')->label('Text')->prose()
                            ->formatStateUsing(fn ($state) => nl2br(e(\Illuminate\Support\Str::limit(strip_tags((string) $state), 3000))))->html(),
                    ])->placeholder('No source items (written by an editor).'),
                ]),
            ]),
        ]);
    }

    public static function approveTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('approve')->icon('heroicon-o-check-circle')->color('success')
            ->requiresConfirmation()->modalHeading('Approve and publish?')
            ->action(function (Story $r) {
                app(StoryPublisher::class)->approve($r, auth('admin')->user());
                Notification::make()->title('Approved and published')->success()->send();
            });
    }

    public static function rejectForm(): array
    {
        return [Forms\Components\Textarea::make('reason')->label('Reason (kept in the approvals log)')->required()->rows(3)];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApprovalQueues::route('/'),
            'view' => Pages\ViewApprovalQueue::route('/{record}'),
        ];
    }
}
