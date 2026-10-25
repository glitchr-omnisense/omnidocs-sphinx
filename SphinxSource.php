<?php

namespace Omnidocs\Sphinx;

use Omnidocs\Exception\SourceException;
use Omnidocs\Html\Conventions;
use Omnidocs\Html\Headings;
use Omnidocs\Html\Links;
use Omnidocs\Html\Text;
use Omnidocs\LinkerInterface;
use Omnidocs\Model\Node;
use Omnidocs\Model\Page;
use Omnidocs\Model\Rendered;
use Omnidocs\Model\Symbol;
use Omnidocs\SourceInterface;
use Omnidocs\Support\Path;

/**
 * A Sphinx manual from its JSON builder's output (`sphinx-build -b json docs
 * out`): a page per .fjson document (its title, its body), the contents in
 * the order the root document's toctrees give (each document's "next"), the
 * API entries of objects.inv at their anchors - with their signature and
 * their first sentence read in the page.
 *
 * The body is Sphinx's HTML, brought to omnidocs' conventions: its
 * admonitions are callouts (note, tip, warning, seealso...), versionadded,
 * versionchanged and deprecated carry their version, a highlighted block is
 * a code frame (Pygments' colouring kept), the title is printed by the page.
 * Its links between documents go to their pages, its pictures (_images/) to
 * the linker's image(); scripts and styles are left out.
 *
 *   new SphinxSource('/build/json', docs: 'docs');
 */
final class SphinxSource implements SourceInterface
{
    private const SPECIAL = ['genindex', 'search', 'py-modindex', 'modindex'];

    private const ADMONITIONS = ['note' => 'note', 'tip' => 'tip', 'hint' => 'tip', 'important' => 'important', 'warning' => 'warning', 'caution' => 'caution', 'attention' => 'warning', 'danger' => 'caution', 'error' => 'caution', 'seealso' => 'seealso'];

    /** @var array<string, array<string, mixed>>|null by document name, in reading order */
    private ?array $documents = null;

    /** @var list<Symbol>|null */
    private ?array $symbols = null;

    private readonly Conventions $conventions;

    /**
     * @param string       $dir     the JSON builder's output folder
     * @param string       $docs    the Sphinx source folder in the repository ("docs"): "edit this page"
     * @param list<string> $exclude documents left out (fnmatch patterns on their names: "_modules/*")
     */
    public function __construct(
        private readonly string $dir,
        private readonly string $docs = 'docs',
        private readonly array $exclude = ['_modules/*'],
        ?Conventions $conventions = null,
    ) {
        $this->conventions = $conventions ?? new Conventions();
    }

    public function home(): ?Node
    {
        $root = $this->root();
        $documents = $this->documents();
        if (!isset($documents[$root])) {
            return null;
        }

        return new Node('', self::title($documents[$root]), $this->sourceOf($documents[$root]), 0);
    }

    public function tree(): array
    {
        $tree = [];
        $i = 0;
        foreach ($this->documents() as $name => $document) {
            if ($name !== $this->root()) {
                $tree[] = new Node($name, self::title($document), $this->sourceOf($document), ++$i);
            }
        }

        return $tree;
    }

    public function page(string $path, ?LinkerInterface $links = null): ?Page
    {
        $name = '' === $path ? $this->root() : $path;
        if ('' !== $path && $path === $this->root()) {
            return null;
        }
        $document = $this->documents()[$name] ?? null;
        if (null === $document) {
            return null;
        }
        $html = $this->body((string) ($document['body'] ?? ''), $name, $links);
        $title = self::title($document);

        return new Page($path, $title, new Rendered($html, $title, Headings::of($html, 2, 4), [], Text::of($html)), $this->sourceOf($document), array_values(array_filter($this->symbols(), static fn (Symbol $s): bool => $s->page === $path)));
    }

