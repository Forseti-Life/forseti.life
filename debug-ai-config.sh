#!/bin/bash
echo "=== Debugging AI Conversation Configuration ==="
echo ""

cd /var/www/html/theoryofconspiracies

echo "Current AI Conversation settings:"
sudo -u www-data ./vendor/bin/drush config:get ai_conversation.settings

echo ""
echo "Testing environment detection logic:"
sudo -u www-data ./vendor/bin/drush php:eval "
\$service = \Drupal::service('ai_conversation.api');
echo 'Service loaded: ' . get_class(\$service) . PHP_EOL;

// Test environment detection
\$reflection = new ReflectionClass(\$service);
\$method = \$reflection->getMethod('isDevelopmentEnvironment');
\$method->setAccessible(true);
\$isDev = \$method->invoke(\$service);
echo 'isDevelopmentEnvironment(): ' . (\$isDev ? 'TRUE' : 'FALSE') . PHP_EOL;

// Test credential check
echo 'Testing credential logic...' . PHP_EOL;
\$config = \Drupal::config('ai_conversation.settings');
\$aws_access_key = \$config->get('aws_access_key_id') ?: getenv('AWS_ACCESS_KEY_ID');
\$aws_secret_key = \$config->get('aws_secret_access_key') ?: getenv('AWS_SECRET_ACCESS_KEY');
echo 'AWS Access Key from config: ' . (\$config->get('aws_access_key_id') ? 'SET' : 'EMPTY') . PHP_EOL;
echo 'AWS Secret Key from config: ' . (\$config->get('aws_secret_access_key') ? 'SET' : 'EMPTY') . PHP_EOL;
echo 'AWS Access Key from env: ' . (getenv('AWS_ACCESS_KEY_ID') ? 'SET' : 'EMPTY') . PHP_EOL;
echo 'AWS Secret Key from env: ' . (getenv('AWS_SECRET_ACCESS_KEY') ? 'SET' : 'EMPTY') . PHP_EOL;
\$has_credentials = (!empty(\$aws_access_key) && !empty(\$aws_secret_key));
echo 'Final has_credentials check: ' . (\$has_credentials ? 'TRUE' : 'FALSE') . PHP_EOL;
"

echo ""
echo "=== Debug complete ==="
