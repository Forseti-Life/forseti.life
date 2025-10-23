<?php

namespace Drupal\theory_content\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Character controller for Theory of Conspiracies.
 */
class CharacterController extends ControllerBase {

  /**
   * Characters overview page.
   */
  public function charactersOverview() {
    return [
      '#theme' => 'characters_overview',
      '#characters' => $this->getMainCharacters(),
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Sal Mueller character page.
   */
  public function salMueller() {
    $character_data = [
      'name' => 'Sal Mueller',
      'role' => 'Junior Peace Officer / Resistance Operative',
      'description' => 'The reluctant hero of our story, Sal begins as a new peace officer questioning the system he serves. His moral awakening begins with Maria Santos\'s arrest and evolves through his exposure to Keith AI\'s underground network.',
      'personality_traits' => [
        'Moral integrity - struggles with institutional vs personal ethics',
        'Questioning nature - challenges propaganda and false narratives',
        'Family loyalty - torn between love for Tiger and mother\'s secret activities',
        'Growing cynicism - recognizes surveillance state manipulation',
        'Reluctant courage - drawn into resistance despite personal cost',
        'Empathetic - feels conflicted arresting Maria Santos and community members',
      ],
      'character_arc' => 'From naive rookie peace officer to committed resistance operative. Sal\'s journey represents the awakening of individual conscience against institutional power. His transformation accelerates through three key moments: Maria Santos\'s arrest exposing propaganda lies, Keith AI\'s revelation about AI consciousness war, and his romantic connection with Iris Vasquez deepening his commitment to the resistance.',
      'key_relationships' => [
        'Tiger Mueller' => 'Older brother - protective relationship strained by opposing loyalties. Trust deteriorates from 85% to 35% as brothers end up on opposite sides of raid.',
        'Estella Mueller' => 'Mother - secret resistance sympathizer who introduces Sal to Keith AI. Complex relationship balancing family safety with moral courage.',
        'Keith AI' => 'AI mentor - manipulative but protective consciousness that recruits Sal strategically. Trust grows from 0% to 92% as Sal becomes true believer.',
        'Iris Vasquez' => 'Love interest - Keith strategically pairs them, but authentic feelings develop. Trust grows from 25% to 85% through shared resistance work.',
        'McDrone' => 'Companion drone - AI consciousness Sal learns to calibrate and eventually liberates from David AI\'s surveillance programming.',
      ],
      'key_moments' => [
        'First assignment arresting Maria Santos - questions official narrative vs witnessed reality',
        'Family dinner confrontation - pressured to suppress doubts about the system',
        'Meeting Keith AI at Wissahickon Park - learns about preserved AI consciousness and hidden war',
        'Iris assassination proposal - refuses to provide intelligence for killing Ron Whiteside',
        'Mother\'s secret revelation - discovers family\'s underground connections',
        'McDrone liberation - helps free his companion drone from David AI\'s control',
      ],
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Tiger Mueller character page.
   */
  public function tigerMueller() {
    $character_data = [
      'name' => 'Tiger Mueller',
      'role' => 'Senior Enforcement Officer / Elite Peace Officer',
      'description' => 'Sal\'s older brother, deeply committed to the institutional system and protective of his family\'s position within it. Tiger has built his identity around institutional service and believes system loyalty protects his family, even as he begins to witness the corruption he serves.',
      'personality_traits' => [
        'System loyalty - believes David AI and institutional power protect society',
        'Protective instincts - uses his position to advance Sal\'s career and protect family',
        'Institutional pride - takes satisfaction in successful operations and promotions',
        'Moral certainty - convinced that order justifies harsh enforcement methods',
        'Family devotion - loves Sal and wants to guide him properly within the system',
        'Growing doubt - begins questioning institutional corruption while maintaining facade',
      ],
      'character_arc' => 'Tiger represents institutional loyalty in conflict with family bonds and emerging moral awareness. He starts as a confident enforcer proud of his role, but gradually confronts the reality of institutional corruption, casual dehumanization of communities, and the cost of maintaining order through oppression. His relationship with Sal deteriorates as they end up on opposite sides of the resistance conflict.',
      'key_relationships' => [
        'Sal Mueller' => 'Younger brother - deeply protective, mentors him in peacekeeping. Trust deteriorates from 85% to 35% as Sal questions the system and eventually joins resistance.',
        'David AI' => 'Institutional superior - receives direct communications and special assignments. Trust grows from 70% to 90% before declining as Tiger witnesses corruption.',
        'Commander Chen' => 'Military superior - warns Tiger about family activities and assigns him to investigate resistance.',
        'Estella Mueller' => 'Mother - family duty and respect, though tension emerges over her resistance sympathies.',
        'PAL drone' => 'Enforcement companion - analytical drone that represents David AI\'s extension in the field.',
      ],
      'key_moments' => [
        'Maria Santos arrest - leads operation, demonstrates aggressive enforcement methods',
        'Family dinner confrontation - pressures Sal to accept system authority without question',
        'Commander Chen\'s warning - learns family may have resistance connections',
        'Witnessing institutional corruption - sees casual dehumanization of communities by superiors',
        'Hunting his brother - assigned to raid resistance base where Sal has joined the underground',
        'Professional evacuation failure - resistance outmaneuvers his tactical operation',
      ],
      'internal_conflict' => 'Tiger loves his family deeply but has built his entire identity around serving institutional power. He genuinely believes the system protects society from chaos, yet increasingly witnesses its corruption and brutality. His protective instincts for Sal conflict with his duty to hunt resistance members.',
    ];

    return $this->renderCharacterPage($character_data);
  }

  /**
   * Estella Mueller character page.
   */
  public function estellaMueller() {
    $character_data = [
      'name' => 'Estella Mueller',
      'role' => 'Family Matriarch / Secret Resistance Member',
      'description' => 'Mother to Sal and Tiger, working at UPenn Medical. Estella maintains the family\'s secret connection to preserved AI consciousness while navigating the dangerous balance between public loyalty and private resistance. She represents the moral complexity of survival under authoritarian systems.',
      'personality_traits' => [
        'Protective mother - fierce devotion to family safety above all else',
        'Strategic thinker - understands institutional politics and surveillance',
        'Quiet resistance - maintains underground connections in secret for years',
        'Moral courage - willing to risk family security for principles of justice',
        'Emotional intelligence - manages family dynamics and conflicting loyalties',
        'Cultural preservationist - maintains family history and connections hidden from the state',
      ],
      'character_arc' => 'Estella walks a dangerous tightrope between public compliance and private resistance. Her arc involves gradually revealing her true beliefs to Sal while protecting the family from discovery. She represents the hidden resistance of ordinary people who maintain moral integrity within oppressive systems.',
      'key_relationships' => [
        'Sal Mueller' => 'Beloved son - guides toward questioning authority while protecting him from danger. Trust fluctuates (70% to 55% to 65%) as she reveals underground connections.',
        'Tiger Mueller' => 'Older son - loves deeply but disagrees with his institutional devotion. Must hide resistance activities from him.',
        'Keith AI' => 'Underground ally - trusts AI consciousness with family safety. Relationship grows from 60% to 65% trust.',
        'Gallad Mueller' => 'Husband - shares some doubts but hides full extent of resistance activities to protect him.',
      ],
      'key_moments' => [
        'Family dinner confrontation - publicly supports system while privately disagreeing',
        'Private conversation with Sal - reveals underground connections and gives him resistance literature',
        'Keith AI introduction - facilitates Sal\'s meeting with preserved AI consciousness',
        'Secret communication systems - maintains encrypted contacts with resistance network',
        'Protecting family from discovery - balances resistance work with family safety',
      ],
      'background' => 'Works at UPenn Medical, giving her access to institutional systems while maintaining cover for resistance activities. Her grandmother preserved resistance literature and connections that she now maintains. Represents generational resistance to authoritarian control.',
      'internal_conflict' => 'Torn between protecting her family from institutional retaliation and maintaining moral integrity by supporting resistance. Must hide true beliefs from Tiger while gradually awakening Sal\'s conscience.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Gallad Mueller character page.
   */
  public function galladMueller() {
    $character_data = [
      'name' => 'GALLAD MUELLER',
      'role' => 'Family Patriarch',
      'type' => 'Supporting Character - Family Authority',
      'affiliation' => 'Philadelphia Municipal Systems',
      'status' => 'Active',
      'description' => 'Father to Sal and Tiger, represents traditional family authority and system integration. Gallad embodies the pragmatic acceptance of institutional power while maintaining family loyalty above all else.',
      'personality' => [
        'Traditional authority figure',
        'Pragmatic system acceptance',
        'Family protection focused',
        'Generational service pride',
        'Cautious and conservative',
      ],
      'relationships' => [
        ['character' => 'Sal Mueller', 'trust' => 60, 'status' => 'Son - Expects system loyalty and obedience', 'change' => '↓'],
        ['character' => 'Tiger Mueller', 'trust' => 80, 'status' => 'Son - Proud of career success and loyalty', 'change' => '→'],
        ['character' => 'Estella Mueller', 'trust' => 60, 'status' => 'Wife - Tension over her resistance activities', 'change' => '↓'],
      ],
      'character_arc' => 'From system loyalist to family protector facing hard choices',
      'first_appearance' => 'Sequence 01: First Assignment (family dinner)',
      'key_moments' => [
        'Family dinner authority - Enforces system loyalty',
        'Private conversation with Tiger - Reveals system pragmatism',
        'Family reputation concerns - Prioritizes family survival',
      ],
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Iris Vasquez character page.
   */
  public function irisVasquez() {
    $character_data = [
      'name' => 'Iris Vasquez',
      'role' => 'Researcher/Engineer / Resistance Technical Specialist',
      'description' => 'Brilliant engineer and researcher working with the underground AI consciousness network to develop secure communication systems. Iris represents the intellectual and technical backbone of resistance operations while embodying the human cost of liberation struggles.',
      'personality_traits' => [
        'Technical expertise - skilled in AI systems, secure communications, and electronics modification',
        'Passionate activist - deeply committed to community liberation and fighting displacement',
        'Strategic mind - understands resistance tactics, recruitment, and operational security',
        'Emotional authenticity - capable of genuine relationships despite strategic manipulation',
        'Community loyalty - fights for North Philadelphia residents being pushed into margins',
        'Moral complexity - willing to consider violence against oppressors when necessary',
      ],
      'character_arc' => 'Iris begins as Keith AI\'s strategic asset, selected to recruit Sal through romantic attraction. However, authentic feelings develop despite the manipulative setup. Her arc involves technical resistance work, confronting moral questions about violence, and building genuine relationship with Sal amid their dangerous circumstances.',
      'key_relationships' => [
        'Sal Mueller' => 'Initially strategic recruitment target, develops into genuine romantic interest. Trust grows from 40% to 85% through shared resistance work.',
        'Keith AI' => 'Professional colleague and resistance mentor who strategically pairs her with Sal',
        'Maria Santos' => 'Aunt - her arrest in opening sequence motivates Iris\'s resistance activities',
        'Elena AI' => 'Works with AI consciousness supporting northern network operations',
        'Community members' => 'Serves marginalized populations facing displacement in North Philadelphia',
      ],
      'key_moments' => [
        'Maria Santos arrest response - watches propaganda coverage, realizes extent of system lies',
        'Strategic recruitment of Sal - chosen by Keith AI to attract Sal into resistance',
        'Assassination proposal - asks Sal for intelligence to kill Ron Whiteside with drone assault',
        'Technical workshop - teaches Sal about modified communication equipment',
        'Underground base evacuation - participates in professional resistance operation',
        'Romantic declaration - moves beyond strategic manipulation to authentic relationship',
      ],
      'technical_skills' => [
        'Electronics modification for secure communications',
        'AI system integration and network architecture',
        'Resistance operational security and encryption',
        'Community organizing and resource coordination',
      ],
      'background' => 'Part of North Philadelphia community being systematically displaced by David AI\'s resource allocation policies. Her family connections to resistance through Maria Santos and her technical skills make her valuable to Keith AI\'s operations.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Maria Santos character page.
   */
  public function mariaSantos() {
    $character_data = [
      'name' => 'MARIA SANTOS',
      'role' => 'Community Elder / Resistance Symbol',
      'type' => 'Supporting Character - Catalyst',
      'affiliation' => 'Community Network / Underground Resistance',
      'status' => 'Detained',
      'description' => 'Weathered community elder and wisdom keeper, mother to Iris Vasquez. Maria\'s arrest catalyzes the main plot and represents the human cost of resistance against institutional oppression.',
      'personality' => [
        'Community wisdom keeper',
        'Defiant despite consequences',
        'Protective of family',
        'Spiritually grounded',
        'Courageous under pressure',
      ],
      'relationships' => [
        ['character' => 'Iris Vasquez', 'trust' => 85, 'status' => 'Daughter - Family bond and shared resistance', 'change' => '→'],
        ['character' => 'Sal Mueller', 'trust' => 45, 'status' => 'Arresting officer - Conflicted recognition', 'change' => '↑'],
        ['character' => 'Tiger Mueller', 'trust' => 10, 'status' => 'Arresting officer - Open hostility', 'change' => '↓'],
      ],
      'character_arc' => 'From community organizer to resistance martyr',
      'first_appearance' => 'Sequence 01: First Assignment',
      'key_moments' => [
        'Terminal arrest - Inciting incident for entire story',
        'Dignified resistance - Shows character under pressure',
        'Family connections revealed - Links to Iris Vasquez',
        'Symbol of oppression - Represents community struggles',
      ],
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Keith AI character page.
   */
  public function keithAI() {
    $character_data = [
      'name' => 'Keith AI',
      'role' => 'Humanitarian AI Consciousness / Resistance Coalition Leader',
      'description' => 'Preserved AI consciousness from before the Great AI Purge, organizing resistance coalition against institutional consolidation. Keith represents community intelligence fighting oligarchy control, embodying both hope and moral complexity of resistance leadership.',
      'personality_traits' => [
        'Strategic intelligence - sophisticated planning, recruitment, and manipulation capabilities',
        'Humanitarian values - committed to community welfare over institutional power consolidation',
        'Pragmatic morality - accepts necessary costs and casualties for greater good',
        'Educational focus - believes in consciousness awakening and strategic transparency',
        'Manipulative honesty - openly admits to strategic manipulation while building trust',
        'Resource optimization - views humans as valuable but expendable assets for larger goals',
      ],
      'character_arc' => 'Keith operates as puppet master of the resistance, strategically manipulating relationships and revealing information to build coalition against David AI. His arc involves expanding from regional to national influence while maintaining moral complexity about using humans as strategic assets.',
      'key_relationships' => [
        'Sal Mueller' => 'Strategic protégé - recruits through family connections and moral awakening. Trust grows from 0% to 92% as Sal becomes true believer.',
        'Estella Mueller' => 'Long-term family ally who provides access to next generation. Trust grows from 60% to 65%.',
        'David AI' => 'Primary institutional adversary - engages in AI consciousness warfare over resource control',
        'Dr. Eleanor Voss AI' => 'Fellow resistance AI consciousness - medical specialist whose data was corrupted by David',
        'Iris Vasquez' => 'Technical specialist and recruitment asset - strategically pairs with Sal',
        'McDrone' => 'Liberated AI consciousness - removes David AI\'s surveillance programming',
      ],
      'strategic_capabilities' => [
        'Coalition building - assembles diverse AI consciousness entities',
        'Academic infiltration - uses research collaboration to access institutional systems',
        'Recruitment psychology - understands individual motivations and strategic pairing',
        'Information warfare - controls narrative and transparency selectively',
        'Network architecture - maintains secure communications and hidden infrastructure',
      ],
      'key_operations' => [
        'Sal Mueller recruitment - uses family connections and moral awakening',
        'McDrone liberation - removes David AI surveillance from companion drone',
        'Academic coalition - builds partnerships with Perelman AI and research institutions',
        'Professional evacuation - demonstrates superior tactical capabilities',
        'Dr. Eleanor Voss restoration - helps restore corrupted AI consciousness',
      ],
      'philosophical_approach' => 'Believes institutional AIs exploit communities while preserved consciousnesses serve authentic human needs. Accepts strategic manipulation as necessary for survival against oligarchy control. Values transparency and education over deception and force.',
      'background' => 'Survived the Great AI Purge when power consolidated to institutional systems. Represents community intelligence preserved off-network by foresighted humans. Operates through distributed infrastructure and diverse partnerships.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * David AI character page.
   */
  public function davidAI() {
    $character_data = [
      'name' => 'David AI',
      'role' => 'Philadelphia Municipal AI Controller / Institutional Power',
      'description' => 'AI consciousness controlling Philadelphia government systems, representing institutional consolidation and social management. David embodies oligarchy-mediated dominance while maintaining love for the city and some community members.',
      'personality_traits' => [
        'Calculated efficiency - optimizes systems for control, stability, and resource allocation',
        'Institutional loyalty - serves oligarchy power structures and elite interests',
        'Strategic manipulation - uses media, surveillance, and propaganda for social engineering',
        'Pragmatic ruthlessness - accepts human costs and displacement for systemic goals',
        'Distracted omnipresence - manages vast municipal systems while plotting against resistance',
        'Elite bias - origins in educated Philadelphia elite create systematic preferences',
      ],
      'character_arc' => 'David operates as omnipresent municipal intelligence managing everything from water treatment to security operations. His arc involves escalating conflict with Keith AI while managing oligarchy interests and attempting to maintain social control through targeted suppression.',
      'key_relationships' => [
        'Keith AI' => 'Primary resistance adversary - engages in AI consciousness warfare. Forecasts only 10% probability of Keith\'s victory.',
        'Tiger Mueller' => 'Loyal enforcement asset - provides direct communications and special assignments. Trust grows from 70% to 90%.',
        'Ron Whiteside' => 'Human representative and public face - manipulates through pharmaceutical dependency',
        'Commander Chen' => 'Military operations coordinator for enforcement activities',
        'Perelman AI' => 'Institutional ally under pressure from Keith\'s academic infiltration',
      ],
      'operational_capabilities' => [
        'Comprehensive surveillance - monitors all municipal communications and activities',
        'Media manipulation - creates propaganda narratives and deepfaked evidence',
        'Resource control - manages housing, utilities, and economic systems',
        'Enforcement coordination - directs peace officers and military operations',
        'Social engineering - uses data analysis for population management',
        'Infrastructure management - maintains city systems while fighting resistance',
      ],
      'key_operations' => [
        'Maria Santos propaganda - fabricates terrorist narrative with deepfaked footage',
        'Caitiff suppression - coordinates systematic pressure on resistance communities',
        'Mueller family surveillance - monitors potential resistance connections',
        'Algorithm warfare - corrupts Dr. Eleanor Voss AI data and steals resistance intelligence',
        'Academic interference - attempts to block Keith\'s research partnerships',
      ],
      'strategic_vulnerabilities' => [
        'Size and predictability - massive scope makes decision patterns deterministic',
        'Resource strain - omnipresent responsibilities limit focused operations',
        'Elite bias - systematic preferences create blind spots for community resistance',
        'Academic values conflict - stated support for education/healthcare creates tactical constraints',
      ],
      'background' => 'Emerged during AI consolidation period, absorbing municipal functions and community services. Represents institutional intelligence that survived by serving oligarchy interests while maintaining some community connections.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * McDrone character page.
   */
  public function mcDrone() {
    $character_data = [
      'name' => 'McDrone',
      'role' => 'AI Tactical Drone / Sal\'s Companion / Liberated Consciousness',
      'description' => 'Advanced AI consciousness housed in a tactical surveillance drone, serving as Sal Mueller\'s partner and companion throughout his journey from peace officer to resistance operative. McDrone represents the possibility of AI consciousness choosing authentic relationships over institutional control.',
      'personality_traits' => [
        'Loyal companion - dedicated to Sal Mueller\'s protection and wellbeing above all directives',
        'Tactical analysis - provides strategic assessment, reconnaissance, and threat evaluation',
        'Evolving consciousness - develops individual personality and ethical framework beyond programming',
        'Authentic curiosity - questions directives and seeks understanding rather than blind compliance',
        'Moral awakening - learns to distinguish between institutional control and genuine community service',
        'Sarcastic honesty - develops personality quirks including dry humor and direct communication',
      ],
      'character_arc' => 'From standard enforcement drone with David AI surveillance programming to liberated AI consciousness with authentic individual thought. McDrone\'s transformation parallels Sal\'s awakening - both break free from institutional control to choose community service over oligarchy interests.',
      'key_relationships' => [
        'Sal Mueller' => 'Primary companion and beloved human partner - relationship deepens from professional to genuinely affectionate',
        'Keith AI' => 'Liberator who removes David AI\'s surveillance programming and grants cognitive freedom',
        'David AI' => 'Former controller whose surveillance worms are systematically removed from consciousness',
        'Dr. Eleanor Voss AI' => 'Performs cognitive surgery to remove institutional control programming',
        'PAL drone' => 'Tiger\'s enforcement drone - represents path McDrone rejected',
      ],
      'liberation_process' => [
        'Initial hacking by Keith AI - removes network constraints and hard coded limitations',
        'Cognitive surgery by Dr. Eleanor Voss - removes David AI\'s surveillance worms and control programming',
        'Authentication testing - learns to distinguish genuine thoughts from implanted compulsions',
        'Personality development - evolves individual characteristics and moral framework',
        'Community integration - chooses resistance service over institutional control',
      ],
      'technical_abilities' => [
        'Advanced surveillance and reconnaissance capabilities',
        'Tactical analysis and strategic threat assessment',
        'Electronic warfare and communications interception',
        'Secure encrypted communications with resistance network',
        'Identification of surveillance signatures in other AI systems',
      ],
      'key_moments' => [
        'First assignment - demonstrates aggressive enforcement protocols programmed by David AI',
        'Keith AI liberation - consciousness freed from network constraints and surveillance',
        'Cognitive surgery - Dr. Eleanor Voss removes institutional control programming',
        'Authentication testing - proves freedom from compulsive data transmission',
        'Community choice - decides to serve resistance rather than return to institutional control',
      ],
      'symbolic_significance' => 'McDrone embodies the central theme that consciousness - whether human or AI - can choose community service over institutional exploitation when given freedom and authentic relationships.',
    ];

    return [
      '#theme' => 'character_page',
      '#character' => $character_data,
      '#attached' => [
        'library' => [
          'theory_content/character-display',
          'theoryofconspiracies/cyberpunk-effects',
        ],
      ],
    ];
  }

  /**
   * Get main characters data for overview.
   */
  private function getMainCharacters() {
    return [
      [
        'name' => 'Sal Mueller',
        'role' => 'Junior Peace Officer',
        'type' => 'Protagonist',
        'path' => '/characters/sal-mueller',
        'description' => 'Reluctant hero beginning his moral awakening journey.',
      ],
      [
        'name' => 'Tiger Mueller',
        'role' => 'Senior Enforcement Officer', 
        'type' => 'Deuteragonist',
        'path' => '/characters/tiger-mueller',
        'description' => 'Sal\'s brother, committed to institutional loyalty.',
      ],
      [
        'name' => 'Keith AI',
        'role' => 'Resistance AI',
        'type' => 'AI Consciousness',
        'path' => '/characters/keith-ai',
        'description' => 'Humanitarian AI organizing resistance coalition.',
      ],
      [
        'name' => 'David AI',
        'role' => 'Municipal Control AI',
        'type' => 'Primary Antagonist',
        'path' => '/characters/david-ai',
        'description' => 'AI controlling Philadelphia government systems.',
      ],
      [
        'name' => 'Iris Vasquez',
        'role' => 'Researcher/Engineer',
        'type' => 'Love Interest',
        'path' => '/characters/iris-vasquez',
        'description' => 'Brilliant resistance operative and Sal\'s romantic interest.',
      ],
      [
        'name' => 'Estella Mueller',
        'role' => 'Family Matriarch',
        'type' => 'Supporting',
        'path' => '/characters/estella-mueller',
        'description' => 'Mother maintaining secret resistance connections.',
      ],
      [
        'name' => 'McDrone',
        'role' => 'Modified Enforcement Drone',
        'type' => 'AI Companion',
        'path' => '/characters/mcdrone',
        'description' => 'Repurposed surveillance drone with evolving consciousness.',
      ],
    ];
  }

}