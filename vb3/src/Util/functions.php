<?php

namespace Sphere\Debloat\Util;

function asset_match($pattern, $asset) {
    if (empty($pattern) || empty($asset)) {
        return false;
    }

    // Convert wildcards to regex pattern
    $pattern = str_replace(
        ['*', '/'],
        ['.*', '\/'],
        $pattern
    );

    return (bool) preg_match('#' . $pattern . '#i', $asset->url);
}

function option_to_array($option) {
    if (empty($option)) {
        return [];
    }

    if (is_array($option)) {
        return $option;
    }

    return array_filter(
        array_map('trim', explode("\n", $option))
    );
}