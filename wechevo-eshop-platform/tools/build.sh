#!/bin/bash
# Builds a clean production package: dist/wechevo-site-php.zip
# Excludes runtime data, uploads, tests and tooling. Run from the project root.
set -euo pipefail
cd "$(dirname "$0")/.."
OUT=dist/wechevo-site-php.zip
mkdir -p dist
rm -f "$OUT"
for f in $(find . -name "*.php" -not -path "./dist/*"); do php -l "$f" > /dev/null; done
zip -qr "$OUT" index.php cron.php .htaccess README.md app assets \
  data/.htaccess data/.gitkeep uploads/.htaccess uploads/.gitkeep \
  -x "*.DS_Store"
echo "built $OUT ($(du -h "$OUT" | cut -f1))"
