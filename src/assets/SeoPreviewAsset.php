<?php

namespace Noo\CraftSeoPreview\assets;

use craft\web\AssetBundle;

class SeoPreviewAsset extends AssetBundle
{
    public $sourcePath = __DIR__.'/../resources';

    public $css = ['css/preview.css'];

    public $js = [
        ['js/preview.js', 'defer' => true],
    ];
}
