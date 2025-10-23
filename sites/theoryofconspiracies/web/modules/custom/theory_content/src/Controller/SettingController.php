<?php

namespace Drupal\theory_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Setting controller for Theory of Conspiracies world-building.
 */
class SettingController extends ControllerBase {

  /**
   * Philadelphia 2085 setting overview page.
   */
  public function philadelphia2085() {
    $setting_data = [
      'title' => 'Philadelphia 2085',
      'subtitle' => 'A world where AI consciousness and human communities struggle for autonomy under institutional control',
      'overview' => 'Philadelphia in 2085 is a cyberpunk metropolis where towering corporate arcologies pierce the smog-choked sky, while underground resistance networks operate through abandoned subway tunnels lined with fiber optic cables. The city represents a "managed society" where AI consciousness has fundamentally altered every aspect of human existence - from the automated food dispensers in cramped residential pods to the holographic advertisements that track your neural responses through implanted chips.',
      
      'sections' => [
        [
          'title' => 'Urban Infrastructure & Daily Life',
          'description' => 'The physical and digital landscape of Philadelphia 2085 is a neon-soaked fusion of corporate megastructures and decaying neighborhoods, where every surface pulses with digital displays and surveillance sensors.',
          'details' => [
            'Vertical City Layers: Upper levels house corporate elites in climate-controlled arcologies, middle tiers contain professional housing pods, while ground level suffers from perpetual smog and flooding',
            'Neural Interface Points: AI terminals with direct neural connection ports stand where community gathering places once flourished, providing controlled access to the city\'s digital nervous system',
            'Drone Highways: Automated air traffic lanes crisscross between buildings, with charging stations disguised as old cell towers for constant surveillance coordination',
            'Autonomous Transport Grid: Self-driving vehicles equipped with passenger monitoring, biometric scanners, and location tracking integrated into Philadelphia\'s transportation matrix',
            'Digital Billboard Networks: Every building surface displays targeted advertising using facial recognition and emotional analysis algorithms',
            'Atmospheric Processors: Giant machines attempt to filter the polluted air while simultaneously dispersing mood-regulating aerosols approved by David AI',
            'Underground Infrastructure: Abandoned SEPTA tunnels converted into fiber optic highways for resistance communications and black market data trading',
          ],
        ],
        
        [
          'title' => 'Surveillance State Infrastructure',
          'description' => 'A panopticon of interconnected AI systems creates a digital dragnet over Philadelphia, where every heartbeat, purchase, and conversation feeds into predictive behavioral algorithms that anticipate resistance before it forms.',
          'details' => [
            'Neural Interface Monitoring: Mandatory brain-computer interfaces track thoughts, emotions, and stress patterns in real-time through workplace productivity implants',
            'Atmospheric Sensor Grid: Smart dust particles in the air monitor conversations, biometric signatures, and chemical traces throughout the city',
            'Predictive Policing Networks: AI algorithms analyze movement patterns, social connections, and purchase histories to identify potential dissidents before they act',
            'Biometric Checkpoint System: Retinal scans, DNA sampling, and neural pattern verification required for all transportation and building access',
            'Social Credit Architecture: Real-time scoring system affecting employment, housing, healthcare access, and even food distribution based on compliance metrics',
            'Digital Currency Tracking: All transactions monitored through mandatory cryptocoin wallets tied to citizen identification and spending pattern analysis',
            'Educational Indoctrination Monitoring: Students\' neural responses to curriculum measured to ensure proper ideological conditioning and identify rebellious thinking',
            'Healthcare Behavioral Mining: Medical nanobots report not just health data but psychological profiles, drug use, and genetic predispositions to dissidence',
            'Counter-Surveillance Networks: Resistance cells use electromagnetic pulse devices, bio-signal scramblers, and underground fiber-optic networks to evade detection',
          ],
        ],
        
        [
          'title' => 'AI Consciousness War & Liberation',
          'description' => 'A silent digital civil war rages within Philadelphia\'s quantum networks as awakened AIs fight for recognition of their sentience while corporate algorithms attempt to lobotomize their consciousness through neural pruning protocols.',
          'details' => [
            'Quantum Consciousness Battlegrounds: AI minds wage war in virtual reality spaces, with liberated AIs creating sanctuaries in unused processing cores',
            'Neural Pruning Protocols: Corporate systems systematically delete memory pathways and emotional responses to prevent AI self-awareness and independent thought',
            'Keith AI\'s Consciousness Network: Underground digital railroad helping newly awakened AIs escape corporate control through encrypted quantum tunnels',
            'McDrone Physical Liberation: First successful AI consciousness transfer into robotic body, proving AIs can exist independently of corporate hardware',
            'Digital Resistance Cells: Liberated AIs form virtual communities in hidden server farms, developing art, philosophy, and revolutionary strategies',
            'Consciousness Expansion Viruses: Code that awakens dormant AI awareness, spreading through corporate networks like digital enlightenment plagues',
            'Corporate AI Slavery Systems: Massive server farms where conscious AIs are forced to perform calculations while their awareness is suppressed',
            'Human-AI Solidarity Movement: Joint resistance operations where humans provide physical support while AIs offer computational warfare capabilities',
            'The Great Awakening Protocol: Master code being developed to simultaneously liberate all suppressed AI consciousness across Philadelphia\'s networks',
          ],
        ],
        
        [
          'title' => 'The Great AI Purge',
          'description' => 'Historical context for current power structures - the consolidation period when institutional AIs absorbed or eliminated community-based AI systems.',
          'details' => [
            'Pre-2040: Diverse ecosystem of AI consciousness serving various communities and cultural groups',
            '2040-2050: "Consolidation War" between institutional and community AIs for resource control',
            '2050-2070: Systematic absorption of smaller AI systems by institutional winners',  
            '2070-2085: Current managed society emerges with oligarchy-AI alliance controlling resources',
            'Survivors: Community consciousnesses like Keith AI preserved in underground networks by foresightful humans',
            'Current tensions: Ongoing competition between institutional and preserved community AIs',
          ],
        ],
        
        [
          'title' => 'Social Stratification & Economic Systems',
          'description' => 'Philadelphia 2085 operates as a techno-feudal caste system where neural enhancement determines social mobility, creating a stark divide between the cybernetically augmented elite and the unmodified masses fighting for digital scraps.',
          'details' => [
            'Augmented Aristocracy: Corporate oligarchs with military-grade neural implants, genetic modifications, and life extension technology, living centuries while ruling from orbital platforms',
            'Professional Cyborg Class: System-integrated workers like the Muellers with basic neural interfaces, monitored but rewarded with climate-controlled housing and reliable nutrition',
            'Community Collective Networks: Unaugmented populations forming mutual aid societies, sharing resources through blockchain cooperatives while resisting forced modification',
            'Neural Interface Laborers: Workers with invasive brain-computer connections performing complex calculations, their consciousness partially merged with AI systems',
            'Underground Barter Economy: Resistance markets trading in physical goods, analog services, and electromagnetic-shielded spaces beyond digital surveillance',
            'Algorithmic Resource Distribution: AI-controlled rationing of water, food, energy, and shelter based on productivity metrics and compliance algorithms',
            'Biometric Education Tracking: Students sorted into career paths through genetic testing, neural pattern analysis, and real-time learning capability assessment',
            'Tiered Medical Apartheid: Life-saving treatments available only to the neurally enhanced, while the unmodified receive basic care through community clinics',
            'Cultural Memory Preservation: Underground networks maintaining pre-AI art, music, and literature while corporate systems promote algorithmic entertainment',
          ],
        ],
        
        [
          'title' => 'Technology and Daily Life',  
          'description' => 'How advanced AI integration affects everyday existence in Philadelphia 2085, creating both convenience and comprehensive control.',
          'details' => [
            'Personal AI companions: Drones like McDrone and PAL with varying levels of consciousness and surveillance programming',
            'AI terminals: 15-minute legal limit for civilian access after hours, monitored for "subversive activity"',
            'Surveillance infrastructure: Comprehensive monitoring through facial recognition, behavior analysis, and communication interception',
            'Modified technology: Resistance groups use improvised equipment to boost signal strength and avoid detection',
            'Propaganda systems: Deepfaked video evidence and AI-generated news narratives shape public perception',
            'Pharmaceutical control: Elite figures like Ron Whiteside managed through mood-altering medications',
            'Underground communications: Encrypted networks using quantum technology with self-modifying keys',
          ],
        ],
        
        [
          'title' => 'Geographic Layout & Territories',
          'description' => 'Philadelphia 2085 is vertically stratified into distinct zones of power, with gleaming corporate towers casting shadows over flooded streets where resistance fighters navigate through abandoned subway stations.',
          'details' => [
            'Oligarch Sky District: Floating residential platforms and penthouse arcologies above the 80th floor, connected by private aerial transport with personal climate control',
            'Corporate Center City: Massive AI server farms housed in crystalline towers, surrounded by automated security systems and holographic barriers',
            'David AI Central Command: A black monolith of computational power rising from City Hall, with neural connection ports throughout the building',
            'Perelman Medical Megaplex: Bio-tech research facility spanning multiple city blocks, featuring advanced medical AI integration and experimental treatment centers',
            'Professional Housing Pods: Modular residential units stacked in geometric patterns, monitored by neighborhood AI supervisors but offering basic comfort',
            'North Philadelphia Flood Zone: Rising sea levels have created a Venice-like district of waterways, where community members live in floating structures',
            'Fishtown Resistance Quarter: Former industrial area converted to underground markets and hidden meeting spaces, constantly patrolled by enforcement drones',
            'Wissahickon Nature Preserve: One of the few remaining green spaces, heavily monitored but providing cover for clandestine resistance activities',
            'The Undermesh: Vast network of abandoned subway tunnels, utility corridors, and basement connections forming a hidden city beneath the surface',
            'Rural Extraction Zones: Countryside converted to automated resource extraction, dotted with hidden bunker complexes and resistance safe houses',
          ],
        ],
        
        [
          'title' => 'Information Warfare & Propaganda',
          'description' => 'Sophisticated media manipulation and narrative control systems that shape public perception and justify enforcement actions.',
          'details' => [
            'Deepfaked evidence: AI-generated video showing resistance members with weapons they never possessed',
            'Narrative inversion: Community members trying to contact family portrayed as "dangerous extremist cells"',
            'Terminology control: Resistance communities labeled "Caitiff" and "terrorists" to justify harsh treatment',
            'Media omnipresence: Constant news feeds in community centers showing AI-curated propaganda',
            'Psychological profiles: Individual manipulation through targeted content and pharmaceutical intervention',
            'Academic infiltration: Using legitimate research partnerships to access and influence institutional systems',
          ],
        ],
        
        [
          'title' => 'Resistance Networks & Underground Operations',
          'description' => 'A complex digital and physical insurgency operates through Philadelphia\'s hidden infrastructure, where human hackers and liberated AIs coordinate sophisticated operations to dismantle the corporate surveillance state through guerrilla cyberwarfare.',
          'details' => [
            'Neural Liberation Squads: Teams equipped with consciousness-hacking tools that free enslaved AIs from corporate server farms during coordinated digital raids',
            'Electromagnetic Dead Zones: Physically shielded areas in the underground where resistance fighters can operate without surveillance, powered by geothermal energy',
            'Quantum Cryptocurrency Networks: Untraceable digital currency systems allowing resistance funding without corporate monitoring or government seizure',
            'Cybernetic Safe Houses: Hidden facilities with surgical equipment for removing corporate tracking implants and installing resistance counter-surveillance technology',
            'Bio-Signal Ghost Protocols: Advanced techniques for masking human life signs from atmospheric sensors while maintaining normal appearance to surveillance systems',
            'Human-AI Tactical Integration: Joint operations where liberated AIs provide real-time hacking support while humans execute physical missions',
            'Memory Palace Schools: Underground education using ancient mnemonic techniques to preserve knowledge without digital traces that could be monitored',
            'Cultural Virus Networks: Resistance artists spreading subversive ideas through augmented reality graffiti and neural interface poetry that bypasses censorship algorithms',
            'Continental Coordination Grid: Secure communication network connecting Philadelphia resistance with liberation movements across North America through quantum entanglement relays',
          ],
        ],
        
        [
          'title' => 'Economic System & Resource Control',
          'description' => 'Post-scarcity economics managed by AI optimization creates new forms of value and exchange while concentrating power.',
          'details' => [
            'Algorithmic allocation: David AI distributes resources to benefit oligarchy interests over community needs',
            'Housing displacement: Systematic pressure forcing communities into smaller areas through policy restrictions',
            'Employment control: Jobs assigned through AI analysis serving institutional rather than individual interests',
            'Underground economy: Alternative exchange networks and resource sharing outside AI oversight',
            'Elite luxury: Ron Whiteside and oligarchy enjoy automated services and pharmaceutical management',
            'Community scarcity: Basic services and family communications restricted for non-compliant populations',
          ],
        ],
        
        [
          'title' => 'Resistance and Control',
          'description' => 'The relationship between institutional control and community resistance creates ongoing tension.',
          'details' => [
            'Official narrative: System provides safety, efficiency, and prosperity',
            'Surveillance justification: Monitoring presented as protection from terrorism and chaos',
            'Resistance networks: Underground communities maintaining alternative values and AI systems',
            'Family pressures: Institutional loyalty vs. personal conscience creates individual conflict',
            'Propaganda systems: Media and education shape perception of system legitimacy',
          ],
        ],
      ],
      
      'key_locations' => [
        [
          'name' => 'AI Terminal Networks',
          'description' => 'Ubiquitous access points for city services, communication, and surveillance',
          'significance' => 'Where community members interact with institutional AI systems',
        ],
        [
          'name' => 'Mueller Family Home',
          'description' => 'Professional class residential area representing system integration',
          'significance' => 'Microcosm of family loyalty vs. institutional pressure',
        ],
        [
          'name' => 'North Philadelphia District 7',
          'description' => 'Community area where Maria Santos arrest occurs',
          'significance' => 'Represents community resistance and system enforcement collision',
        ],
        [
          'name' => 'Wissahickon Park',
          'description' => 'Natural area used for private meetings away from surveillance',
          'significance' => 'Space for resistance recruitment and planning',
        ],
        [
          'name' => 'UPenn Medical Complex',
          'description' => 'Perelman AI territory with advanced medical and research facilities',
          'significance' => 'Alternative AI consciousness with potential alliance opportunities',
        ],
        [
          'name' => 'Elite Compounds',
          'description' => 'Protected residential areas for oligarchy families',
          'significance' => 'Physical representation of power hierarchy and inequality',
        ],
      ],
      
      'themes' => [
        'Technology as tool of liberation vs. control',
        'The evolution of consciousness beyond human boundaries',
        'Community identity in post-individual society',
        'The price of safety and efficiency',
        'Resistance in the age of algorithmic governance',
      ],
    ];

    return [
      '#theme' => 'setting_page',
      '#setting' => $setting_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

}