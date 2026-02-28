# PF2E Gamemastery Guide — Chapter 1: Gamemastery Basics
## Systematic Requirements Analysis (Paragraph by Paragraph)

**Source:** `reference documentation/PF2E Gamemastery Guide.txt` (initial pass)
**Status:** In progress

---

## SECTION: Chapter Scope and GM Priorities

### Paragraph — Chapter topic map
> "Running Encounters (page 10)... Running Exploration (page 17)... Running Downtime (page 22)... Adjudicating Rules (page 28)... Resolving Problems (page 30)... Narrative Collaboration (page 32)... Special Circumstances (page 33)... Rarity in Your Game (page 35)... Campaign Structure (page 36)... Adventure Design (page 40)... Encounter Design (page 46)... Drawing Maps (page 52)."

Requirements identified:
- REQ: The GM support layer shall treat encounter, exploration, and downtime as distinct operating modes with separate guidance surfaces.
- REQ: The documentation model shall preserve explicit cross-links between mode-running guidance and encounter/adventure/map design guidance.

---

## SECTION: General Advice

### Paragraph — Session zero
> "A session for building characters is commonly called 'session zero'... lets players share character details... gives veterans the chance to help less experienced players... gives you a better understanding of the characters and help the players integrate them into the adventure."

Requirements identified:
- REQ: Campaign setup flow shall support an explicit pre-play "session zero" phase.
- REQ: Session zero records shall support party links/relationships prior to first adventure session.
- REQ: Session zero shall capture GM-facing character integration notes for later adventure hooks.

---

### Paragraph — Pacing game sessions
> "Most sessions should have lulls in the action punctuated by challenges... break things up into smaller scenes and memorable moments... About 20 minutes before a play session is scheduled to conclude, it can be beneficial to figure out how you'd like to end... cliffhanger..."

Requirements identified:
- REQ: Session planner should support pacing plans with alternating high-intensity and low-intensity scenes.
- REQ: Session planner should support explicit end-of-session targets (cliffhanger, reveal, pre-combat pause, etc.).
- REQ: Between-session task handling should support asynchronous resolution items (treasure division, leveling, downtime).

---

### Paragraph — Stakes and consequences
> "A GM should always convey a clear picture of the stakes and consequences... Consequences should be specific and evocative... It's usually best if the PCs can foresee the consequences, at least in a general sense... keep [subversions] to a minimum..."

Requirements identified:
- REQ: Scenario definitions shall include explicit failure stakes and success outcomes visible to players.
- REQ: Consequence logic should prefer predictable cause-effect chains over frequent opaque reversals.
- REQ: Reward hooks shall support accomplishment XP signaling when major goals are achieved.

---

### Paragraph — Failing forward
> "'Failing forward' means finding a way to progress the story instead of just saying, 'That didn't work.' ... a failure might still impart more information, reveal a way to improve their chances next time, or even cause unforeseen difficulties."

Requirements identified:
- REQ: Check-resolution flow should support non-blocking failure outcomes that still advance state.
- REQ: Failure outcomes should support one or more of: clue reveal, next-attempt advantage setup, or complication insertion.
- REQ: The system shall still allow hard stops when no credible fail-forward branch exists.

---

### Paragraph — Secret checks
> "During play, you roll some checks in secret... It can be handy to keep a list of the PCs' modifiers on hand... check in anytime the PCs level up..."

Requirements identified:
- REQ: GM tools shall support secret-check mode that hides roll outcomes from players.
- REQ: GM dashboard shall maintain a quick-reference cache of key PC modifiers (Perception, Will, common Recall Knowledge skills).
- REQ: Modifier cache should support easy refresh triggers on level-up/stat-change events.

---

## SECTION: Running Encounters

### Paragraph — Encounter GM focus
> "As a GM, you should primarily focus on... Answering questions quickly... Building anticipation... setting a rapid pace... letting players know when they're up and 'on deck'... showing immediate consequences."

Requirements identified:
- REQ: Encounter UI shall provide turn-order visibility including current actor and next-up actor.
- REQ: Encounter log shall surface immediate action-to-consequence feedback.
- REQ: Encounter assist should optimize for fast rulings over exhaustive interruption for minor edge cases.

---

