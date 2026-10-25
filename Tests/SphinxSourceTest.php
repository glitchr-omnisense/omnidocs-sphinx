<?php

namespace Omnidocs\Sphinx\Tests;

use Omnidocs\Model\Node;
use Omnidocs\SourceInterface;
use Omnidocs\Sphinx\Inventory;
use Omnidocs\Sphinx\SphinxSourceFactory;
use Omnidocs\Testing\RecordingLinker;
use Omnidocs\Testing\SourceContractTests;
use PHPUnit\Framework\TestCase;

/**
 * Real output: Requests' documentation (psf/requests v2.32.3, Fixtures/requests/COMMIT) built by
 * `sphinx-build -b json docs out` (Sphinx 8.1.3) - six of its documents, its globalcontext.json and its
 * objects.inv as written. RealOutputTest reads the whole output.
 */
final class SphinxSourceTest extends TestCase
{
    use SourceContractTests;

    protected function source(): SourceInterface
    {
        return (new SphinxSourceFactory())->create(['dir' => __DIR__.'/Fixtures/requests']);
    }

    protected function documentsSymbols(): bool
    {
        return true;
    }

    public function testTheDocumentsAreReadInTheToctreesOrder(): void
    {
        $source = (new SphinxSourceFactory())->create(['dir' => __DIR__.'/Fixtures/requests']);

        self::assertSame('Requests: HTTP for Humans™', $source->home()?->title);
        self::assertSame('docs/index.rst', $source->home()?->source);
        self::assertSame(['user/install', 'user/quickstart', 'user/advanced'], \array_slice(array_map(static fn (Node $n): string => $n->path, $source->tree()), 0, 3));
    }

    public function testTheBodyFollowsOmnidocsConventions(): void
    {
        $source = (new SphinxSourceFactory())->create(['dir' => __DIR__.'/Fixtures/requests']);
        $links = new RecordingLinker();
        $home = (string) $source->page('', $links)?->html();
        $quickstart = (string) $source->page('user/quickstart', $links)?->html();

        self::assertStringNotContainsString('<h1', $home, 'the title is printed by the page');
        self::assertStringContainsString('<a class="reference internal" href="/user/install#install">', $home, 'a link to a document goes to its page');
        self::assertStringContainsString('<blockquote class="doc-callout doc-callout-note" data-label="Note">', $quickstart, 'an admonition is a callout');
        self::assertMatchesRegularExpression('#<div class="doc-code"( data-lang="[a-z]+")?><pre class="notranslate"><code[^>]*><span class="gp">&gt;&gt;&gt; </span>#', $quickstart, 'highlighted code in its frame, its colours kept');
        self::assertStringNotContainsString('_modules/', (string) $source->page('api', $links)?->html(), 'no link to the module\'s code');
    }

    public function testTheInventorysEntriesAreTheApi(): void
    {
        $source = (new SphinxSourceFactory())->create(['dir' => __DIR__.'/Fixtures/requests']);
        $get = null;
        foreach ($source->symbols() as $symbol) {
            $get = 'requests.get' === $symbol->name ? $symbol : $get;
        }

        self::assertNotNull($get);
        self::assertSame('py:function', $get->kind);
        self::assertSame('api', $get->page);
        self::assertSame('requests.get', $get->anchor);
        self::assertSame('requests.get(url, params=None, **kwargs)', $get->signature);
        self::assertSame('Sends a GET request.', $get->summary);
        self::assertSame(['project' => 'Requests', 'version' => '2.32.3'], Inventory::header(__DIR__.'/Fixtures/requests/objects.inv'));
        self::assertCount(223, Inventory::read(__DIR__.'/Fixtures/requests/objects.inv'));
    }
}
