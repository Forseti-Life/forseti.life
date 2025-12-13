# Forseti Product Documentation

## Lean Startup Methodology Framework

This documentation follows the **Lean Startup** methodology developed by Eric Ries, focusing on validated learning, rapid experimentation, and the Build-Measure-Learn feedback loop.

---

## Documentation Structure

### 📋 Core Artifacts (Required)

```
docs/product/
├── README.md (this file)
├── lean-canvas/
│   └── forseti-lean-canvas.md          # One-page business model
├── customer-development/
│   ├── customer-segments.md            # Target customer personas
│   ├── problem-validation.md           # Problem interviews & findings
│   ├── solution-validation.md          # Solution interviews & findings
│   └── customer-interviews-log.md      # Interview notes & insights
├── experiments/
│   ├── experiment-log.md               # All experiments tracked
│   ├── mvp-experiments.md              # MVP validation tests
│   └── pivot-decisions.md              # Major strategy changes
├── metrics/
│   ├── pirate-metrics.md               # AARRR framework
│   ├── cohort-analysis.md              # User cohort tracking
│   └── key-metrics-dashboard.md        # North Star & supporting metrics
└── mvp/
    ├── mvp-definition.md               # Current MVP scope
    ├── feature-prioritization.md       # Feature backlog & priority
    └── technical-assumptions.md        # Key technical decisions
```

### 📊 Market Analysis

```
docs/market/
├── README.md
├── market-sizing.md                    # TAM/SAM/SOM analysis
├── competitive-analysis.md             # Competitor landscape
├── value-proposition.md                # Unique value proposition
└── go-to-market-strategy.md           # Customer acquisition plan
```

### 🔧 Technical Documentation

```
docs/technical/
├── README.md
├── architecture.md                     # System architecture (link to existing)
├── api-documentation.md                # API endpoints & specs
├── data-models.md                      # Database schemas
└── integration-guides.md               # Third-party integrations
```

---

## Lean Startup Core Principles

### 1. **Build-Measure-Learn Loop**
The fundamental cycle of the Lean Startup:
- **Build**: Create minimum viable product (MVP) to test hypotheses
- **Measure**: Collect data using actionable metrics
- **Learn**: Use data to validate or invalidate hypotheses
- **Decide**: Pivot (change strategy) or Persevere (stay course)

### 2. **Validated Learning**
Progress is measured by validated learning, not feature delivery:
- Test hypotheses with real customers
- Use qualitative and quantitative data
- Focus on learning > planning
- Fail fast, learn fast

### 3. **Innovation Accounting**
Three learning milestones:
1. **Establish Baseline**: Current state metrics
2. **Tune the Engine**: Optimize toward ideal
3. **Pivot or Persevere**: Major strategic decision

### 4. **Minimum Viable Product (MVP)**
Version of product that allows maximum validated learning with least effort:
- Not necessarily smallest product
- Fastest way to get through Build-Measure-Learn loop
- Focus on learning, not perfection

### 5. **Continuous Deployment**
Ship features continuously to get rapid feedback:
- Small batches
- Rapid iteration
- A/B testing
- Feature flags

---

## Lean Canvas (One-Page Business Model)

The Lean Canvas is our primary strategic document, adapted from Business Model Canvas.

### 9 Building Blocks:

1. **Problem** - Top 3 problems we're solving
2. **Customer Segments** - Target customers and early adopters
3. **Unique Value Proposition** - Single, clear, compelling message
4. **Solution** - Top 3 features addressing problems
5. **Channels** - Path to customers (marketing/distribution)
6. **Revenue Streams** - How we make money
7. **Cost Structure** - Fixed and variable costs
8. **Key Metrics** - Numbers that matter most
9. **Unfair Advantage** - What makes us unique and hard to copy

**Location**: `docs/product/lean-canvas/forseti-lean-canvas.md`

---

## Customer Development Process

Based on Steve Blank's Customer Development methodology, integrated into Lean Startup.

### 4 Stages:

1. **Customer Discovery**
   - Identify and understand customer problems
   - Validate problem exists and is worth solving
   - Find early adopters

2. **Customer Validation**
   - Test if the solution solves the problem
   - Validate pricing and business model
   - Prove customers will pay

3. **Customer Creation**
   - Build awareness and demand
   - Scale customer acquisition
   - Execute go-to-market strategy

