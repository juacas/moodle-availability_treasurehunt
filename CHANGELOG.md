# Changelog

All notable changes to the Treasurehunt availability condition are documented here.

## 1.0.6 — 2026-11-03

- Handle conditions that reference a missing, deleted, or unrelated stage safely. They now evaluate as **false**.
- Show students a clear message when a required stage is unavailable, and show teachers a configuration warning.
- Synchronize the CI workflow with the main Treasure Hunt plugin.
- Mark the release as stable.

## 1.0.5

- Treat a deleted Treasure Hunt activity as an unavailable condition instead of failing during evaluation.

## 1.0.4

- Update links to Treasure Hunt activities when activities are restored.

## 1.0.3

- Improve AJAX calls and stage and time condition handling.

## 1.0.2

- Implement the Moodle privacy API and include minor fixes.

## 1.0.1

- Add GitHub Actions workflow support.

## 1.0.0

- Initial release of the Treasurehunt availability condition.
