#!/bin/bash
# reCAPTCHA Configuration Script for St. Louis Integration
# Run this script after obtaining your Google reCAPTCHA keys

# Check if keys are provided as arguments
if [ $# -ne 2 ]; then
    echo "Usage: $0 <SITE_KEY> <SECRET_KEY>"
    echo ""
    echo "Example:"
    echo "$0 6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI 6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe"
    echo ""
    echo "Get your keys from: https://www.google.com/recaptcha/admin/create"
    exit 1
fi

SITE_KEY=$1
SECRET_KEY=$2

echo "Configuring reCAPTCHA for St. Louis Integration..."

cd /workspaces/stlouisintegration.com/drupal

# Set the reCAPTCHA keys
echo "Setting reCAPTCHA site key..."
./vendor/bin/drush config:set recaptcha.settings site_key "$SITE_KEY" -y

echo "Setting reCAPTCHA secret key..."
./vendor/bin/drush config:set recaptcha.settings secret_key "$SECRET_KEY" -y

# Configure additional reCAPTCHA settings
echo "Configuring reCAPTCHA widget settings..."
./vendor/bin/drush config:set recaptcha.settings widget.theme dark -y
./vendor/bin/drush config:set recaptcha.settings widget.type image -y
./vendor/bin/drush config:set recaptcha.settings verify_hostname false -y

# Clear caches
echo "Clearing caches..."
./vendor/bin/drush cache:rebuild

echo ""
echo "✅ reCAPTCHA configuration complete!"
echo ""
echo "Protected forms:"
echo "- User login form"
echo "- User registration form" 
echo "- Password reset form"
echo "- Contact forms"
echo "- AI conversation forms"
echo ""
echo "You can test reCAPTCHA at:"
echo "- https://www.stlouisintegration.com/user/login"
echo "- https://www.stlouisintegration.com/contact"
echo "- https://www.stlouisintegration.com/clauddemo"
echo ""
echo "To manage CAPTCHA settings, visit:"
echo "- https://www.stlouisintegration.com/admin/config/people/captcha"
echo "- https://www.stlouisintegration.com/admin/config/people/captcha/recaptcha"