# Changelog

Every version of omnidocs/sphinx, newest first ([Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
[Semantic Versioning](https://semver.org/)).

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/omnidocs` `^1.0` with no stability flag
(omnisense/contracts `^1.0` for its tests only), so `composer require omnidocs/sphinx` installs it in a
project with Composer's default minimum-stability; the 1.x series follows semantic versioning.
A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

### Added

- Its usage policy, in omnisense/contracts' schema: `policy.json` (named in `composer.json`,
  `extra.omnisense.policy`, so that `omnisense-policies` exports it): no third party - the source
  reads files and calls nobody -, no limit, no key; the manual's licence is the documented
  project's; the notes name the libraries' or the tool's licence. Verified 2026-11-10.
  omnisense/contracts is a development dependency only (`Tests/PolicyTest.php` reads the file
  through it): nothing of the package's code needs it.
- The source, its factory, omnidocs' conformance suite on a real tool's output.

### Changed

- For 1.0.0: `omnisense/omnidocs: ^1.0` and `omnisense/contracts: ^1.0` without `@dev`; `ci.yml`
  callable (`workflow_call`) and pinned on glitchr-studio/ci 133c7ed, `release.yml`
  (glitchr-studio/ci's `php-release.yml`); the README opens on the install line, a first use and what it
  needs (Python and Sphinx are the documented project's tools, not dependencies; its tests read their
  committed output).
- Requires `omnisense/omnidocs`, the core's new name (Omniscient is now Omnisense); the repository is `glitchr-omnisense/omnidocs-sphinx`.