4. **Company Building**
   - Transition from startup to company
   - Build departments and processes
   - Scale operations

**Current Stage**: _[To be documented]_

**Location**: `docs/product/customer-development/`

---

## Experiment Framework

Every feature, hypothesis, and assumption should be testable.

### Experiment Template:

```markdown
## Experiment: [Name]
**Date**: [Start Date]
**Hypothesis**: We believe that [doing this] for [these people] will achieve [this outcome]
**Success Criteria**: We'll know we're right when we see [measurable signal]
**Experiment Type**: [A/B Test | Survey | Interview | Landing Page | Smoke Test]
**Duration**: [Time period]
**Results**: [To be filled after experiment]
**Learning**: [What we learned]
**Decision**: [Pivot | Persevere | Iterate]
```

**Location**: `docs/product/experiments/experiment-log.md`

---

## Key Metrics (Pirate Metrics - AARRR)

Developed by Dave McClure, these are the key metrics for SaaS/mobile apps:

1. **Acquisition**: How users find us
2. **Activation**: First user experience (aha moment)
3. **Retention**: Do users come back?
4. **Revenue**: Monetization
5. **Referral**: Viral growth

### North Star Metric
The single metric that best captures the core value delivered to customers.

**Forseti North Star**: _[To be defined]_
- Options: Active weekly users, Safety alerts sent, Areas monitored, etc.

**Location**: `docs/product/metrics/pirate-metrics.md`

---

## MVP Definition & Scope

### MVP Principles:
- Smallest feature set to test core value hypothesis
- Must deliver on the unique value proposition
- Should enable one complete Build-Measure-Learn loop
- Focus on early adopters, not mainstream market

### Current MVP Status:
**Version**: _[To be documented]_
**Core Features**: _[To be listed]_
**Technical Implementation**: See `docs/product/mvp/mvp-definition.md`

---

## Pivot Types (When to Change Strategy)

Eric Ries identifies 10 types of pivots:

1. **Zoom-in**: Single feature becomes the product
2. **Zoom-out**: Product becomes single feature of larger product
3. **Customer Segment**: Solve same problem for different customers
4. **Customer Need**: Discover different problem worth solving
5. **Platform**: Change from app to platform or vice versa
6. **Business Architecture**: Change from high margin/low volume to low margin/high volume
7. **Value Capture**: Change monetization strategy
8. **Engine of Growth**: Change growth strategy (viral, sticky, paid)
9. **Channel**: Change distribution channel
10. **Technology**: Use different technology to achieve same solution

**Location**: `docs/product/experiments/pivot-decisions.md`

---

## Documentation Workflow

### 1. **Start with Lean Canvas** (Week 1)
Fill out the one-page business model to align on assumptions.

### 2. **Define Customer Segments** (Week 1-2)
Document who we're building for and their problems.

### 3. **Plan First Experiments** (Week 2)
Design tests for riskiest assumptions.

### 4. **Build MVP** (Weeks 2-4)
Minimum features to test core value hypothesis.

### 5. **Measure & Learn** (Ongoing)
Track metrics, run experiments, document learnings.

### 6. **Iterate or Pivot** (Monthly Review)
Review data and decide on strategy.

---

## Key Resources

### Books
- **The Lean Startup** - Eric Ries (2011)
- **Running Lean** - Ash Maurya (2012)
- **The Startup Owner's Manual** - Steve Blank (2012)
- **The Four Steps to the Epiphany** - Steve Blank (2005)

### Tools
- **Lean Canvas**: leanstack.com
- **Experiment Tracking**: Trello, Notion, or internal log
- **Analytics**: Mixpanel, Amplitude, Google Analytics
- **User Feedback**: Intercom, UserTesting, customer interviews

### Frameworks
- **Jobs to be Done**: Understanding customer motivations
- **Value Proposition Canvas**: Aligning solution to customer needs
- **Business Model Canvas**: Strategic business model design

---

## Next Steps

1. [ ] Complete Forseti Lean Canvas
2. [ ] Document customer segments and personas
3. [ ] Define current MVP scope and features
4. [ ] Establish key metrics and North Star
5. [ ] Plan first 3 experiments
6. [ ] Set up analytics and tracking
7. [ ] Schedule first customer interviews

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2024-12-13 | Initial framework setup | Keith Aumiller |

