<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\PageResource\Pages;
use App\Models\Page;
use App\Models\Story;
use App\Support\PageCache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Followable pages (topics, cities, shows): full CRUD. */
class PageResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['admin'];

    protected static ?string $model = Page::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Site';

    public static function form(Form $form): Form
    {
        $disk = config('filesystems.media');

        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(80)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Forms\Set $set, ?Page $record) => $record ? null : $set('slug', Str::slug($state))),
            Forms\Components\TextInput::make('name_hi')->label('Name (Hindi)')->maxLength(80),
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash(),
            Forms\Components\Select::make('type')->options(['topic' => 'Topic', 'city' => 'City', 'show' => 'Show'])->required()->default('topic'),
            Forms\Components\Select::make('category_id')->relationship('category', 'name_en')->preload(),
            Forms\Components\Select::make('city_id')->relationship('city', 'name_en')->preload(),
            Forms\Components\TextInput::make('desk_label')->placeholder('SPORTS DESK'),
            Forms\Components\Toggle::make('verified'),
            Forms\Components\Textarea::make('description')->rows(2)->columnSpanFull(),
            Forms\Components\FileUpload::make('avatar_path')->label('Avatar')->image()->disk($disk)->directory('pages')->avatar(),
            Forms\Components\FileUpload::make('cover_path')->label('Cover (wide)')->image()->disk($disk)->directory('pages'),
            Forms\Components\Section::make('Result strip (black bar on the page; leave empty to hide)')->columns(3)->collapsed()->schema([
                Forms\Components\TextInput::make('pinned_label')->placeholder('RESULT'),
                Forms\Components\TextInput::make('pinned_text')->placeholder('IND 312 & 148/3 beat NZ…')->columnSpan(2),
                Forms\Components\Select::make('pinned_story_id')->label('Match report story')->searchable()->columnSpanFull()
                    ->options(fn () => Story::published()->with('versions')->latest('published_at')->limit(50)->get()
                        ->mapWithKeys(fn ($s) => [$s->id => $s->version('en')?->headline])),
            ]),
            Forms\Components\Repeater::make('upcoming')->label('Upcoming (date tiles)')->collapsed()->columnSpanFull()
                ->schema([
                    Forms\Components\DatePicker::make('date')->required(),
                    Forms\Components\TextInput::make('title')->required(),
                    Forms\Components\TextInput::make('subtitle'),
                ])->columns(3),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['followers', 'stories']))
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_path')->label('')->disk(config('filesystems.media'))->square()->size(40),
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('desk_label')->color('gray'),
                Tables\Columns\TextColumn::make('followers_count')->label('Followers')->alignCenter()->sortable(),
                Tables\Columns\TextColumn::make('stories_count')->label('Stories')->alignCenter(),
                Tables\Columns\IconColumn::make('verified')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(fn () => PageCache::flush()),
                Tables\Actions\Action::make('open')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Page $r) => route('page.show', $r), shouldOpenInNewTab: true),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManagePages::route('/')];
    }
}
