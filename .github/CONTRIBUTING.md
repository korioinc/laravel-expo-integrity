# Contribution Guide

Thank you for considering contributing to Laravel Expo Integrity! Please review the following guidelines before submitting a pull request.

For significant changes, please open an issue first so we can discuss the approach.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit, and push
4. Open a pull request detailing your changes

## Guidelines

- Ensure the coding style passes by running `composer lint`.
- Send a coherent commit history, making sure each commit in your pull request is meaningful.
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- Please remember that we follow [SemVer](http://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Lint

Lint your code:

```bash
composer lint
```

## Tests

Run all tests:

```bash
composer test
```

## Releases

Release automation follows the changelog update flow used by
`korioinc/laravel-jwt-auth`. This package also provides a manual **Release**
workflow. It does not create or push tags automatically.

1. Merge the intended package changes into the repository's default branch.
2. Create and push a stable SemVer tag such as `v1.0.0` using the team's release process.
3. Open **Actions → Release → Run workflow** on the default branch.
4. Enter the existing tag. The workflow resolves its commit and runs the existing
   PHP/Laravel/OS/dependency matrix against that exact commit.
5. After all checks succeed, the workflow creates GitHub release notes for the
   verified tag and updates `CHANGELOG.md` on the default branch.

The tag must belong to the default branch history. Only stable `vMAJOR.MINOR.PATCH`
tags are accepted. The workflow rejects a tag whose object changes during checks;
restrict tag mutation through repository rules as well. A published stable release
for the same tag is reused, which lets a rerun retry a failed changelog update
without creating another release. The changelog updater additionally skips a tag
that already has a version heading,
so rerunning a successful update does not duplicate its entry. Existing
drafts/prereleases are not modified.

The workflow uses the repository's `GITHUB_TOKEN`, with read permission for
validation and write permission only for release/changelog jobs. No PAT or
Packagist API token is required by these files. The existing **Update Changelog**
workflow still handles releases published manually through GitHub. The release
workflow also calls it explicitly because releases created with `GITHUB_TOKEN`
do not trigger another Actions workflow through the `release` event.

Allow GitHub Actions to write repository contents if repository/organization
settings restrict that capability. The changelog workflow commits only
`CHANGELOG.md`; if branch protection disallows bot pushes, that step will fail
and needs the repository's approved changelog update process. Do not weaken branch
protection merely to make the job pass. A changelog failure does not roll back an
already published GitHub release.

Composer package versions come from Git tags, not a `version` field in
`composer.json`. GitHub Release creation is **not Packagist registration**.
Register `korioinc/laravel-expo-integrity` and enable its GitHub integration in
Packagist separately before expecting tagged versions to become installable.

Local workflow validation does not execute GitHub jobs or publish a release.
Full matrix execution and repository permission/Packagist integration must be
confirmed on GitHub during the authorized release process.
