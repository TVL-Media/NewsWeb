<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** POST /reports — stories, comments and polls go to the moderation queue (admin panel → Reports). */
class ReportController extends Controller
{
    public const REASONS = ['spam', 'abusive', 'misinformation', 'other'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(array_keys(Report::TARGETS))],
            'target_id' => ['required', 'integer'],
            'reason' => ['required', Rule::in(self::REASONS)],
        ]);

        $class = Report::TARGETS[$data['target_type']];
        abort_unless($class::whereKey($data['target_id'])->exists(), 404);

        Report::firstOrCreate(
            ['reporter_id' => $request->user()->id, 'target_type' => $data['target_type'], 'target_id' => $data['target_id']],
            ['reason' => $data['reason'], 'status' => 'open'],
        );

        return response()->json(['reported' => true], 201);
    }
}
