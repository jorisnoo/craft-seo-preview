<?php

namespace Noo\CraftSeoPreview\Tests;

use craft\web\twig\tokenparsers\ExitTokenParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use yii\web\NotFoundHttpException;

final class PreviewTemplateTest extends TestCase
{
    private function twig(string $head = ''): Environment
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__).'/src/templates'), ['strict_variables' => true]);
        $twig->addTokenParser(new ExitTokenParser);
        $twig->addFilter(new TwigFilter('t', static fn ($message) => $message));
        $twig->addFunction(new TwigFunction('head', static fn () => $head, ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('beginBody', static fn () => ''));
        $twig->addFunction(new TwigFunction('endBody', static fn () => ''));

        return $twig;
    }

    public function test_direct_template_render_is_not_a_guest_preview(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__).'/src/templates/seo.twig');
        try {
            $this->twig()->render('_seo.twig');
            self::fail('Public template rendering must not produce a preview shell.');
        } catch (RuntimeError $e) {
            self::assertInstanceOf(NotFoundHttpException::class, $e->getPrevious());
        }
    }

    public function test_registered_head_tags_render_in_all_three_cards_under_strict_csp(): void
    {
        $resources = dirname(__DIR__).'/src/resources';
        // An isolated file document keeps this test offline. Permit local assets,
        // but not inline code, inline event handlers or JavaScript evaluation.
        $head = <<<HTML
        <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src file:; style-src file:">
        <link rel="stylesheet" href="file://$resources/css/preview.css">
        <script defer src="file://$resources/js/preview.js"></script>
        <title>Draft title</title>
        <meta name="description" content="First draft">
        <meta property="og:title" content="Social draft">
        HTML;
        $html = $this->twig($head)->render('_seo.twig', [
            'hasPartial' => false,
            'hostName' => 'de.example.test',
            'entry' => ['site' => ['language' => 'de', 'baseUrl' => '/de/'], 'url' => '/de/article'],
        ]);
        $tmp = sys_get_temp_dir().'/seo-preview-'.bin2hex(random_bytes(8));
        $fs = new Filesystem;
        $fs->mkdir($tmp);
        try {
            $fs->dumpFile("$tmp/preview.html", $html);
            $chrome = getenv('CHROME_BIN') ?: (new ExecutableFinder)->find('google-chrome');
            self::assertNotEmpty($chrome, 'Install Chrome/Chromium and set CHROME_BIN to its executable.');
            $process = new Process([$chrome, '--headless', '--disable-gpu', '--no-first-run', '--disable-background-networking', "--user-data-dir=$tmp/profile", '--dump-dom', "file://$tmp/preview.html"]);
            $process->mustRun();
            $doc = new Crawler($process->getOutput());
            self::assertSame('Draft title', $doc->filter('.google-card__title')->text());
            self::assertSame('Social draft', $doc->filter('.facebook-card__title')->text());
            self::assertSame('Social draft', $doc->filter('.x-card__title')->text());
            self::assertSame('de.example.test', $doc->filter('.x-card__domain')->text());
            self::assertSame('11 / 60', $doc->filter('.char-count__value')->first()->text());
            self::assertStringContainsString('No _helpers/seo.twig', $doc->filter('[role="note"]')->text());
        } finally {
            $fs->remove($tmp);
        }
    }
}
