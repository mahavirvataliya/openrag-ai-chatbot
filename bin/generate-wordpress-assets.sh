#!/usr/bin/env bash
#
# Generate WordPress.org plugin assets from SVG sources.
#
# Produces under .wordpress-org/assets/:
#   icon-128.png       128x128 plugin icon (required)
#   icon-256.png       256x256 retina icon (recommended)
#   banner-772x250.png standard banner (required for header display)
#   banner-1544x500.png retina banner (recommended)
#
# Tries: rsvg-convert, Inkscape, then ImageMagick (magick/convert).
# At least one must be installed. On macOS: `brew install librsvg` is the lightest option.
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ASSET_DIR="${ROOT_DIR}/.wordpress-org/assets"

command -v rsvg-convert >/dev/null 2>&1 && RASTER="rsvg"
{ command -v magick >/dev/null 2>&1 || command -v convert >/dev/null 2>&1; } && RASTER="${RASTER:-magick}"
command -v inkscape     >/dev/null 2>&1 && RASTER="${RASTER:-inkscape}"

if [[ -z "${RASTER:-}" ]]; then
    cat >&2 <<EOF
ERROR: no SVG rasterizer found. Install one of:
  • librsvg:    brew install librsvg      (macOS)  /  apt-get install librsvg2-bin  (Debian)
  • Inkscape:   brew install --cask inkscape
  • ImageMagick: brew install imagemagick
EOF
    exit 1
fi
echo "==> Using rasterizer: ${RASTER}"

render() {
    local src="$1"; local out="$2"; local w="$3"; local h="$4"
    case "${RASTER}" in
        rsvg)
            rsvg-convert -w "${w}" -h "${h}" "${src}" -o "${out}"
            ;;
        inkscape)
            inkscape --export-type=png --export-filename="${out}" \
                --export-width="${w}" --export-height="${h}" "${src}" 2>/dev/null
            ;;
        magick)
            if command -v magick >/dev/null 2>&1; then
                magick -background none "${src}" -resize "${w}x${h}" "${out}"
            else
                convert -background none "${src}" -resize "${w}x${h}" "${out}"
            fi
            ;;
    esac
    echo "  ✓ $(basename "${out}")  (${w}x${h})"
}

echo "==> Rendering icon variants"
render "${ASSET_DIR}/icon.svg"   "${ASSET_DIR}/icon-128.png"  128  128
render "${ASSET_DIR}/icon.svg"   "${ASSET_DIR}/icon-256.png"  256  256

echo "==> Rendering banner variants"
render "${ASSET_DIR}/banner.svg" "${ASSET_DIR}/banner-772x250.png"   772  250
render "${ASSET_DIR}/banner.svg" "${ASSET_DIR}/banner-1544x500.png" 1544  500

echo ""
echo "✔ Done. PNG assets are in .wordpress-org/assets/"
echo ""
echo "These files are NOT committed to SVN trunk/ — they live in /assets/ at the"
echo "repository root. The deploy script (bin/deploy-wordpress-org.sh) handles"
echo "placing them in the correct SVN location automatically."
