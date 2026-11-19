<?php

namespace Noo\CraftSeoPreview\Tests;

use Craft;
use craft\base\Element;
use craft\config\GeneralConfig;
use craft\elements\Entry;
use craft\events\RegisterPreviewTargetsEvent;
use craft\models\Site;
use craft\services\Config;
use craft\services\Sites;
use craft\web\Application;
use craft\web\Request;
use Noo\CraftSeoPreview\SeoPreview;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use yii\base\Event;
use yii\i18n\I18N;

final class PreviewTargetTest extends TestCase
{
    public static function routingModes(): iterable
    {
        yield 'pretty URLs' => [true, false, '/de/seopreview/preview', null];
        yield 'path parameter' => [false, false, '/de/index.php', 'seopreview/preview'];
        yield 'PATH_INFO' => [false, true, '/de/index.php/seopreview/preview', null];
    }

    #[DataProvider('routingModes')]
    public function test_registered_target_uses_the_elements_site_in_a_cp_request(bool $pretty, bool $pathInfo, string $path, ?string $queryRoute): void
    {
        $app = $this->createPartialMock(Application::class, ['onInit']);
        Craft::$app = $app;
        $app->method('onInit')->willReturnCallback(static fn ($fn) => $fn());
        $general = $this->createStub(GeneralConfig::class);
        $general->omitScriptNameInUrls = $pretty;
        $general->usePathInfo = $pathInfo;
        $general->pathParam = 'p';
        $general->baseCpUrl = 'https://cp.example.test/';
        $config = $this->createStub(Config::class);
        $config->method('getGeneral')->willReturn($general);
        $request = $this->createStub(Request::class);
        $request->method('getIsCpRequest')->willReturn(true);
        $request->method('getScriptUrl')->willReturn('/index.php');
        $primary = $this->createStub(Site::class);
        $primary->id = 1;
        $primary->method('getBaseUrl')->willReturn('https://en.example.test/');
        $site = $this->createStub(Site::class);
        $site->id = 7;
        $site->method('getBaseUrl')->willReturn('https://de.example.test/de/');
        $sites = $this->createStub(Sites::class);
        $current = $primary;
        $sites->method('getCurrentSite')->willReturnCallback(static function () use (&$current) {
            return $current;
        });
        $sites->method('setCurrentSite')->willReturnCallback(static function ($site) use (&$current) {
            $current = $site;
        });
        $sites->method('getSiteById')->willReturn($site);
        $i18n = $this->createStub(I18N::class);
        $i18n->method('translate')->willReturn('SEO Preview');
        $app->setComponents(compact('config', 'request', 'sites', 'i18n'));
        $entry = $this->createStub(Entry::class);
        $entry->id = 42;
        $entry->siteId = 7;
        $entry->method('getSite')->willReturn($site);
        $entry->method('getUrl')->willReturn('https://de.example.test/de/article');
        try {
            new SeoPreview('seo-preview', $app);
            $event = new RegisterPreviewTargetsEvent;
            Event::trigger($entry, Element::EVENT_REGISTER_PREVIEW_TARGETS, $event);
            $url = parse_url($event->previewTargets[0]['url']);
            self::assertSame('de.example.test', $url['host']);
            self::assertSame($path, $url['path']);
            parse_str($url['query'], $query);
            self::assertSame('42', $query['elementId']);
            self::assertSame('7', $query['siteId']);
            self::assertSame($queryRoute, $query['p'] ?? null);
            self::assertSame($primary, $current);
        } finally {
            Event::offAll();
            Craft::$app = null;
        }
    }
}
