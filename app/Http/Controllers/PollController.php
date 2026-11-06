<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Support\ProfanityFilter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PollController extends Controller
{
    public const USER_POLLS_PER_DAY = 3;

    // POST /polls/{poll}/vote  { option_id } — one vote per reader; results come back after voting.
    public function vote(Request $request, Poll $poll): JsonResponse
    {
        $data = $request->validate(['option_id' => ['required', 'integer']]);
        $option = $poll->options()->whereKey($data['option_id'])->firstOrFail();

        if (! $poll->isOpen()) {
            return response()->json(['message' => __('This poll has closed.'), 'options' => $this->results($poll)], 422);
        }

        try {
            DB::transaction(function () use ($request, $poll, $option) {
                PollVote::create(['poll_id' => $poll->id, 'poll_option_id' => $option->id, 'user_id' => $request->user()->id]);
                PollOption::whereKey($option->id)->increment('votes_count');
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => __('You have already voted in this poll.'),
                'voted' => PollVote::where('poll_id', $poll->id)->where('user_id', $request->user()->id)->value('poll_option_id'),
                'options' => $this->results($poll),
            ], 409);
        }

        return response()->json(['voted' => $option->id, 'options' => $this->results($poll)]);
    }

    // POST /polls — reader-created poll, starts as "pending" until an editor approves it.
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:10', 'max:150'],
            'options' => ['required', 'array', 'min:2', 'max:4'],
            'options.*' => ['required', 'string', 'min:1', 'max:60', 'distinct:ignore_case'],
            'days' => ['required', 'integer', Rule::in([1, 3, 7])],
            'story_id' => ['nullable', 'integer', 'exists:stories,id'],
        ]);
        $user = $request->user();

        foreach ([$data['question'], ...$data['options']] as $text) {
            if (! ProfanityFilter::isClean($text)) {
                throw ValidationException::withMessages(['question' => __('Please keep it respectful. Your poll has words our community rules don\'t allow.')]);
            }
        }

        if (Poll::where('created_by', $user->id)->where('created_at', '>=', now()->subDay())->count() >= self::USER_POLLS_PER_DAY) {
            throw ValidationException::withMessages(['question' => __('You can create up to 3 polls a day.')]);
        }

        $poll = DB::transaction(function () use ($data, $user) {
            $poll = Poll::create([
                'story_id' => $data['story_id'] ?? null,
                'created_by' => $user->id,
                'question' => trim($data['question']),
                'status' => 'pending',
                'ends_at' => now()->addDays($data['days']),
            ]);
            foreach (array_values($data['options']) as $i => $label) {
                $poll->options()->create(['label' => trim($label), 'sort_order' => $i]);
            }

            return $poll;
        });

        return response()->json(['id' => $poll->id, 'status' => 'pending', 'message' => __('Thanks! Your poll will go live after an editor reviews it.')], 201);
    }

    private function results(Poll $poll): array
    {
        return $poll->options()->get()->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'votes' => (int) $o->votes_count])->all();
    }
}
