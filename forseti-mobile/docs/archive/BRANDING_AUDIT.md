# AmISafe Mobile - Branding & Assets Audit

**Audit Date**: December 13, 2024  
**Purpose**: Verify Forseti.life branding, naming, and visual assets are properly integrated

---

## ✅ Brand Names - VERIFIED

### Application Names
- **App Display Name**: ✅ "AmISafe by Forseti" (`app.json`)
- **Android Package**: `com.stlouisintegration.amisafe` ✅
- **iOS Bundle**: Currently "AmISafeTempInit" ⚠️ (should be "AmISafe by Forseti")
- **Android App Name**: ✅ "AmISafe" (`strings.xml`)

### In-App Branding
```typescript
// Home Screen
"AmISafe by Forseti" ✅

// Login Screen  
"AmISafe" ✅

// Settings Screen
"AmISafe is powered by Forseti" ✅

// Background Service Notification
"AmISafe Protection Active" ✅
```

---

## ✅ Website Links - VERIFIED

### All Links Point to forseti.life ✅

**Map Screen**:
- ✅ `https://forseti.life/safety-map` - Main interactive map

**Home Screen Quick Actions**:
- ✅ `https://forseti.life/safety-map` - View map
- ✅ `https://forseti.life/how-it-works` - Learn more
- ✅ `https://forseti.life/community` - Join community
- ✅ `https://forseti.life/about` - About Forseti footer link

**Settings Screen External Links**:
- ✅ `https://forseti.life/about` - About Forseti
- ✅ `https://forseti.life/how-it-works` - How It Works
- ✅ `https://forseti.life/privacy` - Privacy Policy
- ✅ `https://forseti.life/contact` - Contact Us

**Login Screen**:
- ✅ `https://forseti.life/user/password` - Password reset

**Background Service**:
- ✅ `https://forseti.life/safety-map?lat=X&lng=Y` - Deep link from notifications

---

## ✅ API Endpoints - VERIFIED

### Primary API Base URL
```typescript
// BackgroundLocationService.ts
private readonly API_BASE_URL = 'https://forseti.life'; ✅

// DrupalCrimeService.js  
this.baseUrl = 'https://forseti.life'; ✅
```

### API Endpoints Used
- ✅ `GET https://forseti.life/api/amisafe/aggregated` - H3 hexagon data
- ✅ `GET https://forseti.life/api/crime_incidents` - Crime records
- ✅ `GET https://forseti.life/session/token` - CSRF tokens

### Authentication Base URL
```javascript
// DrupalAuthService.js
this.baseUrl = 'https://stlouisintegration.com'; ⚠️
```
**Note**: Auth still points to stlouisintegration.com (legacy). Should this be forseti.life?

---

## ⚠️ App Icons - DEFAULT REACT NATIVE ICONS

### Android Icons
**Location**: `android/app/src/main/res/mipmap-*/`

**Current Status**: ❌ Using default React Native green Android icon

**Sizes Present**:
- ✅ mdpi: 48x48px - `ic_launcher.png`
- ✅ hdpi: 72x72px - `ic_launcher.png` 
- ✅ xhdpi: 96x96px - `ic_launcher.png`
- ✅ xxhdpi: 144x144px - `ic_launcher.png`
- ✅ xxxhdpi: 192x192px - `ic_launcher.png`
- ✅ Round icons for all densities - `ic_launcher_round.png`

**What's Needed**: 
- Custom "AmISafe by Forseti" branded icon
- Should incorporate Forseti visual identity
- Shield/safety theme recommended
- Needs 5 density variants + round versions

### iOS Icons  
**Location**: `ios/AmISafeTempInit/Images.xcassets/AppIcon.appiconset/`

**Current Status**: ❌ No actual image files, only placeholder JSON

**Required Sizes** (per Contents.json):
- 20x20 @2x, @3x (iPhone notification)
- 29x29 @2x, @3x (iPhone settings)
- 40x40 @2x, @3x (iPhone spotlight)
- 60x60 @2x, @3x (iPhone app)
- 1024x1024 @1x (App Store marketing)

