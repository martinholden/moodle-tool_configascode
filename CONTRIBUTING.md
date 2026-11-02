# Contributing

Thanks for helping improve `tool_configascode`.

## Development workflow

1. Create a new branch for your work.
2. Make your changes.
3. Test locally against the Moodle version you have installed.
4. Push your branch to GitHub.
5. Run `Moodle Plugin CI` manually on your branch as often as needed, selecting the version of Moodle to run.
6. Open a pull request when the branch is ready.
7. Let GitHub run `Moodle Plugin CI` automatically on the pull request.
8. Merge the pull request into `main` once all checks pass.
9. Create and push a new `v*` tag from `main` when you are ready to cut a release.
10. `moodle-ci.yml` runs automatically for that tag.
11. If tag CI passes, `moodle-onrelease.yml` runs automatically to generate the release changelog and release metadata.

## Pull requests

- Use the pull request template in `.github/PULL_REQUEST_TEMPLATE.md`.
- Add the label that matches the change so the release changelog is grouped correctly.
- Keep the PR title clear and descriptive.

## Labels used by release notes

- `docs` / `documentation` for documentation changes
- `feature` / `enhancement` for new functionality or improvements
- `bug` / `fix` for bug fixes

## Issues

- Use the issue templates in `.github/ISSUE_TEMPLATE/`.
- Choose the template that best matches the work you want to report or request.

## Release flow

Releases are generated from tagged commits on `main` after CI passes. You do not need to run release automation manually for normal contributions.