    public function symbols(): array
    {
        if (null !== $this->symbols) {
            return $this->symbols;
        }
        $file = $this->dir.'/objects.inv';
        if (!is_file($file)) {
            return $this->symbols = [];
        }
        $symbols = [];
        $this->documents();
        foreach (Inventory::read($file) as $entry) {
            // std:label and std:doc are the document's own cross-reference targets, not an API.
            if (str_starts_with($entry['role'], 'std:')) {
                continue;
            }
            [$target, $anchor] = Path::split($entry['uri']);
            $name = $this->documentOf($target, '');
            if (null === $name) {
                continue;
            }
            $path = $name === $this->root() ? '' : $name;
            $body = (string) ($this->documents()[$name]['body'] ?? '');
            [$signature, $summary] = self::describe($body, $anchor);
            $symbols[] = new Symbol($entry['role'], $entry['name'], $path, $anchor, $summary, $signature);
        }

        return $this->symbols = $symbols;
    }

    /** @return array<string, array<string, mixed>> */
    private function documents(): array
    {
        if (null !== $this->documents) {
            return $this->documents;
        }
        if (!is_dir($this->dir) || !is_file($this->dir.'/globalcontext.json')) {
            throw new SourceException(\sprintf('%s is no output of Sphinx\'s JSON builder (no globalcontext.json).', $this->dir));
        }
        $all = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || 'fjson' !== $file->getExtension()) {
                continue;
            }
            $name = substr($file->getPathname(), \strlen($this->dir) + 1, -6);
            if (\in_array($name, self::SPECIAL, true) || $this->excluded($name)) {
                continue;
            }
            $document = json_decode((string) file_get_contents($file->getPathname()), true);
            if (!\is_array($document) || !isset($document['body'])) {
                throw new SourceException(\sprintf('%s.fjson is not a document of Sphinx\'s JSON builder.', $name));
            }
            $all[$name] = $document;
        }
        // Reading order: from the root document along each one's "next", then whatever is left, by name.
        $ordered = [];
        $name = $this->root();
        while (null !== $name && isset($all[$name]) && !isset($ordered[$name])) {
            $ordered[$name] = $all[$name];
            $next = $all[$name]['next']['link'] ?? null;
            $name = \is_string($next) ? $this->documentOf($next, $name, $all) : null;
        }
        ksort($all);

        return $this->documents = $ordered + $all;
    }

    private function root(): string
    {
        static $cache = [];
        if (!isset($cache[$this->dir])) {
            $context = is_file($this->dir.'/globalcontext.json') ? json_decode((string) file_get_contents($this->dir.'/globalcontext.json'), true) : [];
            $cache[$this->dir] = \is_array($context) ? (string) ($context['root_doc'] ?? $context['master_doc'] ?? 'index') : 'index';
        }

        return $cache[$this->dir];
    }

    /**
     * The document a link of $from's body names ("../advanced/#x" from "user/quickstart" is "user/advanced"); null
     * when it is none of the documents read.
     *
     * @param array<string, array<string, mixed>>|null $documents
     */
    private function documentOf(string $target, string $from, ?array $documents = null): ?string
    {
        $documents ??= $this->documents ?? [];
        // The JSON builder's addresses: the root document at "", any other at "<name>/".
        $base = '' === $from || $from === $this->root() ? '' : $from.'/';
        $resolved = Path::normalise($base.$target);
        if (null === $resolved) {
            return null;
        }
        $resolved = trim($resolved, '/');
        foreach (['' === $resolved ? $this->root() : $resolved, $resolved.'/index', preg_replace('/\.html$/', '', $resolved)] as $candidate) {
            if (\is_string($candidate) && isset($documents[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private function body(string $html, string $name, ?LinkerInterface $links): string
    {
        // What the page prints itself (its title), or never runs (scripts, styles).
        $html = (string) preg_replace(['#<h1\b[^>]*>.*?</h1>#s', '#<(script|style)\b.*?</\1>#si'], '', $html, 1);
        $html = (string) preg_replace('#<(script|style)\b.*?</\1>#si', '', $html);
        // Admonitions as callouts.
        $html = (string) preg_replace_callback('#<div class="admonition(?:-[\w-]+)? ?([\w -]*)">\s*<p class="admonition-title">(.*?)</p>(.*?)</div>#s', function (array $m): string {
            $type = 'note';
            foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $class) {
                $type = self::ADMONITIONS[$class] ?? $type;
            }

            return '<blockquote class="doc-callout doc-callout-'.$type.'" data-label="'.Conventions::escape(Text::of($m[2])).'">'.trim($m[3]).'</blockquote>';
        }, $html);
        // versionadded, versionchanged, deprecated: their version in their label.
        $html = (string) preg_replace_callback('#<div class="(versionadded|versionchanged|deprecated)">\s*<p><span class="versionmodified[^"]*">(.*?)</span>(.*?)</div>#s', function (array $m): string {
            $type = 'versionchanged' === $m[1] ? 'versionadded' : $m[1];
            $version = preg_match('/(\d+(?:\.\d+)+)/', Text::of($m[2]), $v) ? $v[1] : null;
            $attributes = $this->conventions->calloutAttributes($type, $version);
            $attributes['data-label'] = rtrim(Text::of($m[2]), ': ');

            return '<blockquote '.Conventions::attributes($attributes).'><p>'.trim($m[3]).'</blockquote>';
        }, $html);
        // viewcode's [source] links: the module's code is not part of the manual.
        $html = (string) preg_replace('#<a class="reference internal" href="[^"]*_modules/[^"]*">.*?</a>#s', '', $html);
        // Highlighted code: one frame, Pygments' spans kept.
        $html = (string) preg_replace_callback('#<div class="highlight-([\w+-]+) notranslate"><div class="highlight"><pre>(?:<span></span>)?(.*?)</pre></div>\s*</div>#s', fn (array $m): string => $this->conventions->code(rtrim($m[2], "\n"), 'default' === $m[1] ? null : $m[1]), $html);
        // The links: a document's to its page, a picture to the linker's image(), the source pages (_modules) dropped.
        return Links::rewrite($html, function (string $url, bool $image) use ($name, $links): ?string {
            if (Path::isAbsolute($url)) {
                return null;
            }
            [$target, $fragment] = Path::split($url);
            if ($image) {
                $file = Path::resolve('' === $name || $name === $this->root() ? 'x' : $name.'/x', $target);

                return null !== $file && null !== $links ? $links->image($file) : null;
            }
            $document = $this->documentOf($target, $name);
            if (null === $document) {
                return null === $links ? null : '#'; // the index pages, which a manual of its own does not need
            }
            if (null === $links) {
                return null;
            }

            return $links->page($document === $this->root() ? '' : $document, $fragment);
        });
    }

    /**
     * A definition's signature and first sentence, read in the body: <dt id="requests.get">...</dt><dd><p>...</p>.
     *
     * @return array{?string, string}
     */
    private static function describe(string $body, ?string $anchor): array
    {
        if (null === $anchor || !preg_match('#<dt\b[^>]*\sid="'.preg_quote($anchor, '#').'"[^>]*>(.*?)</dt>\s*<dd>\s*(?:<p>(.*?)</p>)?#s', $body, $m)) {
            return [null, ''];
        }
        $signature = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('#<a\b[^>]*class="[^"]*(?:headerlink|reference internal)[^"]*"[^>]*>.*?</a>#s', '', $m[1])), \ENT_QUOTES | \ENT_HTML5)));
        $summary = Text::of($m[2] ?? '');
        if (preg_match('/^(.+?[.!?])(?:\s|$)/u', $summary, $s)) {
            $summary = $s[1];
        }

        return ['' !== $signature ? $signature : null, $summary];
    }

    private static function title(array $document): string
    {
        return Text::of((string) ($document['title'] ?? '')) ?: 'Untitled';
    }

    private function sourceOf(array $document): ?string
    {
        $name = $document['sourcename'] ?? null;

        return \is_string($name) ? Path::normalise(('' !== $this->docs ? trim($this->docs, '/').'/' : '').preg_replace('/\.txt$/', '', $name)) : null;
    }

    private function excluded(string $name): bool
    {
        foreach ($this->exclude as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
