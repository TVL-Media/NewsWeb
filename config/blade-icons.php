<?php

/*
| blade-ui-kit/blade-icons comes in with Filament. By default it registers a generic
| <x-icon> component, which would replace our own Lucide <x-icon name="…" />
| (resources/views/components/icon.blade.php). Turning the generic one off keeps ours.
| Top-level keys not listed here keep the package defaults.
*/

return [
    'components' => [
        'disabled' => false,
        'default' => null,
    ],
];
