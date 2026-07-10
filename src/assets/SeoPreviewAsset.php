<?php

namespace Noo\CraftSeoPreview\assets;

use craft\web\AssetBundle;

/**
 * Bundles Alpine.js (v3.15.12, dist/cdn.min.js) rather than loading it from a
 * third-party CDN, since the preview renders inside an authenticated session.
 */
class SeoPreviewAsset extends AssetBundle
{
    public $sourcePath = __DIR__.'/../resources';

    public $js = [
        ['js/alpine.min.js', 'defer' => true],
    ];
}
