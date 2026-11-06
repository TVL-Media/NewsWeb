<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsByRole;
use App\Models\AutoPublishSetting;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** Sensitive categories, keywords, confidence and copy thresholds for auto-publishing. */
class AutoPublishSettings extends Page implements HasForms
{
    use InteractsWithForms, RestrictsByRole;

    protected static array $roles = ['chief', 'admin'];

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $title = 'Auto-publish settings';
    protected static string $view = 'filament.pages.auto-publish-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(AutoPublishSetting::current()->only(['sensitive_categories', 'sensitive_keywords', 'confidence_threshold', 'copy_threshold']));
    }

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Forms\Components\Section::make('Always send to an editor')->schema([
                Forms\Components\Select::make('sensitive_categories')->multiple()
                    ->options(Category::orderBy('sort_order')->pluck('name_en', 'slug'))
                    ->helperText('Stories in these categories are never auto-published.'),
                Forms\Components\TagsInput::make('sensitive_keywords')->placeholder('Add a word or phrase')
                    ->helperText('Matched in English and Hindi drafts and in the source text (whole words for English).'),
            ]),
            Forms\Components\Section::make('Thresholds')->columns(2)->schema([
                Forms\Components\TextInput::make('confidence_threshold')->numeric()->step(0.01)->minValue(0)->maxValue(1)->required()
                    ->helperText('Auto-publish only at or above this confidence (0–1).'),
                Forms\Components\TextInput::make('copy_threshold')->numeric()->step(0.01)->minValue(0)->maxValue(1)->required()
                    ->helperText('Send to an editor when this share of 5-word phrases matches the source (0–1).'),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        AutoPublishSetting::current()->update([
            'sensitive_categories' => array_values($data['sensitive_categories'] ?? []),
            'sensitive_keywords' => array_values(array_filter(array_map('trim', $data['sensitive_keywords'] ?? []))),
            'confidence_threshold' => (float) $data['confidence_threshold'],
            'copy_threshold' => (float) $data['copy_threshold'],
        ]);

        Notification::make()->title('Settings saved')->success()->send();
    }
}
