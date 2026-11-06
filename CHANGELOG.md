# Release Notes

## 2.0.0 - 2026-11-06

### Breaking Changes

- Read the shared signing key from `INTEGRITY_KEY` instead of `INTEGRITY_SECRET`.
- Rename the Expo environment variable in setup examples to `EXPO_PUBLIC_INTEGRITY_KEY`.

### Upgrade

- Rename the backend environment variable to `INTEGRITY_KEY`, keeping its value unchanged.
- Update any published `config/laravel-expo-integrity.php` to read `INTEGRITY_KEY`, then rebuild Laravel's configuration cache.
- Update the Expo environment variable and its reference in `expo-kit.config.ts` to `EXPO_PUBLIC_INTEGRITY_KEY`.
- The PHP `laravel-expo-integrity.secret` and Expo `integrity.secret` configuration fields and signature format remain unchanged.

## 1.0.1 - 2026-10-24

### Changed

- Use stable release tags without a `v` prefix, matching `korioinc/laravel-jwt-auth`.
- Update release validation and contributor instructions to require `MAJOR.MINOR.PATCH` tags.

## v1.0.0 - 2026-10-12

<!-- Release notes generated using configuration in .github/release.yml at v1.0.0 -->
### What's Changed

#### Other Changes

* chore: update CHANGELOG, composer.json, and README for initial implementation of Expo request signatures and replay protection by @jskorlol in https://github.com/korioinc/laravel-expo-integrity/pull/2

### New Contributors

* @jskorlol made their first contribution in https://github.com/korioinc/laravel-expo-integrity/pull/2

**Full Changelog**: https://github.com/korioinc/laravel-expo-integrity/commits/v1.0.0
