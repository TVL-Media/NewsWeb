<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\FeedSourceResource\Pages;
use App\Jobs\FetchFeeds;
use App\Models\FeedSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Licensed agency feeds and publisher RSS. Only "trusted" sources can auto-publish. */
class FeedSourceResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['chief', 'admin'];

    protected static ?string $model = FeedSource::class;
    protected static ?string $navigationIcon = 'heroicon-o-rss';
    protected static ?string $navigationGroup = 'Newsroom';
    protected static ?string $navigationLabel = 'Feeds';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(120),
            Forms\Components\TextInput::make('url')->label('Feed URL (RSS, Atom or JSON Feed)')->url()->required()->columnSpanFull(),
            Forms\Components\Select::make('type')->options(['agency' => 'Licensed agency', 'rss' => 'Publisher RSS'])->required()->default('rss'),
            Forms\Components\Toggle::make('trusted')->helperText('Stories only from trusted sources may auto-publish.'),
            Forms\Components\Toggle::make('active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('url')->limit(50)->color('gray'),
                Tables\Columns\ToggleColumn::make('trusted'),
                Tables\Columns\ToggleColumn::make('active'),
                Tables\Columns\TextColumn::make('items_count')->label('Items')->alignCenter(),
                Tables\Columns\TextColumn::make('last_fetched_at')->since()->placeholder('Never'),
            ])
            ->actions([
                Tables\Actions\Action::make('fetch')->label('Fetch now')->icon('heroicon-o-arrow-path')
                    ->action(function (FeedSource $r) {
                        FetchFeeds::dispatch($r->id);
                        Notification::make()->title('Fetching '.$r->name)->body('New items go through clustering, AI drafts and checks.')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageFeedSources::route('/')];
    }
}
