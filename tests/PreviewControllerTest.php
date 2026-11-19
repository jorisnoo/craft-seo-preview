<?php

namespace Noo\CraftSeoPreview\Tests;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\AuthorizationCheckEvent;
use craft\models\Site;
use craft\services\Elements;
use craft\services\Sites;
use craft\web\Application;
use craft\web\Request;
use craft\web\Response;
use craft\web\UrlManager;
use craft\web\User as Session;
use craft\web\View;
use Noo\CraftSeoPreview\assets\SeoPreviewAsset;
use Noo\CraftSeoPreview\controllers\PreviewController;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use yii\base\ExitException;
use yii\web\BadRequestHttpException;
use yii\web\ErrorHandler;
use yii\web\ForbiddenHttpException;

#[AllowMockObjectsWithoutExpectations]
final class PreviewControllerTest extends TestCase
{
    private PreviewController $controller;

    private Request $request;

    private UrlManager $router;

    private Elements $elements;

    private Sites $sites;

    private Session $session;

    private Entry $entry;

    private Site $site;

    protected function setUp(): void
    {
        $app = $this->createPartialMock(Application::class, ['getIsMultiSite', 'getIsLive', 'end']);
        Craft::$app = $app;
        $this->request = $this->createPartialMock(Request::class, ['getToken', 'getIsLivePreview', 'hasValidSiteToken', 'validateCsrfToken']);
        $this->request->method('validateCsrfToken')->willReturn(true);
        $this->request->setIsCpRequest(false);
        $this->request->setQueryParams(['siteId' => '7', 'elementId' => '42']);
        $this->request->setBodyParams([]);
        $this->router = $this->createPartialMock(UrlManager::class, ['setMatchedElement']);
        $this->session = $this->createMock(Session::class);
        $this->session->method('getIdentity')->willReturn($this->createStub(User::class));
        $this->site = $this->createPartialMock(Site::class, ['getBaseUrl']);
        $this->site->id = 7;
        $this->site->method('getBaseUrl')->willReturn('https://de.example.test/de/');
        $this->sites = $this->createMock(Sites::class);
        $this->sites->method('getCurrentSite')->willReturn($this->site);
        $this->sites->method('getSiteById')->willReturnMap([[7, true, $this->site]]);
        $this->entry = $this->createPartialMock(Entry::class, ['canView']);
        $this->entry->id = 42;
        $this->entry->siteId = 7;
        $this->elements = $this->createPartialMock(Elements::class, ['getElementById']);
        $app->setComponents([
            'request' => $this->request,
            'response' => new Response,
            'user' => $this->session,
            'urlManager' => $this->router,
            'sites' => $this->sites,
            'elements' => $this->elements,
            'view' => $this->createMock(View::class),
            'errorHandler' => $this->createStub(ErrorHandler::class),
        ]);
        $this->controller = new PreviewController('preview', $app);
    }

    protected function tearDown(): void
    {
        Craft::$app = null;
    }

    public function test_direct_guest_goes_through_crafts_login_flow_even_with_a_token_parameter(): void
    {
        $this->request->method('getToken')->willReturn('unrelated');
        $this->session->method('getIsGuest')->willReturn(true);
        $this->session->expects(self::once())->method('loginRequired');
        Craft::$app->expects(self::once())->method('end')->willThrowException(new ExitException);
        $this->elements->expects(self::never())->method('getElementById');
        $this->expectException(ExitException::class);
        $this->controller->actionIndex();
    }

    public function test_site_permission_denial_precedes_any_element_lookup(): void
    {
        $this->sites->method('getEditableSiteIds')->willReturn([1]);
        $this->elements->expects(self::never())->method('getElementById');
        $this->expectException(ForbiddenHttpException::class);
        $this->controller->actionIndex();
    }

    public function test_service_authorization_event_can_veto_an_otherwise_viewable_entry(): void
    {
        $this->sites->method('getEditableSiteIds')->willReturn([7]);
        $this->elements->method('getElementById')->willReturn($this->entry);
        $this->entry->method('canView')->willReturn(true);
        $this->elements->on(Elements::EVENT_AUTHORIZE_VIEW, static function (AuthorizationCheckEvent $event) {
            $event->authorized = false;
        });
        $this->expectException(ForbiddenHttpException::class);
        $this->controller->actionIndex();
    }

    public static function malformedIds(): iterable
    {
        yield ['elementId', 'not-an-id'];
        yield ['siteId', '7.5'];
        yield ['elementId', ['42']];
    }

    #[DataProvider('malformedIds')]
    public function test_malformed_ids_return_bad_request(string $key, mixed $value): void
    {
        $this->request->setQueryParams([$key => $value] + ['elementId' => '42', 'siteId' => '7']);
        $this->elements->expects(self::never())->method('getElementById');
        $this->expectException(BadRequestHttpException::class);
        $this->controller->actionIndex();
    }

    public function test_authorized_editor_can_preview_a_disabled_site_with_the_matched_element(): void
    {
        $this->site->setEnabled(false);
        $this->sites->method('getEditableSiteIds')->willReturn([7]);
        $this->sites->expects(self::once())->method('setCurrentSite')->with($this->site);
        $this->router->expects(self::once())->method('setMatchedElement')->with($this->entry);
        $this->elements->expects(self::once())->method('getElementById')->with(42, null, 7)->willReturn($this->entry);
        $this->entry->method('canView')->willReturn(true);
        Craft::$app->getView()->expects(self::once())->method('registerAssetBundle')->with(SeoPreviewAsset::class);
        $response = $this->controller->actionIndex();
        self::assertSame('seo-preview/_seo', $response->template);
        self::assertSame($this->entry, $response->variables['entry']);
        self::assertSame('de.example.test', $response->variables['hostName']);
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_token_reroute_uses_the_actual_preview_draft_without_a_site_session(): void
    {
        $this->installPreviewDraft();
        $this->session->expects(self::never())->method('getIsGuest');
        self::assertTrue($this->controller->beforeAction($this->controller->createAction('index')));
        $response = $this->controller->actionIndex();
        self::assertSame($this->entry, $response->variables['entry']);
        self::assertSame(88, $response->variables['entry']->id);
    }

    public static function mismatchedPreview(): iterable
    {
        yield 'another element' => [43, 7, true];
        yield 'another site' => [42, 2, true];
        yield 'ordinary placeholder' => [42, 7, false];
    }

    #[DataProvider('mismatchedPreview')]
    public function test_token_does_not_authorize_an_unrelated_element_or_placeholder(int $canonicalId, int $siteId, bool $previewing): void
    {
        $this->installPreviewDraft($canonicalId, $siteId, $previewing);
        $this->expectException(ForbiddenHttpException::class);
        $this->controller->actionIndex();
    }

    private function installPreviewDraft(int $canonicalId = 42, int $siteId = 7, bool $previewing = true): void
    {
        $this->router->checkToken = false;
        $this->request->method('getToken')->willReturn('preview');
        $this->entry->id = 88;
        $this->entry->siteId = $siteId;
        $this->entry->setCanonicalId($canonicalId);
        $this->entry->previewing = $previewing;
        $this->elements->setPlaceholderElement($this->entry);
        $this->elements->expects(self::never())->method('getElementById');
    }
}
