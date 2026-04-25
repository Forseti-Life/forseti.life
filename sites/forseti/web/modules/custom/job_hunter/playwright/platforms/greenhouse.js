/**
 * Greenhouse ATS — Playwright handler
 *
 * Handles: boards.greenhouse.io apply forms
 * Track: A (no login required)
 * Priority: 1 (first Phase 2 implementation)
 *
 * Greenhouse uses a predictable single-page form with stable CSS IDs.
 * Form URL pattern: https://boards.greenhouse.io/{company}/jobs/{job_id}
 */

'use strict';

const { launchBrowser, humanType, humanDelay, takeScreenshot, extractConfirmationNumber } = require('../utils/stealth');

/**
 * Apply to a Greenhouse job.
 *
 * @param {object} payload  Full payload from PHP (see PLAYWRIGHT_BRIDGE_SPEC.md)
 * @param {function} buildResult  Result factory from apply.js
 * @returns {Promise<object>}  Result object
 */
async function apply(payload, buildResult) {
  const { apply_url, personal_info, resume_pdf_path, cover_letter, screenshot_dir, application_id, options = {} } = payload;

  if (!resume_pdf_path || !require('fs').existsSync(resume_pdf_path)) {
    return buildResult({
      outcome: 'manual_required',
      reason:  'no_resume_pdf',
      error:   'Resume PDF not found at: ' + resume_pdf_path,
      instructions: 'Upload your tailored resume PDF and try again.',
    });
  }

  const { browser, page } = await launchBrowser({ ...options, headless: options.headless !== false });
  const fields_filled   = [];
  const fields_skipped  = [];
  let screenshot_pre    = null;
  let screenshot_post   = null;

  try {
    // Navigate to the apply page
    await page.goto(apply_url, { waitUntil: 'networkidle', timeout: 30000 });
    await humanDelay(800, 1500);

    // Some Greenhouse boards (job-boards.greenhouse.io) show a landing view
    // with an "Apply" CTA and only render the form after clicking it.
    const firstNameField = '#first_name, #app_first_name, #job_application_first_name, input[name="first_name"], input[name*="first_name"]';
    let hasVisibleForm = await page.$(firstNameField);
    if (!hasVisibleForm) {
      const openApplySelectors = [
        'button:has-text("Apply")',
        'button[aria-label="Apply"]',
        'a:has-text("Apply")',
        '[data-testid*="apply" i]',
      ];

      for (const selector of openApplySelectors) {
        const cta = await page.$(selector);
        if (cta) {
          await cta.click().catch(() => {});
          await humanDelay(600, 1200);
          hasVisibleForm = await page.$(firstNameField);
          if (hasVisibleForm) {
            break;
          }
        }
      }
    }

    // ── Personal Information ────────────────────────────────────────────────

    // First name
    if (await fillFirstMatch(page, ['#first_name', '#app_first_name', '#job_application_first_name', 'input[name="first_name"]', 'input[name*="first_name"]'], personal_info.first_name)) fields_filled.push('first_name');
    else fields_skipped.push('first_name');

    // Last name
    if (await fillFirstMatch(page, ['#last_name', '#app_last_name', '#job_application_last_name', 'input[name="last_name"]', 'input[name*="last_name"]'], personal_info.last_name)) fields_filled.push('last_name');
    else fields_skipped.push('last_name');

    // Email
    if (await fillFirstMatch(page, ['#email', '#app_email', '#job_application_email', 'input[name="email"]', 'input[name*="email"]'], personal_info.email)) fields_filled.push('email');
    else fields_skipped.push('email');

    // Phone
    if (await fillFirstMatch(page, ['#phone', '#app_phone', '#job_application_phone', 'input[name="phone"]', 'input[name*="phone"]'], personal_info.phone)) fields_filled.push('phone');
    else fields_skipped.push('phone');

    // LinkedIn
    const linkedinSelectors = [
      '#job_application_linkedin_profile_url',
      '#linkedin',
      'input[name*="linkedin"]',
      'input[placeholder*="LinkedIn"]',
    ];
    if (personal_info.linkedin_url && await fillFirstMatch(page, linkedinSelectors, personal_info.linkedin_url)) {
      fields_filled.push('linkedin_url');
    } else {
      fields_skipped.push('linkedin_url');
    }

    // Location (city, state)
    const locationValue = [personal_info.city, personal_info.state].filter(Boolean).join(', ');
    const locationSelectors = ['#location', '#app_location', '#job_application_location', '#candidate-location', 'input[name="location"]', 'input[name*="location"]'];
    if (locationValue && await fillFirstMatch(page, locationSelectors, locationValue)) {
      fields_filled.push('location');
    } else if (locationValue && await fillReactSelect(page, '#candidate-location', locationValue)) {
      fields_filled.push('location');
    } else {
      fields_skipped.push('location');
    }

    // Website / portfolio (optional)
    const websiteSelectors = [
      '#website',
      '#job_application_website',
      'input[name*="website"]',
      'input[name*="portfolio"]',
      'input[placeholder*="website" i]',
      'input[placeholder*="portfolio" i]',
    ];
    if (personal_info.website_url && await fillFirstMatch(page, websiteSelectors, personal_info.website_url)) {
      fields_filled.push('website_url');
    } else {
      fields_skipped.push('website_url');
    }

    await humanDelay(400, 800);

    // ── Resume Upload ───────────────────────────────────────────────────────
    const resumeInput = await page.$('#resume, input[type=file][name*=resume], input[type=file][accept*=pdf]');
    if (resumeInput) {
      await resumeInput.setInputFiles(resume_pdf_path);
      fields_filled.push('resume');
      await humanDelay(1000, 2000); // Wait for upload processing
    } else {
      fields_skipped.push('resume');
    }

    // ── Cover Letter (optional) ─────────────────────────────────────────────
    if (cover_letter) {
      const clInput = await page.$('#cover_letter, textarea[name*=cover]');
      if (clInput) {
        await clInput.fill(cover_letter);
        fields_filled.push('cover_letter');
      } else {
        fields_skipped.push('cover_letter');
      }
    }

    // ── Demographics / Screening ────────────────────────────────────────────
    // Select "Prefer not to say" for any demographic radio/select groups we find
    await selectPreferNotToSay(page);

    await humanDelay(500, 1000);

    // ── Pre-submit screenshot ───────────────────────────────────────────────
    screenshot_pre = await takeScreenshot(page, screenshot_dir, application_id, 'pre');

    // ── Dry run check ───────────────────────────────────────────────────────
    if (options.dry_run) {
      return buildResult({
        success:       true,
        outcome:       'submitted',
        fields_filled,
        fields_skipped,
        screenshot_pre,
        error:         null,
        reason:        'dry_run',
        instructions:  'Dry run — form was NOT submitted.',
        apply_url,
      });
    }

    // ── Submit ──────────────────────────────────────────────────────────────
    const captchaSelectors = '[data-callback], .g-recaptcha, iframe[src*="recaptcha"], iframe[src*="hcaptcha"]';
    let captchaDetected = await page.$(captchaSelectors);
    if (captchaDetected) {
      if (options.allow_manual_captcha === true) {
        const captchaWaitMs = Number(options.captcha_wait_ms || 300000);
        const start = Date.now();
        while ((Date.now() - start) < captchaWaitMs) {
          await humanDelay(800, 1200);
          captchaDetected = await page.$(captchaSelectors);
          if (!captchaDetected) {
            break;
          }
        }
        if (captchaDetected) {
          return buildResult({
            outcome: 'manual_required',
            reason:  'captcha_timeout',
            error:   'CAPTCHA was not completed within the allowed wait time.',
            instructions: 'Complete CAPTCHA and retry, or apply manually.',
            apply_url,
          });
        }
      } else {
        return buildResult({
          outcome: 'manual_required',
          reason:  'captcha_detected',
          error:   'CAPTCHA detected on Greenhouse apply page.',
          instructions: 'This job requires completing a CAPTCHA. Please apply manually.',
          apply_url,
        });
      }
    }

    const submitBtn = await page.$('input[type=submit], button[type=submit], button[data-submit], .submit_button');
    if (!submitBtn) {
      screenshot_post = await takeScreenshot(page, screenshot_dir, application_id, 'post');
      return buildResult({
        outcome:        'manual_required',
        reason:         'selector_not_found',
        error:          'Submit button not found.',
        fields_filled,
        fields_skipped,
        screenshot_pre,
        screenshot_post,
        apply_url,
        instructions:   'Form was filled but submit button was not found. Please submit manually.',
        field_map:      buildFieldMap(payload),
      });
    }

    await Promise.all([
      page.waitForNavigation({ timeout: 20000, waitUntil: 'networkidle' }).catch(() => {}),
      submitBtn.click(),
    ]);

    await humanDelay(1500, 2500);

    // ── Confirmation detection ──────────────────────────────────────────────
    const pageText  = await page.textContent('body').catch(() => '');
    const pageUrl   = page.url();
    const confirmed = pageUrl.includes('/confirmation') ||
      /thank you|application received|successfully submitted|we.ll be in touch/i.test(pageText);

    screenshot_post = await takeScreenshot(page, screenshot_dir, application_id, 'post');

    if (!confirmed) {
      return buildResult({
        outcome:        'manual_required',
        reason:         'ats_page_error',
        error:          'Submission did not reach a confirmation page.',
        fields_filled,
        fields_skipped,
        screenshot_pre,
        screenshot_post,
        apply_url,
        instructions:   'The form was submitted but no confirmation was detected. Check the screenshot.',
      });
    }

    const confirmationText   = pageText.slice(0, 500);
    const confirmationNumber = extractConfirmationNumber(pageText);

    return buildResult({
      success:            true,
      outcome:            'submitted',
      apply_url,
      confirmation_text:  confirmationText,
      confirmation_number: confirmationNumber,
      fields_filled,
      fields_skipped,
      screenshot_pre,
      screenshot_post,
      error:              null,
      reason:             null,
    });

  } finally {
    await browser.close().catch(() => {});
  }
}

