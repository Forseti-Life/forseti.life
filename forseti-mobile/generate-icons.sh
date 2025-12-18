#!/bin/bash

# Script to generate Android app icons from source image
# Source: forseti_safe.png from website

SOURCE_IMAGE="/home/keithaumiller/forseti.life/sites/forseti/web/themes/custom/forseti/images/logos/originals/forseti_safe.png"
ANDROID_RES="/home/keithaumiller/forseti.life/forseti-mobile/android/app/src/main/res"

# Android launcher icon sizes
# mdpi: 48x48
# hdpi: 72x72
# xhdpi: 96x96
# xxhdpi: 144x144
# xxxhdpi: 192x192

echo "Generating Android launcher icons from forseti_safe.png..."

# Generate mdpi (48x48)
convert "$SOURCE_IMAGE" -resize 48x48 "$ANDROID_RES/mipmap-mdpi/ic_launcher.png"
convert "$SOURCE_IMAGE" -resize 48x48 "$ANDROID_RES/mipmap-mdpi/ic_launcher_round.png"
echo "✓ Generated mdpi icons (48x48)"

# Generate hdpi (72x72)
convert "$SOURCE_IMAGE" -resize 72x72 "$ANDROID_RES/mipmap-hdpi/ic_launcher.png"
convert "$SOURCE_IMAGE" -resize 72x72 "$ANDROID_RES/mipmap-hdpi/ic_launcher_round.png"
echo "✓ Generated hdpi icons (72x72)"

# Generate xhdpi (96x96)
convert "$SOURCE_IMAGE" -resize 96x96 "$ANDROID_RES/mipmap-xhdpi/ic_launcher.png"
convert "$SOURCE_IMAGE" -resize 96x96 "$ANDROID_RES/mipmap-xhdpi/ic_launcher_round.png"
echo "✓ Generated xhdpi icons (96x96)"

# Generate xxhdpi (144x144)
convert "$SOURCE_IMAGE" -resize 144x144 "$ANDROID_RES/mipmap-xxhdpi/ic_launcher.png"
convert "$SOURCE_IMAGE" -resize 144x144 "$ANDROID_RES/mipmap-xxhdpi/ic_launcher_round.png"
echo "✓ Generated xxhdpi icons (144x144)"

# Generate xxxhdpi (192x192)
convert "$SOURCE_IMAGE" -resize 192x192 "$ANDROID_RES/mipmap-xxxhdpi/ic_launcher.png"
convert "$SOURCE_IMAGE" -resize 192x192 "$ANDROID_RES/mipmap-xxxhdpi/ic_launcher_round.png"
echo "✓ Generated xxxhdpi icons (192x192)"

echo ""
echo "✅ All Android launcher icons generated successfully!"
echo ""
echo "Icon sizes generated:"
echo "  - mdpi:    48x48"
echo "  - hdpi:    72x72"
echo "  - xhdpi:   96x96"
echo "  - xxhdpi:  144x144"
echo "  - xxxhdpi: 192x192"
