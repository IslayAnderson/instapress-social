#!/usr/bin/env bash
# Build both editions of the plugin from a git tag:
#
#   dist/instopress-social-X.Y.Z.zip        instopress-social Pro (GitHub Release asset).
#                                           Installed copies update from this exact name,
#                                           so it must always be the Pro build.
#   dist/instopress-social-wporg-X.Y.Z.zip  the WordPress.org edition: no includes/pro/
#                                           (bot protection, GitHub updater), no Update URI
#                                           header, readme lines marked "(Pro)" removed.
#
# Both unpack to an instopress-social/ folder, and both get minified copies of the
# CSS and JS (style.min.css, app.min.js, …) next to the readable originals; the plugin
# serves the .min files unless SCRIPT_DEBUG is on (iar_asset_url()).
#
# Needs esbuild from this repo's package.json: run `npm ci` once.
# Usage: bin/build.sh v1.26.0
set -euo pipefail

tag="${1:?usage: bin/build.sh <tag, e.g. v1.26.0>}"
ver="${tag#v}"
root="$(git rev-parse --show-toplevel)"
src="plugin/instopress-social"
esbuild="$root/node_modules/.bin/esbuild"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

if [ ! -x "$esbuild" ]; then
	echo "esbuild is missing: run 'npm ci' in $root first" >&2
	exit 1
fi

mkdir -p "$root/dist"
pro="$root/dist/instopress-social-$ver.zip"
free="$root/dist/instopress-social-wporg-$ver.zip"
rm -f "$pro" "$free"

# The tagged plugin folder, plus minified CSS/JS.
mkdir -p "$tmp/pro"
git -C "$root" archive --format=tar --prefix=instopress-social/ "$tag:$src" | tar -x -C "$tmp/pro"
find "$tmp/pro/instopress-social/assets" -type f \( -name '*.js' -o -name '*.css' \) ! -name '*.min.*' | while read -r file; do
	"$esbuild" "$file" --minify --log-level=warning --outfile="${file%.*}.min.${file##*.}"
done

# Pro: the plugin as it is.
(cd "$tmp/pro" && zip -qrX "$pro" instopress-social)

# WordPress.org edition: the same, minus the Pro parts.
mkdir -p "$tmp/free"
cp -R "$tmp/pro/instopress-social" "$tmp/free/"
plugin="$tmp/free/instopress-social"
rm -rf "$plugin/includes/pro"
perl -ni -e 'print unless /^\s*\*\s*Update URI:/' "$plugin/instopress-social.php"
perl -pi -e 's/^(\s*\*\s*Plugin Name:\s*)instopress-social Pro\s*$/$1instopress-social\n/' "$plugin/instopress-social.php"
perl -ni -e 'print unless /\(Pro\)\s*$/' "$plugin/readme.txt"

# Sanity checks: the free build must not carry any Pro pieces.
if [ -e "$plugin/includes/pro" ] || grep -q 'Update URI' "$plugin/instopress-social.php" || grep -q 'Plugin Name:.*Pro' "$plugin/instopress-social.php"; then
	echo "WordPress.org build still contains Pro parts" >&2
	exit 1
fi
(cd "$tmp/free" && zip -qrX "$free" instopress-social)

echo "Pro:           $pro"
echo "WordPress.org: $free"