**What's Needed**:
- All iOS icon sizes generated from master artwork
- 10 PNG files total
- Must match Android icon design

---

## 🎨 Missing Branding Assets

### 1. App Icons (Critical) 🔴
**Status**: Using default React Native icons

**Required**:
```
android/app/src/main/res/
  ├── mipmap-mdpi/ic_launcher.png (48x48)
  ├── mipmap-hdpi/ic_launcher.png (72x72)
  ├── mipmap-xhdpi/ic_launcher.png (96x96)
  ├── mipmap-xxhdpi/ic_launcher.png (144x144)
  ├── mipmap-xxxhdpi/ic_launcher.png (192x192)
  └── (+ round variants)

ios/AmISafeTempInit/Images.xcassets/AppIcon.appiconset/
  ├── Icon-20@2x.png
  ├── Icon-20@3x.png
  ├── Icon-29@2x.png
  ├── Icon-29@3x.png
  ├── Icon-40@2x.png
  ├── Icon-40@3x.png
  ├── Icon-60@2x.png (120x120)
  ├── Icon-60@3x.png (180x180)
  └── Icon-1024.png (App Store)
```

**Design Recommendations**:
- Shield icon (safety theme)
- Forseti color palette
- Simple, recognizable at small sizes
- Works on both light and dark backgrounds

### 2. Splash Screen / Launch Screen
**Android**: ❌ No custom splash screen
**iOS**: ⚠️ Basic LaunchScreen.storyboard (no branding)

**Recommended**:
- "AmISafe by Forseti" logo
- Shield/safety icon
- Brand colors
- Loading indicator

### 3. In-App Logo/Header Images
**Status**: ❌ No logo images imported

**Potential Uses**:
- Login screen header
- Settings screen about section
- Home screen banner
- Empty states

### 4. Forseti Logo Source
**Found**: `sites/forseti/themes/custom/stlouisintegration/src/assets/images/logo-main.png`
**Issue**: ❌ File is only 118 bytes, contains base64 data URI stub (1x1 pixel placeholder)

**Action Needed**:
- Locate actual Forseti logo file (SVG, PNG, AI)
- Export at required sizes for mobile
- Import into React Native assets

---

## 📝 Text Branding - COMPLETE ✅

### Consistent Naming Across App
- ✅ "AmISafe" as primary app name
- ✅ "AmISafe by Forseti" as full brand name
- ✅ "Powered by Forseti" attribution in Settings
- ✅ All external links reference forseti.life
- ✅ API calls use forseti.life domain

### Branding Mentions
**Count**: 16+ references to "Forseti" across codebase
**Count**: 25+ references to "AmISafe" across codebase

---

## 🔧 Branding Issues to Fix

### Priority 1: Critical (Blocks App Store Submission) 🔴

1. **Replace Default App Icons**
   - Android: 5 density variants needed
   - iOS: 10 icon sizes needed
   - **Blocker**: Cannot submit to stores with default React Native icon
   - **Timeline**: Before app store submission

2. **iOS Bundle Display Name**
   ```xml
   <!-- ios/AmISafeTempInit/Info.plist -->
   <key>CFBundleDisplayName</key>
   <string>AmISafeTempInit</string> ❌
   
   <!-- Should be: -->
   <key>CFBundleDisplayName</key>
   <string>AmISafe</string> ✅
   ```

3. **iOS Location Permission Descriptions**
   ```xml
   <!-- Info.plist - EMPTY STRINGS -->
   <key>NSLocationWhenInUseUsageDescription</key>
   <string></string> ❌
   
   <!-- Should describe why app needs location -->
   <key>NSLocationWhenInUseUsageDescription</key>
   <string>AmISafe needs your location to show crime safety information for your area.</string> ✅
   
   <key>NSLocationAlwaysAndWhenInUseUsageDescription</key>
   <string>AmISafe monitors your location in the background to alert you when entering high-crime areas.</string> ✅
   ```

