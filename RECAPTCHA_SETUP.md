# Google reCAPTCHA Setup Instructions

## Step 1: Get Google reCAPTCHA Keys

1. **Go to Google reCAPTCHA Admin Console**
   - Visit: https://www.google.com/recaptcha/admin/create
   - Sign in with your Google account

2. **Create a New reCAPTCHA Site**
   - **Label**: St. Louis Integration Website
   - **reCAPTCHA type**: Choose "reCAPTCHA v2" -> "I'm not a robot" Checkbox
   - **Domains**: Add your domains:
     - stlouisintegration.com
     - www.stlouisintegration.com
     - localhost (for development)
   - Accept the reCAPTCHA Terms of Service
   - Click **Submit**

3. **Copy Your Keys**
   - **Site Key** (public key) - goes in Drupal config
   - **Secret Key** (private key) - goes in Drupal config
   - Save these keys securely

## Step 2: Configure in Drupal (After you get the keys)

Run these commands with your actual keys:

```bash
# Navigate to Drupal root
cd /workspaces/stlouisintegration.com/drupal

# Set the reCAPTCHA site key (replace YOUR_SITE_KEY with actual key)
./vendor/bin/drush config:set recaptcha.settings site_key "YOUR_SITE_KEY"

# Set the reCAPTCHA secret key (replace YOUR_SECRET_KEY with actual key)
./vendor/bin/drush config:set recaptcha.settings secret_key "YOUR_SECRET_KEY"

# Enable reCAPTCHA on specific forms
./vendor/bin/drush config:set captcha.captcha_point.contact_message_feedback_form captchachallengeType recaptcha/reCAPTCHA
./vendor/bin/drush config:set captcha.captcha_point.user_login_form captchachallengeType recaptcha/reCAPTCHA
./vendor/bin/drush config:set captcha.captcha_point.user_register_form captchachallengeType recaptcha/reCAPTCHA

# Clear caches
./vendor/bin/drush cache:rebuild
```

## Alternative: Web UI Configuration

After getting your keys, you can also configure via the web interface:

1. **reCAPTCHA Settings**: `/admin/config/people/captcha/recaptcha`
   - Enter Site Key and Secret Key

2. **CAPTCHA Points**: `/admin/config/people/captcha`
   - Select which forms should use reCAPTCHA

## Forms That Will Be Protected

- Contact forms
- User login form
- User registration form
- Any custom forms you specify
