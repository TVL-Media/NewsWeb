<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\RestrictsByRole;
use App\Filament\Resources\CommentResource\Pages;
use App\Models\Comment;
use App\Models\Story;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Moderation › Comments: reported comments first; hide or restore. */
class CommentResource extends Resource
{
    use RestrictsByRole;

    protected static array $roles = ['editor', 'chief', 'admin'];

    protected static ?string $model = Comment::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Moderation';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'story.versions'])->addSelect([
            'reports_count' => DB::table('reports')->selectRaw('count(*)')
                ->where('target_type', 'comment')->whereColumn('target_id', 'comments.id')->where('status', 'open'),
        ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $n = DB::table('reports')->where('target_type', 'comment')->where('status', 'open')->distinct()->count('target_id');

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('body')->wrap()->limit(160),
                Tables\Columns\TextColumn::make('user.name')->label('By')->placeholder('Reader'),
                Tables\Columns\TextColumn::make('story')->label('On')->limit(50)
                    ->getStateUsing(fn (Comment $r) => $r->story?->version('en')?->headline),
                Tables\Columns\TextColumn::make('reports_count')->label('Open reports')->badge()->color(fn ($state) => $state ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => $state === 'hidden' ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->filters([
                Tables\Filters\Filter::make('reported')->label('Reported only')->default()
                    ->query(fn (Builder $q) => $q->whereExists(fn ($e) => $e->from('reports')->whereColumn('reports.target_id', 'comments.id')
                        ->where('reports.target_type', 'comment')->where('reports.status', 'open'))),
                Tables\Filters\SelectFilter::make('status')->options(['visible' => 'Visible', 'hidden' => 'Hidden']),
            ])
            ->actions([
                Tables\Actions\Action::make('hide')->color('danger')->icon('heroicon-o-eye-slash')
                    ->visible(fn (Comment $r) => $r->status === 'visible')
                    ->action(fn (Comment $r) => self::setStatus($r, 'hidden')),
                Tables\Actions\Action::make('restore')->color('gray')->icon('heroicon-o-eye')
                    ->visible(fn (Comment $r) => $r->status === 'hidden')
                    ->action(fn (Comment $r) => self::setStatus($r, 'visible')),
            ]);
    }

    /** Hide/restore a comment, keep the story's comment count right and close its reports. */
    public static function setStatus(Comment $comment, string $status): void
    {
        if ($comment->status === $status) {
            return;
        }
        $comment->update(['status' => $status]);
        $status === 'hidden'
            ? Story::whereKey($comment->story_id)->where('comments_count', '>', 0)->decrement('comments_count')
            : Story::whereKey($comment->story_id)->increment('comments_count');

        DB::table('reports')->where('target_type', 'comment')->where('target_id', $comment->id)->where('status', 'open')
            ->update(['status' => $status === 'hidden' ? 'actioned' : 'dismissed', 'updated_at' => now()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageComments::route('/')];
    }
}
