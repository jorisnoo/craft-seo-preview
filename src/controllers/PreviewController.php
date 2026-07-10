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

        $this->getView()->registerAssetBundle(SeoPreviewAsset::class);

        return $this->renderTemplate('seo-preview/seo', [
            'entry' => $element,
        ], View::TEMPLATE_MODE_SITE);
    }
}
