{{-- Latest News items. Each item: grid card on desktop, horizontal card (96×80) on mobile. Also returned alone for "Load more". --}}
@foreach ($stories as $story)
    <div class="min-w-0">
        <div class="hidden h-full md:block"><x-story-card :story="$story" class="h-full" /></div>
        <div class="md:hidden"><x-story-card :story="$story" variant="row" /></div>
    </div>
@endforeach
