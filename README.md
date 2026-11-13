# omnidocs/sphinx

A Sphinx manual as an [omnidocs](https://github.com/glitchr-omnisense/omnidocs) manual, from its JSON
builder's output (`sphinx-build -b json docs out`) and its objects.inv:

- a page per document, in the order the root document's toctrees give (each document's "next");
- Sphinx's HTML brought to omnidocs' conventions: admonitions (note, tip, hint, important, warning,
  caution, danger, seealso) are callouts, versionadded / versionchanged / deprecated carry their
  version, a highlighted block is a code frame (Pygments' colouring kept), the title is printed by the
  page, links between documents go to their pages, pictures to the linker, viewcode's [source] links,
  scripts and styles left out;
- the inventory's entries (py:function, py:class, py:method... - not its std: labels) are the API
  entries, at their anchors, with their signature and first sentence read in the page.

## Installation and a first use

```sh
composer require omnidocs/sphinx
```

It reads what Sphinx's JSON builder writes (`sphinx-build -b json docs out`, with its `objects.inv`).
Sphinx runs on Python and is the documented project's tool, not a dependency: the package needs PHP
8.2 with ext-zlib, no network, no account. To try it without Sphinx, read the output of Requests'
documentation its tests use, installed with it:

```php
require __DIR__.'/vendor/autoload.php';

use Omnidocs\Model\Version;
use Omnidocs\Sphinx\SphinxSourceFactory;

$source = (new SphinxSourceFactory())->create(['dir' => __DIR__.'/vendor/omnidocs/sphinx/Tests/Fixtures/requests']);
$version = new Version('2.32', $source);
foreach ($version->pages() as $node) {
    echo $node->title, "\n";
}
echo count($version->symbols()), " API entries\n";
```

Its tests (`composer install` then `vendor/bin/phpunit` in a checkout, or every package at once in
[omnidocs' Docker harness](https://github.com/glitchr-omnisense/omnidocs/blob/1.x/docs/harness.md))
read that committed output: Python and Sphinx are not needed to run them. The harness's `sphinx`
service regenerates the whole of it with Sphinx 8.1.3.

With a project's own output:

```php
$manual = (new Omnidocs\Sphinx\SphinxSourceFactory())->create(['dir' => 'build/json', 'docs' => 'docs']);
```

| Option | Default | Meaning |
|---|---|---|
| `dir` | (required) | the JSON builder's output |
| `docs` | `docs` | the Sphinx source folder in the repository: "edit this page" |
| `exclude` | `_modules/*` | documents left out |
| `labels` | English | the callouts' words |

`Inventory::read('objects.inv')` reads any Sphinx inventory of version 2 (intersphinx's format).

## Verified, and not

- Verified (omnidocs' harness, PHP 8.4): omnidocs' conformance suite on Requests' documentation
  (psf/requests v2.32.3) built by Sphinx 8.1.3's JSON builder - six of its documents as fixtures, the
  whole output (15 documents, 167 API entries) with `omnidocs real`; `BareTest`.
- Not verified: other domains than Python's (cpp:, js:) beyond their names; themes' extra context;
  MyST (Markdown) projects; math (MathJax is a script, left out: formulas show as their source).

Licence: MIT.
