<?php

/**
 * @file
 * Local development override configuration feature.
 */

// Disable caching by setting cache lifetimes to 0
$config['system.performance']['cache']['page']['max_age'] = 0;
$settings['cache_ttl_4xx'] = 0;

// Disable CSS and JS aggregation.
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Enable twig debugging and disable caching
$settings['twig_debug'] = TRUE;
$settings['twig_auto_reload'] = TRUE;
$settings['twig_cache'] = FALSE;

// Allow test modules and themes.
$settings['extension_discovery_scan_tests'] = FALSE;

// Enable access to rebuild.php.
$settings['rebuild_access'] = TRUE;

// Skip file system permissions hardening.
$settings['skip_permissions_hardening'] = TRUE;

// Disable entity/field caching (use memory backend for development)
$settings['cache']['bins']['entity'] = 'cache.backend.memory';
$settings['cache']['bins']['menu'] = 'cache.backend.memory';
$settings['cache']['bins']['toolbar'] = 'cache.backend.memory';

// Disable Views caching
$config['views.settings']['ui']['always_live_preview'] = TRUE;
$config['views.settings']['ui']['exposed_filter_any_label'] = 'new_any';

// Disable migration caching (use memory backend for development)
$settings['cache']['bins']['migrate'] = 'cache.backend.memory';

// Additional development settings
$settings['hash_salt'] = 'development-hash-salt-not-for-production';
$settings['update_free_access'] = FALSE;
$settings['allow_authorize_operations'] = FALSE;
