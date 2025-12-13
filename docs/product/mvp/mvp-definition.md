# MVP Definition

**Last Updated**: 2024-12-13  
**Version**: 1.0  
**Status**: 🟡 Draft

---

## What is an MVP?

A **Minimum Viable Product** is the smallest version of your product that allows you to start the Build-Measure-Learn loop with the least amount of effort.

**Common Misconception**: MVP = "Minimal Features"  
**Reality**: MVP = "Maximum Learning with Minimum Effort"

> "The minimum viable product is that version of a new product which allows a team to collect the maximum amount of validated learning about customers with the least effort." - Eric Ries

---

## MVP Principles

### 1. Focus on Learning, Not Building
- Goal: Test core assumptions
- Method: Smallest feature set to test value hypothesis
- Outcome: Validated learning about customers

### 2. Deliver Core Value
- Must solve the primary customer problem
- Must demonstrate unique value proposition
- Should trigger the "aha moment"

### 3. Target Early Adopters
- Build for people who have the problem most acutely
- Not for mainstream market (yet)
- Forgiving of rough edges, want to be involved

### 4. Be Willing to Throw It Away
- MVP is an experiment, not a product
- Pivot frequently based on learning
- Don't over-engineer

---

## Forseti MVP Status

### Current Version: [Version Number]

**Release Date**: [YYYY-MM-DD]  
**Status**: 🟡 In Development | 🟢 Live | 🔴 Sunset

### Core Value Hypothesis

**We believe that**:
> _[e.g., "Providing hyperlocal, real-time crime risk visualization will help urban residents make safer route decisions"]_

**We will know we're right when**:
> _[e.g., "70% of users check the map before walking in unfamiliar areas AND report feeling safer"]_

---

## MVP Feature Set

### ✅ Must Have (Core Features)
_Features absolutely required to deliver core value._

1. **[Feature 1]**: _[e.g., Interactive Safety Map]_
   - **Why**: _[Core value prop - visualize hyperlocal risk]_
   - **Status**: ✅ Implemented | 🔄 In Progress | ⏳ Planned
   - **Implementation**: _[Brief technical description]_

2. **[Feature 2]**: _[e.g., H3 Hexagon Crime Aggregation]_
   - **Why**: _[Unique approach - ~700m precision]_
   - **Status**: ✅ Implemented | 🔄 In Progress | ⏳ Planned
   - **Implementation**: _[Brief technical description]_

3. **[Feature 3]**: _[e.g., Z-Score Risk Assessment]_
   - **Why**: _[Statistical confidence in safety scores]_
   - **Status**: ✅ Implemented | 🔄 In Progress | ⏳ Planned
   - **Implementation**: _[Brief technical description]_

4. **[Feature 4]**: _[e.g., Background Location Monitoring (Premium)]_
   - **Why**: _[Proactive safety alerts]_
   - **Status**: ✅ Implemented | 🔄 In Progress | ⏳ Planned
   - **Implementation**: _[Brief technical description]_

5. **[Feature 5]**: _[e.g., User Authentication]_
   - **Why**: _[Required for premium features, personalization]_
   - **Status**: ✅ Implemented | 🔄 In Progress | ⏳ Planned
   - **Implementation**: _[Brief technical description]_

### 🟡 Nice to Have (Secondary Features)
_Features that enhance experience but aren't core to MVP._

- **[Feature A]**: _[e.g., Historical crime trends over time]_
- **[Feature B]**: _[e.g., Crime type filtering (violent vs. property)]_
- **[Feature C]**: _[e.g., Saved locations / home address]_
- **[Feature D]**: _[e.g., Notification customization (time windows, thresholds)]_

### ❌ Not Now (Post-MVP)
_Features to consider after MVP validation._

- **[Feature X]**: _[e.g., Social features - share locations with friends]_
- **[Feature Y]**: _[e.g., Route planning with safety optimization]_
- **[Feature Z]**: _[e.g., Integration with ride-sharing apps]_

---

## MVP Architecture

### High-Level Components

