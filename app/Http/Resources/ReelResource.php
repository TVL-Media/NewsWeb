<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One reel for the web player and the app API. Expects the reel to be run through
 * App\Support\ReelFeed::decorate() first (is_liked, is_saved, page_following, other_language_reel_id).
 *
 * @mixin \App\Models\Reel
 */
class ReelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $page = $this->page;
        $story = $this->story;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'language' => $this->language,
            'duration' => (int) $this->duration_seconds,
            'video_720_url' => $this->videoUrl('720'),
            'video_480_url' => $this->videoUrl('480'),
            'thumbnail_url' => $this->thumbnailUrl(),
            'url' => route('reels.show', $this->resource),
            'other_language_reel_id' => $this->other_language_reel_id,
            'page' => $page ? [
                'id' => $page->id,
                'slug' => $page->slug,
                'name' => $page->name($this->language),
                'avatar' => $page->avatarUrl(),
                'verified' => (bool) $page->verified,
                'is_following' => (bool) $this->page_following,
            ] : null,
            'story_id' => $this->story_id,
            'story_slug' => $story?->slug,
            'story_url' => $story ? route('story.show', $story) : null,
            'counts' => [
                'likes' => (int) $this->likes_count,
                'comments' => (int) $this->comments_count,
                'shares' => (int) $this->shares_count,
                'views' => (int) $this->views_count,
            ],
            'is_liked' => (bool) $this->is_liked,
            'is_saved' => (bool) $this->is_saved,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
