<?php

// IMMEDIATE DEBUG - before anything else
error_log("INDEX.PHP HIT: " . ($_SERVER['REQUEST_URI'] ?? 'NO_URI'));
file_put_contents('/tmp/forseti_debug.log', date('Y-m-d H:i:s') . " - INDEX.PHP LOADED - URI: " . ($_SERVER['REQUEST_URI'] ?? 'NO_URI') . "\n", FILE_APPEND);

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$autoloader = require_once 'autoload.php';

$kernel = new DrupalKernel('prod', $autoloader);

$request = Request::createFromGlobals();
error_log("FORSETI DEBUG - Request Path: " . $request->getPathInfo() . " | Base: " . $request->getBasePath());

$response = $kernel->handle($request);
error_log("FORSETI DEBUG - Response Status: " . $response->getStatusCode());

// Check front page config
if ($request->getPathInfo() === '/') {
  $front_page = \Drupal::config('system.site')->get('page.front');
  error_log("FORSETI DEBUG - Front page config: " . $front_page);
}

$response->send();

$kernel->terminate($request, $response);
