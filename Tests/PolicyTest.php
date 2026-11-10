<?php

namespace Omnidocs\Sphinx\Tests;

use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/** Its usage policy, as data: a source reads files and calls nobody, so nothing is limited. */
final class PolicyTest extends TestCase
{
    public function testThePackageDeclaresNoThirdParty(): void
    {
        $policy = UsagePolicy::fromFile(__DIR__.'/../policy.json');

        self::assertSame('sphinx', $policy->provider, 'named after the factory');
        self::assertSame([], $policy->rateLimits, 'no service called: no limit');
        self::assertFalse($policy->requirements->key);
        self::assertNull($policy->rights->licence, 'the manual\'s licence is the documented project\'s');
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertMatchesRegularExpression('~"omnisense": \{\s*"policy": "policy\.json"~', (string) file_get_contents(__DIR__.'/../composer.json'), 'composer.json names it');
    }
}
