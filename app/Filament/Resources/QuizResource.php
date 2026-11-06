<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\QuizResource\Pages;
use App\Models\Quiz;
use App\Models\Story;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/** Daily quizzes: drafted at 21:00 by DraftQuiz; approved automatically when all source stories are low-risk. */
class QuizResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['editor', 'chief', 'admin'];

    protected static ?string $model = Quiz::class;
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationGroup = 'Daily';
    protected static ?string $navigationLabel = 'Daily quiz';

    public static function form(Form $form): Form
    {
        $stories = fn () => Story::published()->with('versions')->where('published_at', '>=', now()->subDays(3))
            ->latest('published_at')->get()->mapWithKeys(fn ($s) => [$s->id => $s->version('en')?->headline]);

        return $form->schema([
            Forms\Components\DatePicker::make('date')->required()->unique(ignoreRecord: true),
            Forms\Components\Select::make('status')->options(['draft' => 'Draft (hidden)', 'approved' => 'Approved', 'published' => 'Published'])->required()->default('draft'),
            Forms\Components\Repeater::make('questions')->relationship()->orderColumn('sort_order')
                ->minItems(1)->maxItems(5)->collapsible()->columnSpanFull()
                ->itemLabel(fn (array $state) => $state['question'] ?? null)
                ->schema([
                    Forms\Components\Select::make('story_id')->label('Story')->options($stories)->searchable(),
                    Forms\Components\TextInput::make('question')->required()->maxLength(255),
                    Forms\Components\Grid::make(2)->schema([
                        Forms\Components\TextInput::make('options.0')->label('A')->required(),
                        Forms\Components\TextInput::make('options.1')->label('B')->required(),
                        Forms\Components\TextInput::make('options.2')->label('C')->required(),
                        Forms\Components\TextInput::make('options.3')->label('D')->required(),
                    ]),
                    Forms\Components\Select::make('correct_index')->label('Correct answer')->options(['A', 'B', 'C', 'D'])->required(),
                    Forms\Components\Textarea::make('explanation')->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['questions', 'attempts']))
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('date')->date('D, j M Y')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => $state === 'draft' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('questions_count')->label('Questions')->alignCenter(),
                Tables\Columns\TextColumn::make('attempts_count')->label('Players')->alignCenter(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (Quiz $r) => $r->status === 'draft')
                    ->action(function (Quiz $r) {
                        $r->update(['status' => 'approved']);
                        Notification::make()->title('Quiz approved')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageQuizzes::route('/')];
    }
}
