# Changelog

Every version of omnidocs/sphinx, newest first ([Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
[Semantic Versioning](https://semver.org/)).

## Unreleased

### Added

- Its usage policy, in omnisense/contracts' schema: `policy.json` (named in `composer.json`,
  `extra.omnisense.policy`, so that `omnisense-policies` exports it): no third party - the source
  reads files and calls nobody -, no limit, no key; the manual's licence is the documented
  project's; the notes name the libraries' or the tool's licence. Verified 2026-11-10.
  omnisense/contracts is a development dependency only (`Tests/PolicyTest.php` reads the file
  through it): nothing of the package's code needs it.

### Changed
- Requires `omnisense/omnidocs`, the core's new name (Omniscient is now Omnisense); the repository is `glitchr-omnisense/omnidocs-sphinx`.

### Added
- The source, its factory, omnidocs' conformance suite on a real tool's output.
