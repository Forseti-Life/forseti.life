# Theory of Conspiracies Content Management

## 📋 Content Strategy Overview

This document outlines the recommended approach for managing content on the Theory of Conspiracies website, combining custom modules with theme integration for optimal cyberpunk presentation.

## 🔧 Custom Module: `theory_content`

### Content Types Created

#### 1. **Character** (`character`)
- **Purpose**: Detailed character profiles with relationships and trust levels
- **Fields**: Name, Role, Description, Image, Character Type, Affiliation, Status
- **Special Features**: 
  - Interactive trust network visualization
  - Cyberpunk glitch effects on names
  - Relationship mapping with other characters
  - Character arc tracking

#### 2. **Sequence** (`sequence`) 
- **Purpose**: Story sequence breakdowns from the screenplay
- **Fields**: Title, Act, Description, Scenes, Characters, Trust Changes
- **Special Features**:
  - Timeline visualization
  - Character interaction tracking
  - Trust level changes throughout sequences

#### 3. **Production Note** (`production_note`)
- **Purpose**: Behind-the-scenes content and development updates
- **Fields**: Title, Content, Images, Tags, Publication Date
- **Special Features**:
  - Blog-style presentation
  - Media gallery support
  - Category taxonomy

## 🎨 Cyberpunk Theme Integration

### Visual Features
- **Neon color palette**: Cyan, magenta, green, purple accents
- **Glitch effects**: Animated text distortion on character names
- **Matrix background**: Subtle code rain animations
- **Holographic elements**: Glowing borders and neon shadows
- **Interactive UI**: Hover effects, sound feedback, cursor trails

### JavaScript Enhancements
- **D3.js trust networks**: Interactive relationship visualizations
- **Typewriter effects**: Terminal-style text animation
- **Audio feedback**: Cyberpunk sound effects for interactions
- **Responsive animations**: Mobile-optimized cyberpunk effects

## 📝 Content Creation Workflow

### Adding Characters
1. Go to **Content > Add Content > Character**
2. Fill in character details:
   - **Name**: Character's full name
   - **Role**: Their function in the story
   - **Description**: Character background and personality
   - **Character Type**: Main, Supporting, AI Consciousness, etc.
   - **Affiliation**: Which faction/group they belong to
   - **Status**: Active, Deceased, Unknown, etc.

3. **Trust Relationships**: Add relationship data in structured format:
   ```yaml
   - character: "Sal Mueller"
     value: 70
     status: "Growing trust"
     level: "high"
   ```

### Adding Sequences
1. Go to **Content > Add Content > Sequence**
2. Structure sequence content:
   - **Title**: Sequence name (e.g., "01: First Assignment")
   - **Act**: Which act of the story (I, II, III)
   - **Description**: Sequence summary
   - **Characters**: Reference related character nodes
   - **Trust Changes**: Document relationship changes

### Production Notes
1. Go to **Content > Add Content > Production Note**
2. Add development updates, behind-the-scenes content
3. Use media fields for images, videos, documents
4. Tag with relevant categories for organization

## 🗂️ Content Organization

### Taxonomies (Recommended)
- **Character Types**: Main, Supporting, AI Consciousness, Antagonist
- **Story Acts**: Act I - Discovery, Act II - Development, Act III - Resolution
- **Affiliations**: David AI System, Keith AI Network, Mueller Family, Community
- **Production Categories**: Development, Behind-the-Scenes, Updates, Analysis

### Menus & Navigation
- **Main Menu**: Home, Characters, Story, Production, About
- **Character Menu**: All Characters, Main Characters, AI Consciousnesses
- **Story Menu**: Act I, Act II, Act III, Timeline, Character Arcs

## 🚀 Advanced Features

### Future Enhancements
1. **Interactive Timeline**: Full story progression with character arcs
2. **Trust Level Tracking**: Dynamic relationship changes over time
3. **Character Comparison**: Side-by-side character analysis
4. **Sequence Search**: Find sequences by character, location, or theme
5. **Production Blog**: Regular development updates with media

### Technical Integration
- **Search API**: Enhanced search for characters and sequences
- **Views Integration**: Custom listing pages with cyberpunk styling
- **Media Management**: Image galleries with cyberpunk lightbox
- **Social Sharing**: Cyberpunk-styled sharing buttons

## 📱 Mobile Optimization

All cyberpunk effects are optimized for mobile devices:
- **Reduced animations** on smaller screens
- **Touch-friendly interactions**
- **Responsive grid layouts**
- **Performance-optimized** JavaScript

## 🔒 Access Control

### Content Permissions
- **Anonymous users**: View published content
- **Authenticated users**: Comment on content
- **Content editors**: Create and edit all content types
- **Site administrators**: Full access to configuration

## 🎯 SEO & Performance

### Optimization Features
- **Structured data** for character profiles
- **Meta tag integration** for social sharing
- **Image optimization** for cyberpunk graphics
- **Lazy loading** for performance
- **Cyberpunk-themed** social media cards

## 🛠️ Development Guidelines

### Adding New Content Types
1. Create content type configuration in `config/install/`
2. Add corresponding templates in `templates/`
3. Create CSS/JS in module's asset directories
4. Update `theory_content.libraries.yml`
5. Add theme hooks in `theory_content.module`

### Customizing Cyberpunk Effects
- Edit CSS files in `/css/` directory
- Modify JavaScript behaviors in `/js/` directory
- Update color variables in theme SCSS files
- Test across different devices and browsers

This content management system provides a solid foundation for showcasing the Theory of Conspiracies screenplay with full cyberpunk aesthetic integration!