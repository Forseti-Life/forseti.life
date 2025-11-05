<?php

/**
 * @file
 * Local development override configuration - DISABLE AGGREGATION
 */

// Disable CSS and JS aggregation
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
$config['system.performance']['cache']['page']['max_age'] = 0;

// Twig debugging and cache disabling
$settings['twig_debug'] = TRUE;
$settings['twig_auto_reload'] = TRUE;
$settings['twig_cache'] = FALSE;
