<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\BriefingResource\Pages;
use App\Jobs\BuildBriefingAudio;
use App\Models\Briefing;
use App\Models\Story;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** View and override the Morning 5. Saving rebuilds the joined audio; PickMorning5 never overwrites an existing day. */
class BriefingResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['editor', 'chief', 'admin'];

    protected static ?string $model = Briefing::class;
    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    protected static ?string $navigationGroup = 'Daily';
    protected static ?string $navigationLabel = 'Morning 5';
    protected static ?string $modelLabel = 'Morning 5 briefing';

    public static function form(Form $form): Form
    {
        $options = fn () => Story::published()->with('versions')->where('published_at', '>=', now()->subDays(3))
            ->latest('published_at')->get()->mapWithKeys(fn ($s) => [$s->id => $s->version('en')?->headline]);

        return $form->schema([
            Forms\Components\DatePicker::make('date')->required()->unique(ignoreRecord: true)->default(now(config('app.timezone'))->toDateString()),
            Forms\Components\Repeater::make('story_ids')->label('Stories (in playing order)')
                ->simple(Forms\Components\Select::make('id')->options($options)->searchable()->required()->distinct())
                ->minItems(5)->maxItems(5)->defaultItems(5)->reorderable()->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')->date('D, j M Y')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('stories')->label('Stories')->wrap()->listWithLineBreaks()->limitList(5)
                    ->getStateUsing(fn (Briefing $r) => $r->stories()->map(fn ($s) => $s->version('en')?->headline)->all()),
                Tables\Columns\TextColumn::make('audio_seconds')->label('Audio')
                    ->formatStateUsing(fn ($state) => $state ? intdiv($state, 60).':'.str_pad((string) ($state % 60), 2, '0', STR_PAD_LEFT) : '—'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => self::normaliseIds($data))
                    ->after(fn (Briefing $record) => BuildBriefingAudio::dispatch($record->id)),
                Tables\Actions\Action::make('audio')->label('Rebuild audio')->icon('heroicon-o-speaker-wave')
                    ->action(function (Briefing $r) {
                        BuildBriefingAudio::dispatch($r->id);
                        Notification::make()->title('Rebuilding the Morning 5 audio')->success()->send();
                    }),
            ]);
    }

    /** Repeater "simple" stores [{id}…] in some versions; normalise to a flat list of ints. */
    public static function normaliseIds(array $data): array
    {
        $data['story_ids'] = array_values(array_map(fn ($v) => (int) (is_array($v) ? ($v['id'] ?? 0) : $v), $data['story_ids'] ?? []));

        return $data;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageBriefings::route('/')];
    }
}
