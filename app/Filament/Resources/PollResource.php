<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\PollResource\Pages;
use App\Models\Poll;
use App\Models\Story;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Moderation › Polls: approve readers' polls (pending → live), reject, close; create editorial polls. */
class PollResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['editor', 'chief', 'admin'];

    protected static ?string $model = Poll::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Moderation';

    public static function getNavigationBadge(): ?string
    {
        $n = Poll::where('status', 'pending')->count();

        return $n ? (string) $n : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('question')->required()->maxLength(150)->columnSpanFull(),
            Forms\Components\Select::make('story_id')->label('Linked story')->searchable()
                ->options(fn () => Story::published()->with('versions')->latest('published_at')->limit(50)->get()
                    ->mapWithKeys(fn ($s) => [$s->id => $s->version('en')?->headline])),
            Forms\Components\Select::make('status')->options(['pending' => 'Pending', 'live' => 'Live', 'closed' => 'Closed', 'rejected' => 'Rejected'])
                ->required()->default('live'),
            Forms\Components\DateTimePicker::make('ends_at')->seconds(false)->default(now()->addDay()),
            Forms\Components\Repeater::make('options')->relationship()->orderColumn('sort_order')
                ->simple(Forms\Components\TextInput::make('label')->required()->maxLength(60))
                ->minItems(2)->maxItems(4)->defaultItems(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['creator', 'options']))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('question')->wrap()->limit(100)->weight('bold'),
                Tables\Columns\TextColumn::make('options.label')->label('Options')->listWithLineBreaks()->limitList(4),
                Tables\Columns\TextColumn::make('creator.name')->label('By')->placeholder('Editorial'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'pending' => 'warning', 'live' => 'success', 'rejected' => 'danger', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('votes')->label('Votes')->getStateUsing(fn (Poll $r) => $r->totalVotes())->alignCenter(),
                Tables\Columns\TextColumn::make('ends_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'live' => 'Live', 'closed' => 'Closed', 'rejected' => 'Rejected']),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (Poll $r) => $r->status === 'pending')
                    ->action(function (Poll $r) {
                        // Keep the reader's chosen run length, counted from approval.
                        $days = max(1, (int) round($r->created_at->diffInHours($r->ends_at ?? $r->created_at->addDay()) / 24));
                        $r->update(['status' => 'live', 'ends_at' => now()->addDays($days)]);
                        Notification::make()->title('Poll is live')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Poll $r) => $r->status === 'pending')
                    ->requiresConfirmation()->action(fn (Poll $r) => $r->update(['status' => 'rejected'])),
                Tables\Actions\Action::make('close')->icon('heroicon-o-lock-closed')->color('gray')
                    ->visible(fn (Poll $r) => $r->status === 'live')
                    ->requiresConfirmation()->action(fn (Poll $r) => $r->update(['status' => 'closed', 'ends_at' => now()])),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePolls::route('/')];
    }
}
