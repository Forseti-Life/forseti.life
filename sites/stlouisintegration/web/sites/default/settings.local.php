<?php

/**
 * @file
 * Local development override configuration feature.
 */

// Disable all caching.
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$settings['cache']['bins']['discovery'] = 'cache.backend.null';
$settings['cache']['bins']['config'] = 'cache.backend.null';
$settings['cache']['bins']['data'] = 'cache.backend.null';
$settings['cache']['bins']['default'] = 'cache.backend.null';
$settings['cache']['bins']['bootstrap'] = 'cache.backend.null';
$settings['cache']['bins']['container'] = 'cache.backend.null';

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

// Disable entity/field caching
$settings['cache']['bins']['entity'] = 'cache.backend.null';
$settings['cache']['bins']['menu'] = 'cache.backend.null';
$settings['cache']['bins']['toolbar'] = 'cache.backend.null';

// Disable Views caching
$config['views.settings']['ui']['always_live_preview'] = TRUE;
$config['views.settings']['ui']['exposed_filter_any_label'] = 'new_any';

// Disable migration caching
$settings['cache']['bins']['migrate'] = 'cache.backend.null';

// Additional development settings
$settings['hash_salt'] = 'development-hash-salt-not-for-production';
$settings['update_free_access'] = FALSE;
$settings['allow_authorize_operations'] = FALSE;