```
┌─────────────────────────────────────────────────────┐
│                   Frontend Layer                     │
├─────────────────────────────────────────────────────┤
│  • Web App (React/Drupal)                           │
│  • Mobile App (React Native)                        │
│  • Interactive Map (Leaflet/H3 visualization)       │
└─────────────────────────────────────────────────────┘
                         ↕
┌─────────────────────────────────────────────────────┐
│                    API Layer                         │
├─────────────────────────────────────────────────────┤
│  • RESTful API (Drupal custom modules)              │
│  • Authentication (Drupal user system)              │
│  • Rate limiting & caching                          │
└─────────────────────────────────────────────────────┘
                         ↕
┌─────────────────────────────────────────────────────┐
│                Business Logic Layer                  │
├─────────────────────────────────────────────────────┤
│  • H3 Geospatial Processing (Python)                │
│  • Z-Score Risk Calculation                         │
│  • Crime Data Aggregation                           │
│  • Background Service (Mobile)                      │
└─────────────────────────────────────────────────────┘
                         ↕
┌─────────────────────────────────────────────────────┐
│                   Data Layer                         │
├─────────────────────────────────────────────────────┤
│  • MySQL Database (Crime incidents, user data)      │
│  • H3 Hexagon Index                                 │
│  • Crime Data ETL Pipeline                          │
└─────────────────────────────────────────────────────┘
```

**Detailed Architecture**: See `/docs/ARCHITECTURE.md`

---

## User Journey (MVP)

### Persona: Sarah (Urban Commuter)

#### First-Time User Experience

1. **Discovery** (Acquisition)
   - Sarah searches "crime map Chicago" on Google
   - Finds Forseti website

2. **Landing Page** (Activation Start)
   - Sees clear value prop: "Weather forecast for crime"
   - Views interactive demo map (no signup required)
   - Sees her neighborhood is color-coded

3. **Aha Moment** (Activation)
   - Zooms to her walking route to work
   - Clicks hexagon to see z-score and risk level
   - Realizes one route is safer than another

4. **Signup** (Activation Continue)
   - Decides to create account for mobile alerts
   - Quick signup (email + password)
   - Downloads mobile app

5. **Onboarding** (Activation Complete)
   - Grants location permission
   - Enables background monitoring (premium trial)
   - Sets alert threshold to z-score 2.0

#### Ongoing Usage (Retention)

**Week 1-4**:
- Opens map 2-3x per week before walking in unfamiliar areas
- Receives 1 alert when walking through higher-risk zone
- Adjusts route based on alert
- Experiences value → continues using

**Month 2+**:
- Checks map less frequently (habit formed, trusts it)
- Relies on background alerts for peace of mind
- Recommends to friend after alert saves her from bad area (Referral)
- Converts to paid subscription when trial ends (Revenue)

---

## Success Criteria (MVP)

### Minimum Success Threshold
_What results would validate that we should persevere with this MVP?_

| Metric | Minimum Threshold | Stretch Goal | Status |
|--------|-------------------|--------------|--------|
| **Activation Rate** | 40% of signups view map | 60% | 🔴 |
| **Day 7 Retention** | 20% return after 1 week | 30% | 🔴 |
| **NPS Score** | 30 (more promoters than detractors) | 50 | 🔴 |
| **Core Action Usage** | 50% use map at least once/week | 70% | 🔴 |
| **Free to Paid** | 2% convert to premium | 5% | 🔴 |

**Decision Point**: After 3 months or 500 users, review metrics and decide:
- ✅ **Persevere**: Metrics meet/exceed minimum → continue building
- 🔄 **Iterate**: Metrics close but not quite → optimize features
- 🔀 **Pivot**: Metrics well below minimum → change strategy
- ❌ **Stop**: No signs of traction → explore different problem

---

## MVP Timeline

### Phase 1: Foundation (Weeks 1-4)
**Goal**: Core infrastructure and data pipeline

- [x] Set up crime data ETL pipeline
- [x] Implement H3 hexagon indexing
- [x] Build z-score calculation logic
- [x] Create API endpoints
- [ ] Deploy to production

### Phase 2: Web Interface (Weeks 5-8)
**Goal**: Interactive safety map on website

- [x] Design and build map interface
- [x] Integrate H3 hexagon visualization
- [x] Add user authentication
- [ ] Launch public beta

