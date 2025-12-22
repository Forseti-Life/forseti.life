# Mobile App Icon Update

## Overview

Updated mobile app launcher icons to use the `forseti_safe.png` image which has less white space and better fills the icon area.

## Source Image

- **Location**: `sites/forseti/web/themes/custom/forseti/images/logos/originals/forseti_safe.png`
- **Dimensions**: 407 x 462 pixels
- **Format**: PNG with transparency (RGBA)

## What Was Changed

### Android Icons

Generated all required launcher icon sizes:

- **mdpi**: 48x48 (42x48 actual)
- **hdpi**: 72x72 (63x72 actual)
- **xhdpi**: 96x96 (85x96 actual)
- **xxhdpi**: 144x144 (127x144 actual)
- **xxxhdpi**: 192x192 (169x192 actual)

Both standard (`ic_launcher.png`) and round (`ic_launcher_round.png`) variants were created.

**Location**: `forseti-mobile/android/app/src/main/res/mipmap-*/`

### iOS Icons

Generated all required app icon sizes:

- **1024x1024** - App Store
- **40x40, 60x60** - Notification (20pt @2x, @3x)
- **58x58, 87x87** - Settings (29pt @2x, @3x)
- **80x80, 120x120** - Spotlight (40pt @2x, @3x)
- **120x120, 180x180** - App (60pt @2x, @3x)

**Location**: `forseti-mobile/ios/AmISafeTempInit/Images.xcassets/AppIcon.appiconset/`

## Icon Generation Scripts

### For Android

```bash
./forseti-mobile/generate-icons.sh
```

### For iOS

```bash
./forseti-mobile/generate-ios-icons.sh
```

Both scripts use ImageMagick's `convert` command to resize the source image to all required dimensions.

## Notes

- The source image has a 407:462 aspect ratio (slightly taller than wide)
- Icons maintain this aspect ratio and are not stretched to perfect squares
- This creates less white space around the icon compared to the previous version
- The icon appears more prominent on device home screens

## Next Steps

1. Build new APK to test the Android icons
2. Test on iOS device (requires Mac with Xcode for iOS build)
3. Deploy updated APK to website

## Technical Details

- **Tool used**: ImageMagick 6.9.11.60
- **Command**: `convert [source] -resize [size] [output]`
- **Maintains**: Aspect ratio and transparency
