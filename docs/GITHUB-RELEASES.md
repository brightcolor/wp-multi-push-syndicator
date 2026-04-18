# GitHub Releases and Auto Updates

## How updater works

`WMPS\\Update\\GithubUpdater`:

1. Reads `owner/repo` from plugin settings.
2. Calls `https://api.github.com/repos/{owner}/{repo}/releases/latest`.
3. Compares release `tag_name` with plugin `WMPS_VERSION`.
4. Uses first `.zip` release asset as install package.
5. Registers update payload via `pre_set_site_transient_update_plugins`.

## Release packaging recommendation

- Include plugin root folder in ZIP (`wp-multi-push-syndicator/...`).
- Ensure plugin main file is at ZIP root folder level.

## Suggested Release Workflow

1. Bump version in:
   - plugin header
   - `WMPS_VERSION`
   - `CHANGELOG.md`
2. Create Git tag `vX.Y.Z`.
3. Build release ZIP.
4. Publish GitHub release with ZIP asset.

## Example GitHub Actions Workflow

See `.github/workflows/release.yml`.

## Notes

- Keep pre-releases out of stable channel if not intended for production.
- Add authentication (token) if API rate limits become an issue.