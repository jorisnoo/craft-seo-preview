<?php

namespace Noo\CraftSeoPreview\controllers;

use Craft;
use craft\base\ElementInterface;
use craft\web\Controller;
use craft\web\View;
use Noo\CraftSeoPreview\assets\SeoPreviewAsset;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class PreviewController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function beforeAction($action): bool
    {
        if ($this->isReroutedPreview()) {
            // Match Craft's preview controller while retaining normal offline
            // access restrictions for requests using a site session.
            $this->allowAnonymous['index'] |= self::ALLOW_ANONYMOUS_OFFLINE;
        }

        return parent::beforeAction($action);
    }

    public function actionIndex(): Response
    {
        $this->requireSiteRequest();
        $tokenPreview = $this->isReroutedPreview();
        if (! $tokenPreview) {
            $this->requireLogin();
        }

        $request = Craft::$app->getRequest();
        $sites = Craft::$app->getSites();

        $elementId = $this->positiveId($request->getRequiredParam('elementId'), 'element');
        $siteId = $this->positiveId($request->getParam('siteId', $sites->getCurrentSite()->id), 'site');
        $site = $sites->getSiteById($siteId, true);
        if (! $site) {
            throw new BadRequestHttpException("Invalid site ID: $siteId");
        }

        $elements = Craft::$app->getElements();
        if ($tokenPreview) {
            $element = $this->previewedElement($elementId, $siteId);
        } else {
            if (! in_array($siteId, $sites->getEditableSiteIds(), true)) {
                throw new ForbiddenHttpException('User not authorized to preview content for this site.');
            }

            $element = $elements->getElementById($elementId, null, $siteId);

            if (! $element) {
                throw new NotFoundHttpException('Element not found.');
            }

            if (! $elements->canView($element, static::currentUser())) {
                throw new ForbiddenHttpException('User not authorized to preview this element.');
            }
        }

        $sites->setCurrentSite($site);
        $element->previewing = true;
        Craft::$app->getUrlManager()->setMatchedElement($element);
        $view = $this->getView();
        $view->registerAssetBundle(SeoPreviewAsset::class);
        $this->response->setNoCacheHeaders();
        $this->response->getHeaders()->set('Referrer-Policy', 'no-referrer');

        return $this->renderTemplate('seo-preview/_seo', [
            'entry' => $element,
            'hasPartial' => $view->doesTemplateExist('_helpers/seo.twig', View::TEMPLATE_MODE_SITE),
            'hostName' => parse_url($site->getBaseUrl() ?? '', PHP_URL_HOST) ?: '',
        ], View::TEMPLATE_MODE_SITE);
    }

    private function isReroutedPreview(): bool
    {
        // Craft's preview controller sets checkToken=false *after* validating
        // the token and installing the element for the second routing pass.
        return ! Craft::$app->getUrlManager()->checkToken && $this->request->getToken() !== null;
    }

    private function previewedElement(int $elementId, int $siteId): ElementInterface
    {
        foreach (Craft::$app->getElements()->getPlaceholderElements() as $element) {
            if ($element->previewing && $element->siteId === $siteId &&
                ($element->id === $elementId || $element->getCanonicalId() === $elementId)) {
                return $element;
            }
        }

        // A token is not permission to query arbitrary elements or sites.
        throw new ForbiddenHttpException('This preview link does not match the requested element and site.');
    }

    private function positiveId(mixed $value, string $name): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ((! is_int($value) && ! is_string($value)) || $id === false) {
            throw new BadRequestHttpException("Invalid $name ID.");
        }

        return $id;
    }
}
