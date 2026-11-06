<?php

namespace App\Events;

use App\Models\Story;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Fired after a story goes live (auto-publish or editor approval). Sitemap updates listen here (Milestone 6). */
class StoryPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public Story $story) {}
}
