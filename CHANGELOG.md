# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
- nothing

### Changed
- nothing

### Fixed
- nothing

## [4.0.0] - 2016-06-10
### Added
- CHANGELOG.md as defined by [Oliver LaCan](https://raw.githubusercontent.com/olivierlacan/keep-a-changelog/master/CHANGELOG.md)
- different phpunit bootstrap.xml files for different php versions to exclude tests that only make sense for php versions < 7

### Changed
- made the code php 7 compatible which is a breaking change. Removed reserved words from class names as per [the php 7 upgrade documentation](https://secure.php.net/manual/en/migration70.incompatible.php#migration70.incompatible.other.classes)
- changed the travis.yml file so the builds have the right dependencies and use the correct bootstrap files for phpunit
- README.md with an update path from 3.y.z version to 4.0.0

### Fixed
- incorrect method signatures in tests

## [3.y.z] - 2015-10-19 and older
### Changed
- did not maintain a changelog



[Unreleased]: https://github.com/rolfvreijdenberger/izzum-statemachine/compare/4.0.0...HEAD
[4.0.0]: https://github.com/rolfvreijdenberger/izzum-statemachine/compare/3.2.3...4.0.0
[3.2.3]: https://github.com/rolfvreijdenberger/izzum-statemachine/compare/3.2.2...3.2.3
