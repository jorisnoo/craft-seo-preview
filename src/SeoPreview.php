<?php

namespace Noo\CraftSeoPreview;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\events\RegisterPreviewTargetsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;

class SeoPreview extends Plugin
{
    public function init(): void
    {
        parent::init();

        Craft::$app->onInit(function () {
            $this->attachEventHandlers();
        });
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Element::class,
            Element::EVENT_REGISTER_PREVIEW_TARGETS,
            static function (RegisterPreviewTargetsEvent $event) {
                $element = $event->sender;
                if (! $element->getUrl()) {
                    return;
                }
                $event->previewTargets[] = [
                    'label' => Craft::t('seo-preview', 'SEO Preview'),
                    'url' => self::previewUrl($element),
                ];
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['seo-preview'] = $this->getBasePath().'/templates';
            }
        );

        $request = Craft::$app->getRequest();

        if (! $request->getIsConsoleRequest() && $request->getIsSiteRequest()) {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                static function (RegisterUrlRulesEvent $event) {
                    $event->rules['seopreview/preview'] = 'seo-preview/preview/index';
                }
            );
        }
    }

    private static function previewUrl(Element $element): string
    {
        $path = 'seopreview/preview';
        $params = ['elementId' => $element->id, 'siteId' => $element->siteId];
        $general = Craft::$app->getConfig()->getGeneral();
        $base = $element->getSite()->getBaseUrl();
        if ($general->omitScriptNameInUrls || ! $base || ! UrlHelper::isAbsoluteUrl($base)) {
            return UrlHelper::siteUrl($path, $params, siteId: $element->siteId);
        }

        // In script-name mode siteUrl() uses the CP host, even with a siteId.
        $request = Craft::$app->getRequest();
        $url = $base.($request->getIsConsoleRequest() ? 'index.php' : basename($request->getScriptUrl()));
        if ($general->usePathInfo || ! $general->pathParam) {
            $url .= '/'.$path.($general->addTrailingSlashesToUrls ? '/' : '');
        } else {
            $params[$general->pathParam] = $path;
        }

        return UrlHelper::urlWithParams($url, $params);
    }
}
