# Forseti Mobile App - Content & Button Review

## Current Status
- **Package Name**: com.stlouisintegration.forseti ✅
- **App Name**: Needs review in multiple places
- **Colors**: Fixed neon green → Forseti palette ✅
- **Storage Keys**: forseti_* ✅
- **Notification Channels**: forseti-* ✅

## Screens & Navigation

### Bottom Tab Navigation (Main 6 Tabs)
1. **Home** - Title: "AmISafe" ❌ (needs update)
2. **Map** - Title: "Safety Map" ✅
3. **Chat** - Title: "Talk with Forseti" ✅
4. **Safety** - Title: "Safety" ✅
5. **Statistics** - Title: "Statistics" ✅
6. **Profile** - Title: "Profile" ✅

### Additional Screens (Stack Navigation)
- About
- How It Works
- Privacy
- Settings
- Conversation List

---

## Screen-by-Screen Review

### 1. Home Screen (`src/screens/Home/HomeScreen.tsx`)
**Current Issues:**
- Header shows "AmISafe by Forseti" ✅ (already fixed to "Forseti Mobile")
- Subtitle: "AI-Powered Safety Monitoring for Philadelphia" ✅

**Action Buttons:**
- View Safety Map → `https://forseti.life/safety-map` (external link)
- How It Works → `https://forseti.life/how-it-works` (external link)
- Emergency Call → `tel:911`
- Community → `https://forseti.life/community` (external link)
- Learn More → `https://forseti.life/about` (external link)

**Question:** Should these buttons navigate to in-app screens instead of external website links?

---

### 2. Settings Screen (`src/screens/Settings/SettingsScreen.tsx`)
**Text References:**
- "Forseti uses H3 geospatial hexagons..." ✅ (already fixed)

**Navigation Buttons:**
- About Forseti → navigates to About screen ✅
- How It Works → navigates to HowItWorks screen ✅
- Privacy & Security → navigates to Privacy screen ✅
- Contact Us → `https://forseti.life/contact` (external link)

**Action Buttons:**
- View Location History
- Clear Location History
- Save Settings

---

### 3. Profile Screen (`src/screens/Profile/ProfileScreen.tsx`)
**Features:**
- Login/Logout functionality
- Connects to Drupal backend (forseti.life)
- View Conversations button

---

### 4. Chat Screen (`src/screens/Chat/ChatScreen.js`)
**Features:**
- AI conversation with Forseti
- Connects to `/api/amisafe/chat` ❌ (still using amisafe endpoint)

---

### 5. Map/CrimeMap Screen (`src/screens/CrimeMapScreen.js`)
**Issues Found:**
- Uses `/api/amisafe/*` endpoints ❌
- Color scheme: Now using Forseti colors ✅

---

### 6. Safety Screen (`src/screens/Safety/SafetyScreen.tsx`)
**Review Needed:** Check for AmISafe references

---

### 7. Statistics Screen (`src/screens/Statistics/StatisticsScreen.tsx`)
**Review Needed:** Check for AmISafe references and API endpoints

---

## API Endpoints Still Using "amisafe"

### DrupalCrimeService.js
- `/api/amisafe/risk-level` ❌
- `/api/amisafe/aggregated` ❌
- `/api/amisafe/incidents` ❌
- `/api/amisafe/citywide-stats` ❌

### BackgroundLocationService.ts
- `/api/amisafe/aggregated` ❌

### Chat/AI Services
- Check for amisafe endpoints ❌

---

## Text Still Showing "AmISafe"

### App.tsx
- Line 1: Comment "AmISafe Mobile Application" ❌
- Line 96: Tab screen title "AmISafe" ❌
- Line 144: Console log "Initializing AmISafe Mobile App..." ❌
- Line 161: Alert message "AmISafe needs location access..." ❌
- Line 177: Console log "AmISafe Mobile App initialization complete!" ❌

### Service Comments
- NotificationService.ts: "Notification Service for AmISafe Mobile Application" ❌
- H3LocationService.js: "H3 Geospatial Service for AmISafe Mobile Application" ❌
- AIConversationService.js: "AI Conversation Service for AmISafe Mobile" ❌
- CrimeMapScreen.js: "Crime Map Screen for AmISafe Mobile App" ❌
- HomeScreen.tsx: "Home Screen - Main dashboard for AmISafe Mobile Application" ❌

---

## Questions for Review

### 1. API Endpoints
**Decision Needed:** Should the Drupal backend API endpoints be renamed from `/api/amisafe/*` to `/api/forseti/*`?
- This would require backend changes on forseti.life
- Or keep the API paths as-is and just update the app UI/UX?

### 2. External vs Internal Navigation
**Home Screen Buttons:** Currently link to external website pages:
- "View Safety Map" → forseti.life/safety-map
- "How It Works" → forseti.life/how-it-works
- "Community" → forseti.life/community
- "Learn More" → forseti.life/about

**Options:**
- A. Keep external links (users see website content)
- B. Navigate to in-app screens (About, HowItWorks, etc.)
- C. Mix: Some external, some internal

### 3. Content Pages
The app has these built-in screens:
- About Screen
- How It Works Screen
- Privacy Screen

**Decision Needed:** Should we populate these with content, or keep linking to the website?

### 4. Drupal Module Name
The backend Drupal module might be called `amisafe_*`. Should this be renamed to `forseti_*`?

---

## Recommended Action Plan

### Priority 1: Critical Branding (Must Fix Before Release)
- [ ] Update App.tsx title from "AmISafe" to "Forseti"
- [ ] Update all console.log messages
- [ ] Update all Alert messages
- [ ] Update file header comments
- [ ] Remove all "AmISafe" text visible to users

### Priority 2: API Endpoints (Backend Decision Required)
- [ ] **DECISION:** Keep `/api/amisafe/*` or rename to `/api/forseti/*`?
- [ ] If renaming: Update all API service files
- [ ] If keeping: Just update UI text

### Priority 3: Navigation & UX
- [ ] **DECISION:** External links vs in-app screens?
- [ ] Review and populate About, HowItWorks, Privacy screens
- [ ] Ensure all buttons work correctly

### Priority 4: Testing
- [ ] Test all buttons and navigation
- [ ] Verify colors match Forseti brand
- [ ] Check all API connections
- [ ] Test permissions and notifications
- [ ] Install and test on actual device

---

## Files to Update (Complete List)

### High Priority
1. `App.tsx` - Tab title, alerts, console logs
2. `src/screens/Home/HomeScreen.tsx` - Header comment
3. `src/screens/CrimeMapScreen.js` - Header comment
4. `src/services/notifications/NotificationService.ts` - Header comment
5. `src/services/H3LocationService.js` - Header comment
6. `src/services/AIConversationService.js` - Header comment

### Medium Priority (If changing API paths)
7. `src/services/DrupalCrimeService.js` - All API endpoints
8. `src/services/location/BackgroundLocationService.ts` - API endpoint
9. Chat services - API endpoints

### Low Priority (Internal developer documentation)
10. Comments and console logs throughout the app
