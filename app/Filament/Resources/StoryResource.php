<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\StoryResource\Pages;
use App\Models\Story;
use App\Services\Newsroom\StoryPublisher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** All stories: edit both languages, upload a photo, publish/unpublish in one click, correction label. */
class StoryResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['editor', 'chief', 'admin'];

    protected static ?string $model = Story::class;
    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationGroup = 'Newsroom';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('Story')->columnSpanFull()->persistTabInQueryString()->tabs([
                self::versionTab('en', 'English'),
                self::versionTab('hi', 'हिंदी'),
                Forms\Components\Tabs\Tab::make('Details')->schema([
                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\Select::make('category_id')->relationship('category', 'name_en')->required()->preload(),
                        Forms\Components\Select::make('page_id')->relationship('page', 'name')->preload()->searchable(),
                        Forms\Components\Select::make('city_id')->relationship('city', 'name_en')->preload(),
                        Forms\Components\Select::make('source_id')->relationship('source', 'name')->preload()->searchable()
                            ->createOptionForm([Forms\Components\TextInput::make('name')->required(), Forms\Components\TextInput::make('url')->url()]),
                        Forms\Components\TextInput::make('source_url')->label('Original URL')->url()->columnSpan(2),
                        Forms\Components\Select::make('status')->options([
                            'draft' => 'Draft', 'pending_approval' => 'Pending approval', 'published' => 'Published',
                            'corrected' => 'Corrected', 'rejected' => 'Rejected', 'removed' => 'Removed',
                        ])->required()->default('draft')
                            ->helperText('Use the Publish / Unpublish buttons to go live: they also make the audio and clear caches.'),
                        Forms\Components\TextInput::make('importance')->numeric()->minValue(1)->maxValue(10)->default(5)->required(),
                        Forms\Components\TextInput::make('read_minutes')->numeric()->minValue(1)->maxValue(30)->default(2)->required(),
                        Forms\Components\Toggle::make('is_breaking')->label('Breaking news'),
                        Forms\Components\DateTimePicker::make('published_at')->seconds(false),
                        Forms\Components\TextInput::make('correction_label')
                            ->placeholder('Corrected: an earlier version gave the wrong venue.')
                            ->helperText('Shown above the headline. Publishing with a label sets the status to "corrected".')
                            ->columnSpan(2),
                    ]),
                    Forms\Components\FileUpload::make('photo_path')->label('Photo')->image()->imageEditor()
                        ->disk(config('filesystems.media'))->directory('stories')->maxSize(8192),
                    Forms\Components\TextInput::make('photo_credit'),
                ]),
            ]),
        ]);
    }

    /** One language tab. State lives under "en.*" / "hi.*" and is saved to story_versions by the pages. */
    private static function versionTab(string $lang, string $label): Forms\Components\Tabs\Tab
    {
        $hi = $lang === 'hi';
        $field = fn (Forms\Components\Field $f) => $hi ? $f->extraInputAttributes(['lang' => 'hi']) : $f;

        return Forms\Components\Tabs\Tab::make($label)->schema([
            $field(Forms\Components\TextInput::make("{$lang}.headline")->label('Headline')->required(! $hi)->maxLength(250)),
            $field(Forms\Components\Textarea::make("{$lang}.card_text")->label('Card text (≤ 60 words)')->rows(3)->required(! $hi)
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if ($value && count(preg_split('/\s+/u', trim($value))) > 60) {
                        $fail('The card text must be 60 words or fewer.');
                    }
                })),
            Forms\Components\Grid::make(1)->schema([
                $field(Forms\Components\TextInput::make("{$lang}.key_points.0")->label('Key point 1')->required(! $hi)),
                $field(Forms\Components\TextInput::make("{$lang}.key_points.1")->label('Key point 2')->required(! $hi)),
                $field(Forms\Components\TextInput::make("{$lang}.key_points.2")->label('Key point 3')->required(! $hi)),
            ]),
            $field(Forms\Components\Textarea::make("{$lang}.why_it_matters")->label('Why it matters')->rows(2)),
            $field(Forms\Components\Textarea::make("{$lang}.simple_text")->label('Explain simply')->rows(4)),
            $field(Forms\Components\Textarea::make("{$lang}.full_text")->label('Full story (blank line between paragraphs)')->rows(12)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['versions', 'category']))
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('photo_path')->label('')->disk(config('filesystems.media'))->square()->size(44),
                Tables\Columns\TextColumn::make('headline')->label('Headline')->wrap()->limit(90)
                    ->getStateUsing(fn (Story $r) => $r->version('en')?->headline)
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas('versions', fn ($v) => $v->where('headline', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('category.name_en')->label('Category')->badge()->color('gray'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'published' => 'success', 'corrected' => 'info', 'pending_approval' => 'warning', 'rejected', 'removed' => 'danger', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('publish_mode')->label('Mode')->badge()->color(fn ($state) => $state === 'auto' ? 'info' : 'gray'),
                Tables\Columns\TextColumn::make('importance')->sortable()->alignCenter(),
                Tables\Columns\TextColumn::make('published_at')->since()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'Draft', 'pending_approval' => 'Pending approval', 'published' => 'Published',
                    'corrected' => 'Corrected', 'rejected' => 'Rejected', 'removed' => 'Removed',
                ]),
                Tables\Filters\SelectFilter::make('category')->relationship('category', 'name_en'),
                Tables\Filters\SelectFilter::make('publish_mode')->options(['auto' => 'Auto', 'manual' => 'Manual']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('publish')->icon('heroicon-o-arrow-up-circle')->color('success')
                    ->visible(fn (Story $r) => in_array($r->status, ['draft', 'pending_approval', 'removed', 'rejected'], true))
                    ->requiresConfirmation()
                    ->action(function (Story $r) {
                        app(StoryPublisher::class)->approve($r, auth('admin')->user());
                        Notification::make()->title('Published')->success()->send();
                    }),
                Tables\Actions\Action::make('unpublish')->icon('heroicon-o-eye-slash')->color('danger')
                    ->visible(fn (Story $r) => in_array($r->status, ['published', 'corrected'], true))
                    ->requiresConfirmation()->modalDescription('The story disappears from the site straight away.')
                    ->action(function (Story $r) {
                        app(StoryPublisher::class)->unpublish($r);
                        Notification::make()->title('Unpublished')->success()->send();
                    }),
                Tables\Actions\Action::make('view')->icon('heroicon-o-arrow-top-right-on-square')->label('Open')
                    ->visible(fn (Story $r) => in_array($r->status, ['published', 'corrected'], true))
                    ->url(fn (Story $r) => route('story.show', $r), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStories::route('/'),
            'create' => Pages\CreateStory::route('/create'),
            'edit' => Pages\EditStory::route('/{record}/edit'),
        ];
    }

    /** Write the en/hi form state into story_versions. */
    public static function saveVersions(Story $story, array $data): void
    {
        foreach (['en', 'hi'] as $lang) {
            $v = $data[$lang] ?? [];
            if (blank($v['headline'] ?? null)) {
                continue; // Hindi is optional; readers get the English fallback
            }
            $story->versions()->updateOrCreate(['language' => $lang], [
                'headline' => trim($v['headline']),
                'card_text' => trim((string) ($v['card_text'] ?? '')),
                'key_points' => array_values(array_map('trim', array_filter($v['key_points'] ?? [], 'filled'))),
                'why_it_matters' => $v['why_it_matters'] ?? null,
                'simple_text' => $v['simple_text'] ?? null,
                'full_text' => $v['full_text'] ?? null,
            ]);
        }
    }

    /** Form state for the language tabs from story_versions. */
    public static function versionState(Story $story): array
    {
        $out = [];
        foreach ($story->versions as $v) {
            $out[$v->language] = [
                'headline' => $v->headline, 'card_text' => $v->card_text,
                'key_points' => array_pad($v->key_points ?? [], 3, ''),
                'why_it_matters' => $v->why_it_matters, 'simple_text' => $v->simple_text, 'full_text' => $v->full_text,
            ];
        }

        return $out;
    }
}
