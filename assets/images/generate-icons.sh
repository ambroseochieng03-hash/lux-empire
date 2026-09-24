#!/usr/bin/env bash
#
# LUX EMPIRE — regenerates the app icons and the social-preview image
# from assets/images/logo.svg, composited onto the platform's own
# dark background (#0A0A0A) instead of logo.svg's transparent one.
# logo.svg itself is never modified — only read.
#
# Requires: rsvg-convert (pacman -S librsvg) and ImageMagick (convert).
#
# Run from inside assets/images/:
#   cd assets/images && chmod +x generate-icons.sh && ./generate-icons.sh

set -euo pipefail

SRC_SVG="logo.svg"
BG_COLOR="#0A0A0A"
TMP="/tmp/lux_logo_tmp.png"

command -v rsvg-convert >/dev/null 2>&1 || { echo "Missing rsvg-convert. Install with: sudo pacman -S librsvg"; exit 1; }
command -v convert >/dev/null 2>&1 || { echo "Missing ImageMagick. Install with: sudo pacman -S imagemagick"; exit 1; }
[ -f "$SRC_SVG" ] || { echo "Run this from inside assets/images/ (logo.svg not found here)."; exit 1; }

FONT_BOLD="$(fc-match -f '%{file}' 'DejaVu Sans:bold' 2>/dev/null || true)"
FONT_REG="$(fc-match -f '%{file}' 'DejaVu Sans' 2>/dev/null || true)"
[ -f "$FONT_BOLD" ] || { echo "DejaVu Sans Bold not found. Install with: sudo pacman -S ttf-dejavu"; exit 1; }
[ -f "$FONT_REG" ]  || { echo "DejaVu Sans not found. Install with: sudo pacman -S ttf-dejavu"; exit 1; }

# "canvas_size logo_width output_file"
ICONS=(
  "16 12 favicon-16.png"
  "32 23 favicon-32.png"
  "48 35 favicon-48.png"
  "180 126 apple-touch-icon.png"
  "192 134 icon-192.png"
  "512 307 icon-512.png"
  "512 256 icon-512-maskable.png"
)

for entry in "${ICONS[@]}"; do
  read -r size logo_w outfile <<< "$entry"

  rsvg-convert -w "$logo_w" -h "$logo_w" -a "$SRC_SVG" -o "$TMP"

  if [ "$outfile" = "apple-touch-icon.png" ]; then
    # iOS fills a TRANSPARENT home-screen icon with white automatically
    # — this is almost certainly the actual source of the "white
    # background" you're seeing. Must be fully opaque, no alpha channel.
    convert -size "${size}x${size}" xc:"$BG_COLOR" \
      "$TMP" -gravity center -composite \
      -alpha remove -alpha off \
      "$outfile"
  else
    convert -size "${size}x${size}" xc:"$BG_COLOR" \
      "$TMP" -gravity center -composite \
      "$outfile"
  fi

  echo "Generated $outfile"
done

# Social link-preview image (WhatsApp/Facebook/Twitter). 1200x630 is
# the standard OG size. WhatsApp in particular often fails to render
# SVG previews at all, which is likely why the logo wasn't showing up
# — this is a PNG specifically because of that.
rsvg-convert -w 340 -h 340 -a "$SRC_SVG" -o "$TMP"
convert -size 1200x630 xc:"$BG_COLOR" \
  "$TMP" -gravity center -geometry +0-60 -composite \
  -gravity center -fill "#D4AF37" -font "$FONT_BOLD" -pointsize 46 \
  -annotate +0+210 "LUX EMPIRE" \
  -fill "#9a9a9a" -font "$FONT_REG" -pointsize 22 \
  -annotate +0+260 "Luxury Living. Elite Movement. One Empire." \
  og-preview.png

echo "Generated og-preview.png"
rm -f "$TMP"
echo "Done — review the images, then redeploy."
