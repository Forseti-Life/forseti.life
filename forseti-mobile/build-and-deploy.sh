#!/bin/bash
# Complete build and deploy workflow for Forseti Mobile
# 1. Increments version
# 2. Builds release APK
# 3. Copies APK to website deployment location
# 4. Shows git status for commit

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$SCRIPT_DIR/../sites/forseti/web/sites/default/files/forseti/mobile"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Forseti Mobile Complete Build & Deploy${NC}"
echo "================================================"
echo ""

# Step 1: Increment version
echo -e "${YELLOW}Step 1: Incrementing build version...${NC}"
"$SCRIPT_DIR/increment-build.sh"
echo ""

# Step 2: Build release APK
echo -e "${YELLOW}Step 2: Building release APK...${NC}"
cd "$SCRIPT_DIR/android"
./gradlew clean assembleRelease --no-daemon

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Build failed!${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✅ Build successful!${NC}"
echo ""

# Step 3: Find and copy APK
echo -e "${YELLOW}Step 3: Deploying APK to website location...${NC}"

# Find the arm64-v8a APK (most common architecture)
APK_DIR="$SCRIPT_DIR/android/app/build/outputs/apk/release"
ARM64_APK=$(find "$APK_DIR" -name "*arm64-v8a.apk" | head -1)

if [ -z "$ARM64_APK" ]; then
    echo -e "${RED}❌ Could not find arm64-v8a APK!${NC}"
    echo "Available APKs:"
    ls -lh "$APK_DIR"/*.apk
    exit 1
fi

APK_FILENAME=$(basename "$ARM64_APK")
APK_SIZE=$(du -h "$ARM64_APK" | cut -f1)

echo -e "${BLUE}Found APK:${NC} $APK_FILENAME ($APK_SIZE)"

# Create deployment directory if it doesn't exist
mkdir -p "$DEPLOY_DIR"

# Copy APK to deployment location
cp "$ARM64_APK" "$DEPLOY_DIR/Forseti-latest.apk"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to copy APK to deployment location!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ APK copied to:${NC} $DEPLOY_DIR/Forseti-latest.apk"
echo ""

# Step 4: Show git status
echo -e "${YELLOW}Step 4: Git status${NC}"
cd "$SCRIPT_DIR/.."
git status

echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}🎉 Build and Deploy Complete!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "${BLUE}Files changed:${NC}"
echo "  • forseti-mobile/App.tsx (version display)"
echo "  • forseti-mobile/android/app/build.gradle (versionCode)"
echo "  • sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Review changes: ${YELLOW}git diff${NC}"
echo "  2. Commit changes: ${YELLOW}git add -A && git commit -m 'Build and deploy Forseti Mobile v1.0.3-<version>'${NC}"
echo "  3. Push to trigger deployment: ${YELLOW}git push origin main${NC}"
echo ""
echo -e "${BLUE}Deployment URL:${NC} https://forseti.life/sites/default/files/forseti/mobile/Forseti-latest.apk"