### Paragraph — Looking up rules
> "For something that isn't too impactful, it's better to just make a ruling on the spot and move on... look up something that's both significant and heavily rules-dependent... summarize."

Requirements identified:
- REQ: Adjudication policy shall differentiate minor rulings (resolve now, verify later) from major rules-dependent rulings (pause and verify).
- REQ: Rules references shown in encounter mode should be summarized by default to reduce table-flow disruption.

---

### Paragraph — Rewinding
> "The best policy is usually to let them rewind as needed within their own turn but stop them before they intrude into someone else's... Try to be consistent about what kinds of things you will rewind for and when."

Requirements identified:
- REQ: Turn manager shall permit same-turn rewinds and block cross-turn rewinds by default.
- REQ: Rewind policy shall be configurable but consistent within a session.
- REQ: Lightweight corrections (for example applying omitted static damage) may be allowed outside-turn without full rewind.

---

### Paragraph — Initiative and stealth
> "Anyone who's Avoiding Notice should attempt a Stealth check for their initiative... compare their Stealth check for initiative to the Perception DC of their enemies... You can give them the option to roll Perception instead, but if they do they forsake their Stealth."

Requirements identified:
- REQ: Initiative subsystem shall support skill-based initiative selection (Stealth when Avoiding Notice).
- REQ: Encounter start resolution shall compare Stealth-initiative results against enemy Perception DCs to determine initial undetected state per observer.
- REQ: Choosing Perception initiative in place of Stealth shall remove stealth-preservation at encounter start.

---

### Paragraph — Batch initiative
> "If you have multiple enemies of the same type... you can roll just one initiative check for all of them. They still take individual turns and can still individually change their initiative by Delaying."

Requirements identified:
- REQ: Encounter tooling shall support grouped initiative for identical enemy sets.
- REQ: Grouped initiative shall preserve individual turns and individual Delay behavior.
- REQ: Grouped initiative should be optional and marked as a speed-of-play optimization.

---

### Paragraph — Aid and Ready adjudication
> "AID... preparation should be specific to the task at hand... in a proper position to help... determine how long the preparation takes... READY... trigger must be something that happens in the game world and is observable by the character..."

Requirements identified:
- REQ: Aid validation shall require task-specific preparation, valid helping position, and communication feasibility.
- REQ: Aid timing shall scale with task scope (single-action support for short tasks; sustained support for long tasks).
- REQ: Ready trigger validation shall reject purely meta triggers (for example HP thresholds or unobservable rules tags) and require in-world observables.

---

### Paragraph — Take Cover and map abstraction
> "TAKE COVER... usually just need a large enough object... might require them to Drop Prone... let them combine this with the Take Cover action... A grid and miniatures can make it easier... there’s still room for improvisation... give players minor boosts that fit the story."

Requirements identified:
- REQ: Cover adjudication shall use physical silhouette plausibility to determine cover availability.
- REQ: Take Cover resolution should support optional prone-integration when posture change is required by terrain/object geometry.
- REQ: Tactical map mode shall allow narrative micro-adjustments (minor movement/position boosts) when approved by GM adjudication.

---

## SECTION: Running Exploration

### Paragraph — Exploration goals
> "As you run exploration, keep the following basic goals in mind... evoke the setting with sensory details... shift the passage of time... present small-scale mysteries... look for ways to move the action forward... plan effective transitions to encounters."

Requirements identified:
- REQ: Exploration flow shall support sensory-scene prompts, variable time compression/expansion, and mystery hooks.
- REQ: Exploration checks should support fail-forward outcomes consistent with forward motion and added complications.
- REQ: Exploration engine shall support clean transition hooks into encounter mode.

---

### Paragraph — Evocative environments
> "Convey their surroundings by appealing to the players’ senses... think about what’s familiar versus novel... the more you explain something, the more important it seems."

Requirements identified:
- REQ: Scene-description templates shall include multi-sensory fields (sight, sound, smell, temperature, texture).
- REQ: Environment authoring shall support explicit familiar-vs-novel tagging to guide emphasis.
- REQ: GM aid should warn that repeated/high-detail emphasis implies significance to players.

---

### Paragraph — Flow of time
> "You rarely measure exploration down to the second or minute... nearest 10-minute increment typically does the job... Time will seem to slow down the more detail you give... speed up or slow down... when establishing or progressing the story."

