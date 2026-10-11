<?php

namespace Noo\CraftSeoPreview\controllers;

use Craft;
use craft\web\Controller;
use craft\web\View;
use Noo\CraftSeoPreview\assets\SeoPreviewAsset;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PreviewController extends Controller
{
    /**
     * Site template that outputs the element's meta tags.
     */
    public const SEO_PARTIAL = '_helpers/seo.twig';

    protected array|bool|int $allowAnonymous = false;

    public function actionIndex(): Response
    {
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $sites = Craft::$app->getSites();

        $elementId = $request->getRequiredParam('elementId');
        $siteId = $request->getParam('siteId');

        if (! is_numeric($elementId)) {
            throw new BadRequestHttpException('Invalid element ID.');
        }

        if ($siteId !== null && ! is_numeric($siteId)) {
            throw new BadRequestHttpException('Invalid site ID.');
        }

        $elementId = (int) $elementId;
        $siteId = $siteId !== null ? (int) $siteId : $sites->getCurrentSite()->id;

        if (! $sites->getSiteById($siteId)) {
            throw new BadRequestHttpException("Invalid site ID: $siteId");
        }

        if (! in_array($siteId, $sites->getEditableSiteIds(), true)) {
            throw new ForbiddenHttpException('User not authorized to preview content for this site.');
        }

        $element = Craft::$app->getElements()->getElementById($elementId, null, $siteId);

        if (! $element) {
            throw new NotFoundHttpException('Element not found.');
        }

        if (! $element->canView(static::currentUser())) {
            throw new ForbiddenHttpException('User not authorized to preview this element.');
        }

        $view = $this->getView();
        $view->registerAssetBundle(SeoPreviewAsset::class);

        // Show the element's own site rather than whatever host serves the preview.
        $siteBaseUrl = $element->getSite()->getBaseUrl();
        $hostName = parse_url($element->getUrl() ?? $siteBaseUrl ?? '', PHP_URL_HOST);

        return $this->renderTemplate('seo-preview/_seo', [
            'entry' => $element,
            'seoPartial' => self::SEO_PARTIAL,
            'hasSeoPartial' => $view->doesTemplateExist(self::SEO_PARTIAL, View::TEMPLATE_MODE_SITE),
            'siteBaseUrl' => $siteBaseUrl,
            'hostName' => is_string($hostName) ? $hostName : null,
        ], View::TEMPLATE_MODE_SITE);
    }
}
