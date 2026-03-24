<?php

namespace jorisnoo\SeoPreview;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\events\RegisterPreviewTargetsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
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
                    'label' => Craft::t('app', 'SEO Preview'),
                    'url' => UrlHelper::siteUrl('seopreview/preview', [
                        'elementId' => $element->id,
                        'siteId' => $element->siteId,
                    ]),
                ];
            }
        );

        $request = Craft::$app->getRequest();

        if (! Craft::$app->getUser()->getIsGuest() && $request->getIsSiteRequest() && ! $request->getIsConsoleRequest()) {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                static function (RegisterUrlRulesEvent $event) {
                    $event->rules['seopreview/preview'] = [
                        'template' => 'seo-preview/seo',
                    ];
                }
            );
        }
    }
}