Requirements identified:
- REQ: Exploration time tracking shall default to coarse increments (typically 10-minute units, hour-scale for long travel).
- REQ: Narrative pacing controls shall tie description depth to perceived time dilation.
- REQ: GM controls shall allow explicit slow-time moments for key decisions, emotional beats, and new-area entry.

---

## SECTION: Running Downtime

### Paragraph — Downtime objectives and scope
> "You can use downtime... demonstrate changes to the setting... emphasize planning... keep the number of rolls small... switch to encounter or exploration as needed..."

Requirements identified:
- REQ: Downtime subsystem shall support world-state updates tied to prior PC accomplishments.
- REQ: Downtime resolution shall prefer low-roll-count summaries over granular roll spam by default.
- REQ: Downtime actions shall be allowed to branch into encounter or exploration scenes when triggered by outcomes.

---

### Paragraph — Depth of downtime
> "Determine how involved your group wants downtime to be at the start of the game... Downtime should rarely last a whole session... about a half hour between significant adventures..."

Requirements identified:
- REQ: Campaign configuration shall include a downtime-depth setting (light/medium/deep) adjustable over campaign lifetime.
- REQ: Session planner shall support recommended real-time budgets for downtime blocks.
- REQ: Downtime narration depth shall scale up only when player intent/questions indicate high story value.

---

### Paragraph — Group engagement and no-downtime campaigns
> "One major challenge of downtime is keeping the whole group involved... combine multiple people’s tasks into one... If a player really isn’t interested... one-sentence description... campaigns without downtime... summarize what happens between adventures and skip downtime rules."

Requirements identified:
- REQ: Downtime scheduler shall support scene fusion for multiple PC tasks in shared contexts.
- REQ: Participation controls shall support per-player low-detail summaries when a player opts out of downtime roleplay.
- REQ: Campaign mode shall permit disabling downtime mechanics in favor of between-adventure summary resolution.

---

## SECTION: Adjudicating Rules

### Paragraph — Core adjudication principles
> "Strive to make quick, fair, and consistent rulings... make a call and get on with play... review your decision after the session... explain why you’re ruling a certain way and compare to past rulings."

Requirements identified:
- REQ: Rules-adjudication policy shall prioritize speed, fairness, consistency, and post-session correction loops.
- REQ: Ruling records shall support precedent linkage so future rulings can reference prior analogous decisions.
- REQ: The system shall treat accumulated precedents as candidate house-rule seeds.

---

## SECTION: Resolving Problems

### Paragraph — Table problem handling and TPKs
> "Keep in mind the primary reason... have fun... total party kills... discuss [TPKs] with players... offer opportunities to avoid TPK... game should continue only if players want it to."

Requirements identified:
- REQ: Campaign safety/governance setup shall include explicit table preferences for lethality and TPK handling.
- REQ: Encounter-control guidance shall include non-forced escape/capture/aid branches to preserve player agency under looming TPK risk.
- REQ: Post-TPK workflow shall require explicit player consent before campaign continuation.

---

## SECTION: Narrative Collaboration

### Paragraph — Agency and collaboration models
> "Most players want their contributions to shape the campaign’s story... idea farm... creative collaboration... decentralized storytelling..."

Requirements identified:
- REQ: Campaign framework shall support selectable collaboration modes: GM-led with feedback, shared content ownership, and decentralized narration.
- REQ: Collaboration workflows shall include periodic checkpoints for player input at campaign start and major milestones.
- REQ: Shared-authoring mode shall support ownership logs for player-authored setting/NPC components.

---

## SECTION: Rarity in Your Game

### Paragraph — Four rarities and context/access
> "Common... Uncommon... Rare... Unique... Just because something is common or uncommon in one context doesn’t necessarily mean it’s the same in others... Access entries... Starting elements..."

Requirements identified:
- REQ: Content catalog shall encode rarity tiers (common, uncommon, rare, unique) with distinct default access semantics.
- REQ: Rarity evaluation shall be context-sensitive by locale/culture/campaign framing rather than globally static.
- REQ: Uncommon-option access entries shall grant common-like availability when character criteria are met.
- REQ: Character-creation pipeline shall support uncommon/rare starting elements via GM campaign allowlists.

---
