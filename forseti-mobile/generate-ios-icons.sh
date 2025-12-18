#!/bin/bash

# Script to generate iOS app icons from source image
# Source: forseti_safe.png from website

SOURCE_IMAGE="/home/keithaumiller/forseti.life/sites/forseti/web/themes/custom/forseti/images/logos/originals/forseti_safe.png"
IOS_ICONS="/home/keithaumiller/forseti.life/forseti-mobile/ios/AmISafeTempInit/Images.xcassets/AppIcon.appiconset"

echo "Generating iOS app icons from forseti_safe.png..."

# iOS requires specific sizes
# App Store: 1024x1024
# iPhone Notification (2x, 3x): 40x40, 60x60
# iPhone Settings (2x, 3x): 58x58, 87x87
# iPhone Spotlight (2x, 3x): 80x80, 120x120
# iPhone App (2x, 3x): 120x120, 180x180

# 1024x1024 - App Store
convert "$SOURCE_IMAGE" -resize 1024x1024 "$IOS_ICONS/icon-1024.png"
echo "✓ Generated icon-1024.png (App Store)"

# 20pt @2x, @3x - Notification
convert "$SOURCE_IMAGE" -resize 40x40 "$IOS_ICONS/icon-20@2x.png"
convert "$SOURCE_IMAGE" -resize 60x60 "$IOS_ICONS/icon-20@3x.png"
echo "✓ Generated 20pt icons (Notification)"

# 29pt @2x, @3x - Settings
convert "$SOURCE_IMAGE" -resize 58x58 "$IOS_ICONS/icon-29@2x.png"
convert "$SOURCE_IMAGE" -resize 87x87 "$IOS_ICONS/icon-29@3x.png"
echo "✓ Generated 29pt icons (Settings)"

# 40pt @2x, @3x - Spotlight
convert "$SOURCE_IMAGE" -resize 80x80 "$IOS_ICONS/icon-40@2x.png"
convert "$SOURCE_IMAGE" -resize 120x120 "$IOS_ICONS/icon-40@3x.png"
echo "✓ Generated 40pt icons (Spotlight)"

# 60pt @2x, @3x - App
convert "$SOURCE_IMAGE" -resize 120x120 "$IOS_ICONS/icon-60@2x.png"
convert "$SOURCE_IMAGE" -resize 180x180 "$IOS_ICONS/icon-60@3x.png"
echo "✓ Generated 60pt icons (App)"

echo ""
echo "✅ All iOS app icons generated successfully!"
echo ""
echo "Icon sizes generated:"
echo "  - 1024x1024 (App Store)"
echo "  - 40x40, 60x60 (Notification)"
echo "  - 58x58, 87x87 (Settings)"
echo "  - 80x80, 120x120 (Spotlight)"
echo "  - 120x120, 180x180 (App)"
