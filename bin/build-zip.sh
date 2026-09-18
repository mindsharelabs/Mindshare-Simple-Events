#!/usr/bin/env bash
# Build dist/Mindshare-Simple-Events.zip from the committed HEAD.
#
# Uses git archive, so only committed files are included, minus everything
# marked export-ignore in .gitattributes. Uncommitted changes are refused
# rather than silently left out.
set -euo pipefail

cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
    echo "Commit or stash your changes first; the zip is built from HEAD." >&2
    exit 1
fi

name="Mindshare-Simple-Events"
mkdir -p dist
git archive --format=zip --prefix="$name/" --output="dist/$name.zip" HEAD
echo "Built dist/$name.zip ($(git rev-parse --short HEAD))"
