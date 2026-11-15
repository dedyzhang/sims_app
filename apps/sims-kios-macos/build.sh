#!/bin/bash
set -euo pipefail

source_dir="$(cd "$(dirname "$0")" && pwd)"
output_dir="${1:-$source_dir/build}"
app="$output_dir/SIMS Kios.app"
survey_url="${SIMS_SURVEY_URL:-http://127.0.0.1:8765/survei-pembelajaran/masuk/01a1d329-5f78-70dd-be29-4341b81b232f}"
sdk="$(xcrun --sdk macosx --show-sdk-path)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

for arch in x86_64 arm64; do
    xcrun swiftc -O -sdk "$sdk" -target "$arch-apple-macos12.0" \
        "$source_dir/main.swift" "$source_dir/DeviceKey.swift" -o "$work/$arch"
done

mkdir -p "$app/Contents/MacOS"
xcrun lipo -create "$work/x86_64" "$work/arm64" -output "$app/Contents/MacOS/SIMS-Kios"
cp "$source_dir/Info.plist" "$app/Contents/Info.plist"
/usr/libexec/PlistBuddy -c "Add :SIMSSurveyURL string $survey_url" "$app/Contents/Info.plist"

if [[ -n "${SIMS_KIOS_SIGN_IDENTITY:-}${SIMS_KIOS_PROFILE:-}" ]]; then
    : "${SIMS_KIOS_SIGN_IDENTITY:?Tentukan identitas penandatanganan}"
    : "${SIMS_KIOS_PROFILE:?Tentukan profil provisioning}"
    cp "$SIMS_KIOS_PROFILE" "$app/Contents/embedded.provisionprofile"
    codesign --force --options runtime --entitlements "$source_dir/Assessment.entitlements" \
        --sign "$SIMS_KIOS_SIGN_IDENTITY" "$app"
else
    if [[ -e "$app/Contents/embedded.provisionprofile" ]]; then
        rm "$app/Contents/embedded.provisionprofile"
    fi
    codesign --force --sign - "$app"
fi

codesign --verify --strict "$app"
printf '%s\n' "$app"
