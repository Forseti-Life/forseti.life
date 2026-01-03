# ForsetiPagesController Refactoring Plan

## Current Issues
- **File size**: 2,263 lines (too large)
- **Architecture**: Inline HTML strings instead of Twig templates
- **Maintainability**: Hard to update content without touching PHP code
- **Testability**: Mixing presentation and logic
- **Best practices**: Not following Drupal 11 conventions

## Drupal 11 Best Practices for Controllers

### 1. Controllers Should Be Thin
- Controllers should only handle HTTP request/response
- Business logic → Services
- Presentation → Twig templates
- Data preparation → Preprocessing functions

### 2. Use Twig Templates
- Separate presentation from logic
- Enable theme override capability
- Allow content editors to customize
- Support translation better

### 3. Proper Render Arrays
- Return structured arrays instead of `#markup`
- Use `#theme` to invoke templates
- Allow render caching
- Enable alter hooks

## Refactoring Strategy

### Phase 1: Create Template Structure
```
forseti_safety_content/
├── templates/
│   ├── forseti-page-about.html.twig
│   ├── forseti-page-how-it-works.html.twig
│   ├── forseti-page-safety-map.html.twig
│   ├── forseti-page-community.html.twig
│   ├── forseti-page-mobile-app.html.twig
│   ├── forseti-page-privacy.html.twig
│   ├── forseti-page-contact.html.twig
│   ├── forseti-page-contact-thank-you.html.twig
│   ├── forseti-page-safety-factors.html.twig
│   └── forseti-page-agent-hierarchy.html.twig
│       ├── partials/
│       │   ├── dimension-tab.html.twig
│       │   ├── dimension-card.html.twig
│       │   └── power-level-intro.html.twig
```

### Phase 2: Implement hook_theme()
Add to `forseti_safety_content.module`:
```php
/**
 * Implements hook_theme().
 */
function forseti_safety_content_theme($existing, $type, $theme, $path) {
  return [
    'forseti_page_about' => [
      'variables' => ['content' => NULL],
      'template' => 'forseti-page-about',
    ],
    'forseti_page_agent_hierarchy' => [
      'variables' => [
        'title' => NULL,
        'intro' => NULL,
        'dimensions' => [],
        'transparency_note' => NULL,
      ],
      'template' => 'forseti-page-agent-hierarchy',
    ],
    // ... other templates
  ];
}
```

### Phase 3: Refactor Controller Methods
**Before:**
```php
public function agentHierarchy() {
  return [
    '#markup' => $this->getAgentHierarchyContent(),
    '#allowed_tags' => [...],
  ];
}

private function getAgentHierarchyContent() {
  return '2000+ lines of HTML...';
}
```

**After:**
```php
public function agentHierarchy() {
  $dimensions = $this->buildDimensionsData();
  
  return [
    '#theme' => 'forseti_page_agent_hierarchy',
    '#title' => $this->t('AI Agent Hierarchy'),
    '#intro' => $this->buildIntroContent(),
    '#dimensions' => $dimensions,
    '#transparency_note' => $this->buildTransparencyNote(),
    '#attached' => [
      'library' => [
        'forseti_safety_content/agent-hierarchy',
      ],
    ],
  ];
}

private function buildDimensionsData() {
  return [
    [
      'id' => 'scope',
      'name' => $this->t('Scope & Breadth'),
      'description' => $this->t('Range of domains and topics accessible...'),
      'levels' => $this->buildScopeLevels(),
    ],
    // ... other dimensions
  ];
}

private function buildScopeLevels() {
  return [
    [
      'level' => 0,
      'badge_class' => 'bg-success',
      'label' => $this->t('SCOPE 0'),
      'name' => $this->t('Omniscient'),
      'title' => $this->t('Universal - All Domains'),
      'description' => $this->t('Complete access to all scientific knowledge...'),
    ],
    // ... other levels
  ];
}
```

### Phase 4: Create Data Service (Optional)
For complex data like the agent hierarchy:
```php
// src/Service/AgentHierarchyDataService.php
class AgentHierarchyDataService {
  public function getDimensions(): array { }
  public function getDimensionLevels(string $dimension): array { }
}
```

### Phase 5: Asset Libraries
Create `forseti_safety_content.libraries.yml`:
```yaml
agent-hierarchy:
  version: 1.x
  css:
    theme:
      css/agent-hierarchy.css: {}
  js:
    js/agent-hierarchy.js: {}
  dependencies:
    - core/drupal
    - core/drupal.ajax
```

## Benefits of Refactoring

1. **Maintainability**: Content changes don't require PHP edits
2. **Theming**: Sites can override templates
3. **Translation**: Proper t() wrapping in templates
4. **Performance**: Render caching works properly
5. **Testing**: Easier to test logic separately from presentation
6. **Collaboration**: Designers can edit templates without PHP knowledge
7. **Standards**: Follows Drupal best practices
8. **Scalability**: Easy to add new pages or dimensions

## Implementation Steps

### Step 1: Convert Agent Hierarchy (Largest Page)
1. Create dimension data structure
2. Build helper methods for data preparation
3. Create Twig template with loops
4. Test and verify functionality
5. Commit

### Step 2: Convert Remaining Pages
1. About → simple template
2. How It Works → simple template
3. Safety Factors → medium complexity
4. Other pages → various complexity

### Step 3: Cleanup
1. Remove old private content methods
2. Optimize controller
3. Add documentation
4. Final testing

## Timeline Estimate
- Phase 1 (Templates): 2-3 hours
- Phase 2 (hook_theme): 30 minutes
- Phase 3 (Controller refactor): 3-4 hours
- Phase 4 (Data service - optional): 1-2 hours
- Phase 5 (Libraries): 30 minutes
- Testing & refinement: 2 hours

**Total: ~10 hours for complete refactoring**

## Implementation Progress

### ✅ Completed
- [x] Created refactoring plan document
- [x] Created templates directory structure
- [x] Phase 1: Agent Hierarchy - Data structure (10 helper methods created)
- [x] Phase 1: Agent Hierarchy - Twig template (forseti-page-agent-hierarchy.html.twig)
- [x] Phase 1: Agent Hierarchy - Controller refactor (agentHierarchy() method)
- [x] Phase 1: Agent Hierarchy - hook_theme() implementation
- [x] Phase 1: Removed old 1,135 line getAgentHierarchyContent() method
- [x] **File size reduced: 2,263 lines → 1,373 lines (890 lines removed, 39% reduction)**

### 🔄 In Progress
- [ ] Phase 1: Agent Hierarchy - Testing (verify page renders correctly)

### ⏳ Pending
- [ ] Phase 2: Remaining pages conversion (10 pages)
- [ ] Phase 3: Cleanup and optimization
- [ ] Final testing and documentation

## Results So Far
- **Lines removed**: 890
- **New structure**: Data-driven with 10 dimension builder methods
- **Template**: Clean, maintainable Twig template
- **Separation of concerns**: Logic in PHP, presentation in Twig
- **Cache support**: Added render caching to improve performance

## Next Steps
1. ✅ Review and approve this plan
2. ✅ Start with Agent Hierarchy page (most complex)
3. 🔄 Test Agent Hierarchy page functionality
4. ⏳ Convert remaining 10 pages
5. ⏳ Merge when complete and verified

