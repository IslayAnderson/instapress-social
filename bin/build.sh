#!/usr/bin/env bash
# Build both editions of the plugin from a git tag:
#
#   dist/instapress-social-X.Y.Z.zip        instapress-social Pro (GitHub Release asset).
#                                           Installed copies update from this exact name,
#                                           so it must always be the Pro build.
#   dist/instapress-social-wporg-X.Y.Z.zip  the WordPress.org edition: no includes/pro/
#                                           (bot protection, GitHub updater), no Update URI
#                                           header, readme lines marked "(Pro)" removed.
#
# Both unpack to an instapress-social/ folder. Usage: bin/build.sh v1.26.0
set -euo pipefail

tag="${1:?usage: bin/build.sh <tag, e.g. v1.26.0>}"
ver="${tag#v}"
root="$(git rev-parse --show-toplevel)"
src="plugin/instapress-social"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

mkdir -p "$root/dist"
pro="$root/dist/instapress-social-$ver.zip"
free="$root/dist/instapress-social-wporg-$ver.zip"
rm -f "$pro" "$free"

# Pro: the tagged plugin folder as it is.
git -C "$root" archive --format=zip --prefix=instapress-social/ -o "$pro" "$tag:$src"

# WordPress.org edition.
git -C "$root" archive --format=tar --prefix=instapress-social/ "$tag:$src" | tar -x -C "$tmp"
plugin="$tmp/instapress-social"
rm -rf "$plugin/includes/pro"
perl -ni -e 'print unless /^\s*\*\s*Update URI:/' "$plugin/instapress-social.php"
perl -pi -e 's/^(\s*\*\s*Plugin Name:\s*)instapress-social Pro\s*$/$1instapress-social\n/' "$plugin/instapress-social.php"
perl -ni -e 'print unless /\(Pro\)\s*$/' "$plugin/readme.txt"

# Sanity checks: the free build must not carry any Pro pieces.
if [ -e "$plugin/includes/pro" ] || grep -q 'Update URI' "$plugin/instapress-social.php" || grep -q 'Plugin Name:.*Pro' "$plugin/instapress-social.php"; then
	echo "WordPress.org build still contains Pro parts" >&2
	exit 1
fi
(cd "$tmp" && zip -qrX "$free" instapress-social)

echo "Pro:           $pro"
echo "WordPress.org: $free"
