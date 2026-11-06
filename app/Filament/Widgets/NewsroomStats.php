<?php

namespace App\Filament\Widgets;

use App\Models\AiCheck;
use App\Models\Poll;
use App\Models\Report;
use App\Models\Story;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NewsroomStats extends BaseWidget
{
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        return [
            Stat::make('Waiting for approval', Story::where('status', 'pending_approval')->count())
                ->description('Sensitive or low-confidence drafts')
                ->color('danger')
                ->url(route('filament.admin.resources.approval-queues.index')),
            Stat::make('Auto-published today', AiCheck::where('decision', 'auto')->whereDate('created_at', today())->count())
                ->description('Passed every check'),
            Stat::make('Open reports', Report::where('status', 'open')->count())
                ->color('warning'),
            Stat::make('Polls to review', Poll::where('status', 'pending')->count()),
        ];
    }
}
