<?php

namespace Omnidocs\Sphinx;

use Omnidocs\Exception\SourceException;

/**
 * A Sphinx inventory (objects.inv, version 2): what a project lets others
 * cross-reference - each object's name, its domain and role ("py:function"),
 * its priority, its address ("api/#requests.get", "$" standing for the name)
 * and the name shown ("-": the name itself).
 */
final class Inventory
{
    /** @return list<array{name: string, role: string, priority: int, uri: string, display: string}> */
    public static function read(string $file): array
    {
        $data = (string) @file_get_contents($file);
        $lines = explode("\n", $data, 5);
        if (\count($lines) < 5 || !str_starts_with($lines[0], '# Sphinx inventory version 2')) {
            throw new SourceException(\sprintf('%s is no Sphinx inventory of version 2.', $file));
        }
        $body = @zlib_decode($lines[4]);
        if (false === $body) {
            throw new SourceException(\sprintf('%s: its compressed part does not decompress.', $file));
        }
        $entries = [];
        foreach (explode("\n", $body) as $line) {
            // name domain:role priority uri dispname - a name may hold spaces, the rest may not (but dispname).
            if (!preg_match('/^(.+?)\s+(\S+:\S+)\s+(-?\d+)\s+(\S*)\s+(.*)$/', $line, $m)) {
                continue;
            }
            $uri = str_ends_with($m[4], '$') ? substr($m[4], 0, -1).$m[1] : $m[4];
            $entries[] = ['name' => $m[1], 'role' => $m[2], 'priority' => (int) $m[3], 'uri' => $uri, 'display' => '-' === $m[5] ? $m[1] : $m[5]];
        }

        return $entries;
    }

    /** @return array{project: string, version: string} */
    public static function header(string $file): array
    {
        $head = (string) @file_get_contents($file, false, null, 0, 512);

        return [
            'project' => preg_match('/^# Project: (.*)$/m', $head, $p) ? trim($p[1]) : '',
            'version' => preg_match('/^# Version: (.*)$/m', $head, $v) ? trim($v[1]) : '',
        ];
    }
}
