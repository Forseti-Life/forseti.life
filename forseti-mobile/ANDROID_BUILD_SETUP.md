# Android Build Setup Guide

## Quick Start

### Prerequisites
- Node.js 18+ installed
- Android Studio installed
- Android SDK 34 installed
- Java JDK 17+ installed

### Initial Setup

1. **Install dependencies**
   ```bash
   cd forseti-mobile
   npm install
   ```

2. **Configure environment variables**
   ```bash
   cp .env.development .env
   # Edit .env with your API keys
   ```

3. **Start Metro bundler**
   ```bash
   npm start
   ```

4. **Run on Android device/emulator**
   ```bash
   npm run android:dev
   ```

---

## Release Build Setup

### 1. Generate Release Keystore

```bash
cd android/app
keytool -genkeypair -v -storetype PKCS12 \
  -keystore forseti-release.keystore \
  -alias forseti-key \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -dname "CN=Forseti, OU=Mobile, O=St Louis Integration, L=Philadelphia, ST=PA, C=US"
```

**Important:** Store the keystore and passwords securely. You'll need them for all future releases.

### 2. Configure Signing (Local Development)

Create or edit `android/local.properties`:

```properties
sdk.dir=/Users/YOUR_USERNAME/Library/Android/sdk

# Release signing config
FORSETI_RELEASE_STORE_FILE=forseti-release.keystore
FORSETI_RELEASE_STORE_PASSWORD=your_keystore_password
FORSETI_RELEASE_KEY_ALIAS=forseti-key
FORSETI_RELEASE_KEY_PASSWORD=your_key_password
```

### 3. Configure Signing (CI/CD)

For GitHub Actions, GitLab CI, or other CI systems, add these as secrets/environment variables:

```yaml
# CI Environment Variables
FORSETI_RELEASE_STORE_FILE=forseti-release.keystore
FORSETI_RELEASE_STORE_PASSWORD=***
FORSETI_RELEASE_KEY_ALIAS=forseti-key
FORSETI_RELEASE_KEY_PASSWORD=***
```

### 4. Build Release APK

```bash
# Clean previous builds
npm run android:clean

# Build release APK
npm run android:build

# Output location:
# android/app/build/outputs/apk/release/
```

### 5. Build Android App Bundle (AAB) for Play Store

```bash
npm run android:bundle

# Output location:
# android/app/build/outputs/bundle/release/app-release.aab
```

---

## Build Commands Reference

### Development
| Command | Description |
|---------|-------------|
| `npm run android` | Run debug build (default) |
| `npm run android:dev` | Run debug build explicitly |
| `npm start` | Start Metro bundler |
| `npm run start:reset` | Clear cache and start Metro |

### Building
| Command | Description |
|---------|-------------|
| `npm run android:build` | Build release APK |
| `npm run android:build:debug` | Build debug APK |
| `npm run android:bundle` | Build release AAB for Play Store |
| `npm run android:clean` | Clean build artifacts |

### Testing
| Command | Description |
|---------|-------------|
| `npm test` | Run Jest tests |
| `npm run test:watch` | Watch mode |
| `npm run test:coverage` | Generate coverage report |
| `npm run lint` | Run ESLint |
| `npm run type-check` | TypeScript validation |

---

## Environment Configuration

### Development (.env.development)
```env
ENV=development
API_BASE_URL=http://10.0.2.2:8000/api
ENABLE_DEBUG_MODE=true
```

### Staging (.env.staging)
```env
ENV=staging
API_BASE_URL=https://staging.forseti.life/api
ENABLE_DEBUG_MODE=false
```

### Production (.env.production)
```env
ENV=production
API_BASE_URL=https://forseti.life/api
ENABLE_DEBUG_MODE=false
```

---

## Troubleshooting

### Build Fails with "Out of Memory"

**Solution:** Increase Gradle heap size in `android/gradle.properties`:
```properties
org.gradle.jvmargs=-Xmx6144m -XX:MaxMetaspaceSize=1536m
```

### Metro Bundler Connection Issues

**Solution:** Reset Metro cache:
```bash
npm run start:reset
```

### Android Build Cache Issues

**Solution:** Clean and rebuild:
```bash
npm run android:clean
cd android && ./gradlew clean
npm run android:build
```

### Release Build Crashes

**Solution:** Check ProGuard rules in `android/app/proguard-rules.pro`. Add keep rules for any libraries causing issues.

### Missing Google Maps

**Solution:** Verify API key in `android/app/src/main/AndroidManifest.xml`:
```xml
<meta-data
  android:name="com.google.android.geo.API_KEY"
  android:value="YOUR_GOOGLE_MAPS_API_KEY"/>
```

---

## Performance Optimization

### APK Size Analysis

```bash
cd android
./gradlew app:assembleRelease
./gradlew app:analyzeReleaseBundle
```

### Build Speed Optimization

Already configured in `gradle.properties`:
- ✅ Parallel builds enabled
- ✅ Build cache enabled
- ✅ Configure on demand
- ✅ 4GB heap size

---

## Security Checklist

Before releasing:

- [ ] Release keystore generated and backed up
- [ ] Keystore passwords stored securely
- [ ] `local.properties` not committed to Git
- [ ] HTTPS enforced in production
- [ ] ProGuard/R8 enabled for release
- [ ] No hardcoded API keys in code
- [ ] Google Maps API key restricted to app signature
- [ ] Tested on physical devices

---

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Android Build

on: [push, pull_request]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Set up JDK 17
        uses: actions/setup-java@v3
        with:
          java-version: '17'
          distribution: 'temurin'
      
      - name: Setup Node.js
        uses: actions/setup-node@v3
        with:
          node-version: '18'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Build release APK
        run: |
          cd android
          ./gradlew assembleRelease
        env:
          FORSETI_RELEASE_STORE_PASSWORD: ${{ secrets.KEYSTORE_PASSWORD }}
          FORSETI_RELEASE_KEY_PASSWORD: ${{ secrets.KEY_PASSWORD }}
      
      - name: Upload APK
        uses: actions/upload-artifact@v3
        with:
          name: release-apk
          path: android/app/build/outputs/apk/release/*.apk
```

---

## Play Store Deployment

### 1. Generate Signed AAB

```bash
npm run android:bundle
```

### 2. Test AAB Locally

```bash
bundletool build-apks \
  --bundle=android/app/build/outputs/bundle/release/app-release.aab \
  --output=app.apks \
  --mode=universal

bundletool install-apks --apks=app.apks
```

### 3. Upload to Play Console

1. Go to Google Play Console
2. Select your app
3. Navigate to Release > Production
4. Create new release
5. Upload the AAB file
6. Complete release notes
7. Review and rollout

---

## Version Management

### Update Version

Edit `android/app/build.gradle`:

```gradle
defaultConfig {
    versionCode 2        // Increment for each release
    versionName "1.1.0"  // Semantic versioning
}
```

### Version Naming Convention

- **versionCode:** Integer, must increment with each release
- **versionName:** String, semantic versioning (MAJOR.MINOR.PATCH)

Example progression:
- 1.0.0 (versionCode 1) - Initial release
- 1.0.1 (versionCode 2) - Bug fix
- 1.1.0 (versionCode 3) - New features
- 2.0.0 (versionCode 4) - Major changes

---

## Support

For issues or questions:
1. Check [BEST_PRACTICES_REVIEW.md](./BEST_PRACTICES_REVIEW.md)
2. Review [React Native Docs](https://reactnative.dev/docs/getting-started)
3. Check [Android Build Issues](https://developer.android.com/studio/build)
