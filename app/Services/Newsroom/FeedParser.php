<?php

namespace App\Services\Newsroom;

use Illuminate\Support\Carbon;
use SimpleXMLElement;

/**
 * Parses RSS 2.0, Atom and JSON Feed into plain items:
 * [{url, title, body, image_url, published_at}]. Agency feeds are expected in one of these formats.
 */
class FeedParser
{
    public function parse(string $content): array
    {
        $content = ltrim($content, "\xEF\xBB\xBF \t\n\r");

        if (str_starts_with($content, '{')) {
            return $this->parseJsonFeed(json_decode($content, true) ?? []);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if (! $xml) {
            return [];
        }

        return $xml->getName() === 'feed' ? $this->parseAtom($xml) : $this->parseRss($xml);
    }

    private function parseRss(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->channel->item ?? [] as $item) {
            $ns = $item->getNamespaces(true);
            $content = isset($ns['content']) ? (string) $item->children($ns['content'])->encoded : '';
            $image = (string) ($item->enclosure['url'] ?? '');
            if (! $image && isset($ns['media'])) {
                $media = $item->children($ns['media']);
                $image = (string) ($media->content->attributes()->url ?? $media->thumbnail->attributes()->url ?? '');
            }

            $items[] = $this->item(
                url: (string) ($item->link ?: $item->guid),
                title: (string) $item->title,
                body: $content ?: (string) $item->description,
                image: $image,
                date: (string) ($item->pubDate ?: ($ns['dc'] ?? null ? $item->children($ns['dc'])->date : '')),
            );
        }

        return array_values(array_filter($items));
    }

    private function parseAtom(SimpleXMLElement $xml): array
    {
        $items = [];
        foreach ($xml->entry as $entry) {
            $link = '';
            foreach ($entry->link as $l) {
                if ((string) ($l['rel'] ?? 'alternate') === 'alternate') {
                    $link = (string) $l['href'];
                    break;
                }
            }
            $items[] = $this->item(
                url: $link ?: (string) $entry->id,
                title: (string) $entry->title,
                body: (string) ($entry->content ?: $entry->summary),
                image: '',
                date: (string) ($entry->published ?: $entry->updated),
            );
        }

        return array_values(array_filter($items));
    }

    private function parseJsonFeed(array $feed): array
    {
        return array_values(array_filter(array_map(fn ($i) => $this->item(
            url: (string) ($i['url'] ?? $i['id'] ?? ''),
            title: (string) ($i['title'] ?? ''),
            body: (string) ($i['content_html'] ?? $i['content_text'] ?? $i['summary'] ?? ''),
            image: (string) ($i['image'] ?? $i['banner_image'] ?? ''),
            date: (string) ($i['date_published'] ?? ''),
        ), $feed['items'] ?? [])));
    }

    private function item(string $url, string $title, string $body, string $image, string $date): ?array
    {
        $url = trim($url);
        $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5));

        if (! filter_var($url, FILTER_VALIDATE_URL) || $title === '') {
            return null;
        }

        try {
            $published = $date ? Carbon::parse($date) : now();
        } catch (\Throwable) {
            $published = now();
        }

        return [
            'url' => mb_substr($url, 0, 700),
            'title' => mb_substr($title, 0, 255),
            'body' => trim(html_entity_decode(strip_tags($body, '<p><br>'), ENT_QUOTES | ENT_HTML5)),
            'image_url' => filter_var($image, FILTER_VALIDATE_URL) ? mb_substr($image, 0, 700) : null,
            'published_at' => $published->isFuture() ? now() : $published,
        ];
    }
}
