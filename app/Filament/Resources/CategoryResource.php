<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use App\Support\PageCache;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Categories: chips, Explore tiles, onboarding topics. Full CRUD. */
class CategoryResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['admin'];

    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Site';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name_en')->label('Name (English)')->required()->maxLength(60)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Forms\Set $set, ?Category $record) => $record ? null : $set('slug', Str::slug($state))),
            Forms\Components\TextInput::make('name_hi')->label('Name (Hindi)')->required()->maxLength(60),
            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true)->alphaDash(),
            Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
            Forms\Components\Toggle::make('is_sensitive')->label('Sensitive (never auto-publish)')
                ->helperText('Also add it under Settings › Auto-publish settings.'),
            Forms\Components\FileUpload::make('image_path')->label('Tile photo')->image()
                ->disk(config('filesystems.media'))->directory('categories')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('stories'))
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('')->disk(config('filesystems.media'))->size(40),
                Tables\Columns\TextColumn::make('name_en')->label('Name')->weight('bold'),
                Tables\Columns\TextColumn::make('name_hi')->label('Hindi'),
                Tables\Columns\TextColumn::make('slug')->color('gray'),
                Tables\Columns\IconColumn::make('is_sensitive')->label('Sensitive')->boolean(),
                Tables\Columns\TextColumn::make('stories_count')->label('Stories')->alignCenter(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->after(fn () => PageCache::flush()),
                Tables\Actions\DeleteAction::make()->after(fn () => PageCache::flush()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCategories::route('/')];
    }
}