### Phase 3: Mobile App (Weeks 9-12)
**Goal**: Mobile app with background monitoring

- [x] Build React Native app
- [x] Implement background location service
- [x] Add push notification system
- [x] Create settings for customization
- [ ] Submit to App Store / Google Play

### Phase 4: Launch & Iterate (Weeks 13-16)
**Goal**: Get first users and start learning

- [ ] Soft launch to beta testers (50 users)
- [ ] Collect feedback and usage data
- [ ] Run problem validation interviews
- [ ] Iterate based on learning
- [ ] Public launch

---

## Technical Debt & Constraints

### Known Limitations (MVP)

**Data Quality**:
- Crime data may have reporting delays (days to weeks)
- Not all crime types are included
- Data accuracy varies by jurisdiction

**Scalability**:
- H3 calculation currently not optimized for real-time at scale
- Database queries may slow with >10k concurrent users
- Background service drains battery (mobile)

**Feature Gaps**:
- No route planning (only point location risk)
- No historical trends (only current snapshot)
- Limited crime type filtering

**Platform**:
- iOS background monitoring limited by Apple policies
- Android battery optimization may kill background service
- No offline mode

### Acceptable Trade-offs for MVP

_What are we okay with being imperfect?_

✅ **Okay for now**:
- Manual data updates (not real-time)
- Simple z-score calculation (not ML)
- Basic map UI (not polished design)
- Web-only analytics (no mobile dashboard)

❌ **Not okay** (must fix before launch):
- Inaccurate z-scores (breaks trust)
- Background service crashing (core value)
- Data privacy issues (legal risk)
- Broken authentication (security risk)

---

## Key Assumptions to Test

### Problem Assumptions
- [ ] Users struggle to assess neighborhood safety
- [ ] Current solutions (city websites, word of mouth) are insufficient
- [ ] Users would change behavior based on better data

### Solution Assumptions
- [ ] H3 hexagon precision (~700m) is granular enough to be useful
- [ ] Z-score statistical approach is understandable to average user
- [ ] Background monitoring provides enough value to justify battery drain

### Market Assumptions
- [ ] Urban areas have sufficient crime data to aggregate
- [ ] Users care enough about safety to use dedicated app
- [ ] Market size is large enough to build sustainable business

### Financial Assumptions
- [ ] Users will pay $4.99/month for premium features
- [ ] Cost per user is < $1/month (infrastructure)
- [ ] Can acquire users for < $10 CPA

**Testing Plan**: See `docs/product/experiments/experiment-log.md`

---

## MVP vs. Final Vision

### MVP (Now)
- Interactive safety map
- Basic z-score risk assessment
- Background monitoring with alerts
- Simple freemium model

### Final Vision (Future)
- AI-powered predictive crime modeling
- Real-time crime event integration
- Smart route planning with safety optimization
- Social safety network (friends, family)
- Integration with smart home, wearables
- Insurance partnerships for discount programs
- B2B white-label solutions for real estate, rideshare

**Path from MVP to Vision**: Build-Measure-Learn loop will guide us.

---

## MVP Retrospective Template

### Date: [YYYY-MM-DD]

**Participants**: [Team members]

### What Went Well ✅
- _[Success 1]_
- _[Success 2]_

### What Didn't Go Well ❌
- _[Challenge 1]_
- _[Challenge 2]_

### What We Learned 📚
- _[Learning 1]_
- _[Learning 2]_

### What We'll Change 🔄
- _[Action 1]_
- _[Action 2]_

### Pivot or Persevere? 🎯
**Decision**: [Persevere | Pivot | Iterate]  
**Reasoning**: _[Explain based on data]_

---

## Resources

- **The Lean Startup** - Eric Ries (Chapter 6: Build-Measure-Learn)
- **Running Lean** - Ash Maurya (Part 2: Validate Your Product)
- **Inspired** - Marty Cagan (Product Discovery)
- **Sprint** - Jake Knapp (Rapid prototyping)

**Related Documents**:
- Technical Architecture: `/docs/ARCHITECTURE.md`
- Feature Prioritization: `docs/product/mvp/feature-prioritization.md`
- Experiment Log: `docs/product/experiments/experiment-log.md`

