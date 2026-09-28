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

## [5.0.0] - unreleased
This is a breaking release. See the [upgrade path in the README](README.md#upgrade-path-to-5yz-release-for-php-84-from-4yz) before upgrading.

### Added
- Utils::checkConfiguration($machine) as per request [#7](https://github.com/rolfvreijdenberger/izzum-statemachine/issues/7)
- native php type hints for parameters, properties and return types throughout the library
- documentation for generating plantuml state diagrams, which was previously an empty 'TO DESCRIBE' section

### Changed
- **breaking**: requires php 8.4. The previous requirement was php >= 5.3.3
- **breaking**: all namespaces are now PascalCase (`izzum\statemachine\persistence` becomes `Izzum\StateMachine\Persistence`). This affects `use` statements in your code *and* any fully qualified rule/command/callable/factory names stored as strings in your database, yaml, xml or json configuration. Those strings are resolved by autoloading at runtime, so a stale name fails only when that transition is first used. Match the casing exactly: a partially corrected name such as `Izzum\rules\TrueRule` resolves on a case-insensitive filesystem (macOS) but fails on a case-sensitive one
- **breaking**: interface and abstract method signatures now declare native return types (`Loader::load(): int`, `ICommand::execute(): void`, `ICommand::toString(): string`, `IRule::applies(): bool`, `Command::_execute(): void`, and the `Adapter` hooks). Php enforces return type covariance, so your implementations and subclasses must declare matching types
- **breaking**: protected properties renamed from snake_case to camelCase (for example `State::$command_entry_name` is now `State::$commandEntryName`). This only affects code that subclasses `State`, `Transition`, `Context`, `Identifier` or an `Adapter`
- modernized to php 8.4 idioms, including constructor property promotion and `::class` resolution
- the test suite now runs on Codeception instead of PHPUnit directly, via `composer test`
- test directories renamed to match the namespaces they declare
- optimized some code by moving it to utils class

### Removed
- **breaking**: the MongoDB persistence adapter. It targeted the long deprecated ext-mongo driver (`MongoClient`), which has no build for any supported php version, and its tests could never run. Use the Redis or PDO adapter instead
- Scrutinizer and Travis CI configuration, neither of which was still in use

### Fixed
- `Identifier::setEntityId()` and `getEntityId()` read and wrote an undeclared dynamic property instead of the declared `$entityId`, which is deprecated as of php 8.2
- `PDO::load()` discarded the transition count returned by its delegate loader, breaking the `Loader::load(): int` contract
- the YAML loader passed `false` to `yaml_parse()`'s `$pos` parameter, which expects an int
- `State::setEntryCommandName()` and `setExitCommandName()` passed null straight to `trim()`, which the JSON and YAML loaders trigger for any state that defines callables but no commands
- `Transition::setRuleName()` and `setCommandName()` had the same `trim(null)` problem
- `State::setType()` read `$type` before the constructor had assigned it
- unreachable and dead code in `PDO::processGetState()` and `Transition::isTriggeredBy()`
- README code examples that could not run as written, including a regex-state example that passed an event name into `addTransition()`'s boolean parameter and a closure guard declaring a second `$event` argument that is never passed

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



[Unreleased]: https://github.com/rolfvreijdenberger/izzum-statemachine/compare/5.0.0...HEAD
[5.0.0]: https://github.com/rolfvreijdenberger/izzum-statemachine/compare/4.0.0...5.0.0
[4.0.0]: https://github.com/rolfvreijdenberger/izzum-statemachine/compare/3.2.3...4.0.0
[3.2.3]: https://github.com/rolfvreijdenberger/izzum-statemachine/compare/3.2.2...3.2.3
