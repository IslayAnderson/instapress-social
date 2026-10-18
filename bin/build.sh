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
# Both unpack to an instopress-social/ folder. Usage: bin/build.sh v1.26.0
set -euo pipefail

tag="${1:?usage: bin/build.sh <tag, e.g. v1.26.0>}"
ver="${tag#v}"
root="$(git rev-parse --show-toplevel)"
src="plugin/instopress-social"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

mkdir -p "$root/dist"
pro="$root/dist/instopress-social-$ver.zip"
free="$root/dist/instopress-social-wporg-$ver.zip"
rm -f "$pro" "$free"

# Pro: the tagged plugin folder as it is.
git -C "$root" archive --format=zip --prefix=instopress-social/ -o "$pro" "$tag:$src"

# WordPress.org edition.
git -C "$root" archive --format=tar --prefix=instopress-social/ "$tag:$src" | tar -x -C "$tmp"
plugin="$tmp/instopress-social"
rm -rf "$plugin/includes/pro"
perl -ni -e 'print unless /^\s*\*\s*Update URI:/' "$plugin/instopress-social.php"
perl -pi -e 's/^(\s*\*\s*Plugin Name:\s*)instopress-social Pro\s*$/$1instopress-social\n/' "$plugin/instopress-social.php"
perl -ni -e 'print unless /\(Pro\)\s*$/' "$plugin/readme.txt"

# Sanity checks: the free build must not carry any Pro pieces.
if [ -e "$plugin/includes/pro" ] || grep -q 'Update URI' "$plugin/instopress-social.php" || grep -q 'Plugin Name:.*Pro' "$plugin/instopress-social.php"; then
	echo "WordPress.org build still contains Pro parts" >&2
	exit 1
fi
(cd "$tmp" && zip -qrX "$free" instopress-social)

echo "Pro:           $pro"
echo "WordPress.org: $free"
