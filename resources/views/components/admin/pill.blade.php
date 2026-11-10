@props(['tone' => 'neutral'])
<span {{ $attributes->merge(['class' => 'pill '.$tone]) }}>{{ $slot }}</span>
