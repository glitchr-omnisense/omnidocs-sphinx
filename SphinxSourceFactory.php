<?php

namespace Omnidocs\Sphinx;

use Omnidocs\Config;
use Omnidocs\Html\Conventions;
use Omnidocs\SourceFactory;
use Omnidocs\SourceInterface;

/**
 * The "sphinx" source:
 *
 *   dir      the output of `sphinx-build -b json <docs> <dir>`              required
 *   docs     the Sphinx source folder in the repository, for "edit this page"   "docs"
 *   exclude  documents left out (fnmatch patterns on their names)          "_modules/*"
 *   labels   the callouts' words
 */
final class SphinxSourceFactory extends SourceFactory
{
    public function getName(): string
    {
        return 'sphinx';
    }

    protected function populate(Config $config): void
    {
        $config->defaults(['docs' => 'docs', 'exclude' => ['_modules/*'], 'labels' => [], 'omnidocs.required_options' => ['dir']]);
    }

    protected function build(Config $config): SourceInterface
    {
        return new SphinxSource($config->directory('dir', $this->getName()), (string) $config->string('docs'), $config->list('exclude'), new Conventions($config->map('labels')));
    }
}
