<?php

/**
 * @file
 * Local development override configuration - DRUPAL 11 COMPATIBLE
 */

// Disable CSS and JS aggregation
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
$config['system.performance']['cache']['page']['max_age'] = 0;

// Twig debugging and cache disabling
$settings['twig_debug'] = TRUE;
$settings['twig_auto_reload'] = TRUE;
$settings['twig_cache'] = FALSE;

// Additional development settings
$settings['extension_discovery_scan_tests'] = FALSE;
$settings['rebuild_access'] = TRUE;
$settings['skip_permissions_hardening'] = TRUE;

// Development settings
$settings['hash_salt'] = 'development-hash-salt-not-for-production';
$settings['update_free_access'] = FALSE;
$settings['allow_authorize_operations'] = FALSE;

// Disable internal page cache
$settings['omit_vary_cookie'] = TRUE;