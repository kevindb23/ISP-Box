# Repository Guidelines

## Project Structure

NexusBox is a PHP 8.1+ operations portal. Application modules live under `app/Modules/`, with each module typically containing `Routes/`, controllers, services, repositories, validators, views, and module-specific `Assets/css` and `Assets/js`. Shared framework code is in `framework/`; cross-module infrastructure is in `app/Core/` and `app/Infrastructure/`. Global layouts and UI components are under `app/UI/`. Static assets are in `public/assets/`, database migrations and tools are in `database/`, and architecture/security checks are in `tests/`.

## Development Commands

- `composer install` — install PHP dependencies and generate the Composer autoloader.
- `composer test` — run the complete architecture, integration, frontend-contract, and security test suite.
- `php -l path/to/file.php` — check PHP syntax for a changed file.
- Serve the project through the configured PHP web server and verify module routes such as `/subscribers` or `/vlan-management`.

## Coding Style

Use PSR-4 namespaces: `App\\Modules\\ModuleName\\...` and `Framework\\...`. Follow the existing PHP style with four-space indentation, braces on their own lines, strict validation, and descriptive method names. Keep module CSS scoped to its page root and prefer shared `public/assets/css/nx.css` tokens over hardcoded colors, spacing, typography, or component rules. Use DM Sans/global typography and `--nx-font-size-table-head` for table headers. Preserve existing API, route, authorization, and data contracts.

## Testing Guidelines

Add or update focused architecture/security tests under `tests/Architecture/` or `tests/Security/` when behavior or contracts change. Use descriptive names such as `nap_nodes_table_contract_test.php`. Run `composer test` before submitting changes, plus targeted `php -l` checks for modified PHP files.

## Commits and Pull Requests

Use concise imperative commit subjects, such as `Standardize module table UI`. Pull requests should explain affected modules, user-visible behavior, tests run, and migration or configuration impact. Include screenshots for UI changes in both relevant themes and note responsive verification. Keep generated Markdown, notes, cache, and temporary files under `public/assets/.doc/`.