// ── Helpers ─────────────────────────────────────────────────────────────────

async function fillIfExists(page, selector, value) {
  if (!value) return false;
  const el = await page.$(selector);
  if (!el) return false;
  await el.fill('');
  await el.type(String(value), { delay: 60 + Math.random() * 60 });
  return true;
}

async function fillFirstMatch(page, selectors, value) {
  for (const sel of selectors) {
    if (await fillIfExists(page, sel, value)) return true;
  }
  return false;
}

async function fillReactSelect(page, containerSelector, value) {
  if (!value) return false;
  const container = await page.$(containerSelector);
  if (!container) return false;

  // Try common react-select input patterns within this field.
  const input = await container.$('input')
    || await page.$(`${containerSelector} input`)
    || await page.$('input[id*="candidate-location"]');

  if (!input) return false;

  await input.click().catch(() => {});
  await input.fill('');
  await input.type(String(value), { delay: 55 + Math.random() * 45 });
  await humanDelay(350, 700);
  await page.keyboard.press('ArrowDown').catch(() => {});
  await page.keyboard.press('Enter').catch(() => {});
  await humanDelay(250, 500);
  return true;
}

async function selectPreferNotToSay(page) {
  // For each select that likely is a demographic field, pick "prefer not to say"
  const selects = await page.$$('select');
  for (const sel of selects) {
    const options = await sel.$$eval('option', opts =>
      opts.map(o => ({ value: o.value, text: o.textContent.trim().toLowerCase() }))
    );
    const preferOption = options.find(o =>
      /prefer not|decline|not to answer|choose not/i.test(o.text)
    );
    if (preferOption) {
      await sel.selectOption({ value: preferOption.value });
    }
  }
}

function buildFieldMap(payload) {
  const p = payload.personal_info || {};
  return {
    first_name:  p.first_name || '',
    last_name:   p.last_name  || '',
    email:       p.email      || '',
    phone:       p.phone      || '',
    location:    [p.city, p.state].filter(Boolean).join(', '),
    linkedin:    p.linkedin_url || '',
  };
}

module.exports = { apply };