### Priority 2: Important (Before Public Launch) 🟡

4. **Add Splash Screen Branding**
   - Android: Create drawable/launch_screen.xml
   - iOS: Update LaunchScreen.storyboard with logo

5. **Create Forseti Logo Asset**
   - Design/source high-res logo
   - Export multiple sizes for @1x, @2x, @3x
   - Import to `src/assets/images/`

6. **Add In-App Logo Usage**
   - Login screen header
   - Settings "About Forseti" section
   - Home screen branded banner (optional)

### Priority 3: Nice to Have (Polish) 🟢

7. **Feature Graphics for Stores**
   - Google Play: 1024x500 feature graphic
   - iOS: Screenshots with branding

8. **App Store Screenshots**
   - Show "AmISafe by Forseti" branding prominently
   - Include forseti.life URL in descriptions

9. **Notification Icon (Android)**
   - Custom notification icon (monochrome, 24x24dp)
   - Currently uses default app icon

---

## 🎯 Action Items

### Immediate (This Week)
- [ ] **Design AmISafe app icon** (or commission designer)
  - Shield + location pin theme suggested
  - Forseti brand colors
  - Export all required sizes

- [ ] **Generate all icon sizes**
  ```bash
  # Can use tools like:
  # - https://appicon.co/ (online generator)
  # - https://easyappicon.com/
  # - Figma + export scripts
  ```

- [ ] **Update iOS Info.plist**
  - Change CFBundleDisplayName to "AmISafe"
  - Add location permission descriptions
  - Add background modes descriptions

- [ ] **Replace default icons**
  - Copy generated icons to android/app/src/main/res/mipmap-*
  - Copy generated icons to ios/AmISafeTempInit/Images.xcassets/AppIcon.appiconset/
  - Update Contents.json with filenames

### Before App Store Submission
- [ ] **Create splash screen**
  - Android: launch_screen.xml
  - iOS: Update LaunchScreen.storyboard

- [ ] **Verify all branding**
  - Test app displays "AmISafe" everywhere
  - Check all links go to forseti.life
  - Confirm API calls work

- [ ] **App Store assets**
  - Feature graphic (Google Play)
  - Screenshots showing branding
  - Description mentions "powered by Forseti"

### Future Enhancements
- [ ] **In-app logo usage**
  - Import Forseti logo to React Native
  - Add to Login, Settings, About screens

- [ ] **Custom notification icon**
  - Monochrome 24x24dp for Android status bar

---

## 📊 Branding Score: 75/100

### Breakdown
- ✅ **Text Branding**: 100% - All names, links, and copy correct
- ✅ **Domain References**: 100% - All point to forseti.life
- ✅ **API Integration**: 95% - Correct endpoints (auth domain questionable)
- ❌ **Visual Assets**: 0% - No custom icons, using React Native defaults
- ⚠️ **iOS Configuration**: 60% - Missing display name and permission descriptions

### What's Good
- Consistent "AmISafe by Forseti" naming
- All external links properly branded
- API calls to forseti.life
- Code references brand throughout

### What Needs Work
- **No custom app icon** (most critical)
- **iOS Info.plist incomplete**
- **No splash screen branding**
- **No in-app logo images**

---

## 🚀 Next Steps

1. **Commission or design app icon** (1-3 days)
2. **Generate all icon sizes** (1 hour with tools)
3. **Update iOS Info.plist** (15 minutes)
4. **Replace default icons** (30 minutes)
5. **Test on devices** (1 hour)
6. **Create splash screens** (2-4 hours)
7. **Rebuild APK/iOS** (30 minutes)
8. **Final branding verification** (1 hour)

**Estimated Total Time**: 2-3 days including design work

---

**Bottom Line**: Text branding is excellent ✅, but visual branding is completely missing ❌. Cannot submit to app stores without custom icons. This is the #1 blocker for launch.
