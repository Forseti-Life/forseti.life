<?php
// Temporary fix for missing twig_render_template function
if (!function_exists('twig_render_template')) {
  function twig_render_template($template, array $variables = []) {
    // This is a compatibility shim for the removed twig_render_template function
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/web');
    $twig = new \Twig\Environment($loader);
    return $twig->render($template, $variables);
  }
}
