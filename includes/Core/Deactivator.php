<?php

namespace WMPS\Core;

if (! defined('ABSPATH')) {
    exit;
}

final class Deactivator
{
    public static function deactivate(): void
    {
        // Keep data for safe reactivation.
    }
}