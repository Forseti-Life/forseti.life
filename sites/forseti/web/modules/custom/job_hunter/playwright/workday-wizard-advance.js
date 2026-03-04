/**
 * workday-wizard-advance.js — Playwright stealth script for Workday wizard steps 2-7.
 *
 * Handles:
 *   Step 2 — My Information     : Verify pre-filled fields, fill gaps, click Continue.
 *   Step 3 — My Experience      : Verify pre-filled fields, fill gaps, click Continue.
 *   Step 4 — Application Questions : Screenshot & flag for manual review (questions vary per job).
 *   Step 5 — Voluntary Disclosures : Fill EEO standard disclosures, click Continue.
 *   Step 6 — Self-Identify         : Fill disability self-identification, click Continue.
 *   Step 7 — Review & Submit       : Take review screenshot, click Submit.
 *
 * Usage:
 *   node workday-wizard-advance.js \
 *     --payload-file=/tmp/jh_wz_xyz.json \
 *     --output-file=/tmp/jh_wz_out.json \
 *     [--timeout=120] \
 *     [--executable-path=/usr/bin/google-chrome]
 *
 * Payload file format:
 *   {
 *     "username": "user@example.com",
 *     "password": "secretpass",
 *     "apply_url": "https://jj.wd5.myworkdayjobs.com/en-US/JJ/job/.../apply",
 *     "target_step": "my_information",   // one of: my_information, my_experience, application_questions,
 *                                        //         voluntary_disclosures, self_identify, review_submit
 *     "profile_data": {                  // optional — used for filling missing fields
 *       "full_name": "Keith Aumiller",
 *       "first_name": "Keith",
 *       "last_name": "Aumiller",
 *       "email": "keith.aumiller@example.com",
 *       "phone": "(314) 369-0811",
 *       "city": "Philadelphia",
 *       "state": "PA",
 *       "country": "United States",
 *       "linkedin": "https://www.linkedin.com/in/keithaumiller/",
 *       "eeo_gender": "<from user profile>",
 *       "eeo_ethnicity": "<from user profile>",
 *       "eeo_veteran": "<from user profile>",
 *       "disability_status": "<from user profile>",
 *       "work_authorized_us": "<from user profile, e.g. Yes/No>",
 *       "requires_sponsorship": "<from user profile, e.g. Yes/No>",
 *       "age_18_or_older": "<from user profile, e.g. Yes/No>"
 *     },
 *     "screenshot_dir": "/var/private/forseti/job_hunter/screenshots",
 *     "application_id": 1
 *   }
 *
 * The payload file is deleted immediately after reading.
 *
 * Output JSON:
 *   {
 *     "ok": true/false,
 *     "target_step": "my_information",
 *     "detected_page": "My Information",
 *     "page_matched": true/false,
 *     "fields_filled": ["first_name", "last_name"],
 *     "fields_skipped": ["phone"],
 *     "continue_clicked": true/false,
 *     "post_continue_url": "...",
 *     "page_title": "...",
 *     "needs_manual_review": false,
 *     "evidence": "...",
 *     "screenshots": [],
 *     "error": ""
 *   }
 */

'use strict';

const fs = require('fs');
const path = require('path');
const minimist = require('minimist');
const { launchBrowser, humanType, humanDelay, sleep, takeScreenshot } = require('./utils/stealth');

const args = minimist(process.argv.slice(2));

const PAYLOAD_FILE = args['payload-file'] || '';
const OUTPUT_FILE  = args['output-file'] || '';
const TIMEOUT      = parseInt(args['timeout'] || '120', 10) * 1000;
const EXEC_PATH    = args['executable-path'] || '';

// ── Step metadata ──────────────────────────────────────────────────────────────

const STEP_PAGE_HEADINGS = {
  my_information:        ['My Information'],
  my_experience:         ['My Experience'],
  application_questions: ['Application Questions', 'Job-Specific Information'],
  voluntary_disclosures: ['Voluntary Disclosures', 'EEO Self-Identification'],
  self_identify:         ['Self Identify', 'Self-Identify', 'Disability'],
  review_submit:         ['Review', 'Review & Submit', 'Summary'],
};

const AUTO_STEP_ORDER = [
  'my_information',
  'my_experience',
  'application_questions',
  'voluntary_disclosures',
  'self_identify',
  'review_submit',
];

// ── Helpers ────────────────────────────────────────────────────────────────────

function writeResult(result) {
  const json = JSON.stringify(result, null, 2);
  if (OUTPUT_FILE) {
    fs.writeFileSync(OUTPUT_FILE, json, 'utf8');
  } else {
    process.stdout.write(json + '\n');
  }
}

function fail(msg) {
  writeResult({
    ok: false,
    target_step: '',
    detected_page: '',
    page_matched: false,
    fields_filled: [],
    fields_skipped: [],
    continue_clicked: false,
    post_continue_url: '',
    page_title: '',
    needs_manual_review: false,
    evidence: '',
    screenshots: [],
    error: msg,
  });
  process.exit(1);
}

// ── Read payload ───────────────────────────────────────────────────────────────

if (!PAYLOAD_FILE || !fs.existsSync(PAYLOAD_FILE)) {
  fail('Payload file not found: ' + PAYLOAD_FILE);
}

let payload;
try {
  payload = JSON.parse(fs.readFileSync(PAYLOAD_FILE, 'utf8'));
  fs.unlinkSync(PAYLOAD_FILE);
} catch (e) {
  try { fs.unlinkSync(PAYLOAD_FILE); } catch (_) {}
  fail('Failed to parse payload file: ' + e.message);
}

const {
  username = '',
  password = '',
  apply_url = '',
  target_step = '',
  start_step = 'my_information',
  profile_data = {},
  resume_pdf_path = '',
  screenshot_dir = '',
  application_id = 0,
} = payload;

if (!username || !password || !apply_url) {
  fail('Payload must include username, password, and apply_url.');
}

const VALID_TARGETS = [...Object.keys(STEP_PAGE_HEADINGS), 'wizard_auto', 'wizard_validate'];
if (!target_step || !VALID_TARGETS.includes(target_step)) {
  fail('Invalid target_step: ' + target_step + '. Must be one of: ' + VALID_TARGETS.join(', '));
}

if ((target_step === 'wizard_auto' || target_step === 'wizard_validate') && !AUTO_STEP_ORDER.includes(start_step)) {
  fail('Invalid start_step for ' + target_step + ': ' + start_step + '. Must be one of: ' + AUTO_STEP_ORDER.join(', '));
}

// ── Workday URL helpers ────────────────────────────────────────────────────────

function parseWorkdayUrls(applyUrl) {
  const url = new URL(applyUrl);
  const parts = url.pathname.split('/').filter(Boolean);
  const locale = parts[0] || 'en-US';
  const siteId = parts[1] || '';
  const base   = `${url.protocol}//${url.host}`;

  return {
    base,
    locale,
    siteId,
    loginUrl:  `${base}/${locale}/${siteId}/login`,
    signInUrl: `${base}/${locale}/${siteId}/login?redirect=%2F${locale}%2F${siteId}%2FuserHome`,
  };
}

// ── Field filling helpers ──────────────────────────────────────────────────────

/**
 * Try to fill a Workday form field by data-automation-id, only if currently empty.
 * Returns the field name if filled, null if skipped/failed.
 */
async function fillFieldIfEmpty(page, automationId, value, fieldName) {
  if (!value) return null;
  try {
    const selector = `[data-automation-id="${automationId}"]`;
    const el = page.locator(selector).first();
    await el.waitFor({ state: 'attached', timeout: 3000 });

    // Check if it's an input/textarea.
    const tagName = await el.evaluate(node => node.tagName.toLowerCase());
    if (tagName === 'input' || tagName === 'textarea') {
      const currentVal = await el.inputValue({ timeout: 2000 });
      if (currentVal && currentVal.trim().length > 0) {
        process.stderr.write(`INFO: Field "${fieldName}" already has value: "${currentVal.substring(0, 30)}..." — skipping.\n`);
        return null; // Already filled.
      }
      await el.click({ timeout: 2000 });
      await humanDelay(200, 400);
      await el.fill('');
      await humanType(page, selector, value);
      process.stderr.write(`INFO: Filled field "${fieldName}" with "${value.substring(0, 30)}..."\n`);
      return fieldName;
    }
  } catch (e) {
    process.stderr.write(`WARN: Could not fill field "${fieldName}" (${automationId}): ${e.message}\n`);
  }
  return null;
}

/**
 * Try to select a dropdown option in Workday's custom dropdown component.
 */
async function selectDropdownOption(page, automationId, optionText, fieldName) {
  if (!optionText) return null;
  try {
    const selector = `[data-automation-id="${automationId}"]`;
    const el = page.locator(selector).first();
    await el.waitFor({ state: 'visible', timeout: 3000 });

    // Check if already has the correct value.
    const currentText = await el.textContent({ timeout: 2000 });
    if (currentText && currentText.includes(optionText)) {
      process.stderr.write(`INFO: Dropdown "${fieldName}" already set to "${optionText}" — skipping.\n`);
      return null;
    }

    // Click to open dropdown.
    await el.click({ timeout: 3000 });
    await humanDelay(500, 1000);

    // Select the option.
    const option = page.locator(`[data-automation-id="promptOption"]:has-text("${optionText}")`).first();
    await option.waitFor({ state: 'visible', timeout: 5000 });
    await option.click({ timeout: 3000 });
    process.stderr.write(`INFO: Selected dropdown "${fieldName}" → "${optionText}"\n`);
    await humanDelay(300, 600);
    return fieldName;
  } catch (e) {
    process.stderr.write(`WARN: Could not select dropdown "${fieldName}" (${automationId}): ${e.message}\n`);
    return null;
  }
}

/**
 * Select a radio button option in Workday.
 */
async function selectRadioOption(page, labelText, fieldName) {
  if (!labelText) return null;
  try {
    // Workday uses div/label combos with data-automation-id, or standard radio patterns.
    const selectors = [
      `label:has-text("${labelText}")`,
      `[data-automation-id="radioBtn"]:has-text("${labelText}")`,
      `div[role="radio"]:has-text("${labelText}")`,
      `input[type="radio"] + label:has-text("${labelText}")`,
    ];

    for (const sel of selectors) {
      try {
        const el = page.locator(sel).first();
        await el.waitFor({ state: 'visible', timeout: 3000 });
        await el.click({ timeout: 2000 });
        process.stderr.write(`INFO: Selected radio "${fieldName}" → "${labelText}" via ${sel}\n`);
        await humanDelay(300, 500);
        return fieldName;
      } catch (_) {
        continue;
      }
    }
  } catch (e) {
    process.stderr.write(`WARN: Could not select radio "${fieldName}": ${e.message}\n`);
  }
  return null;
}

/**
 * Click a checkbox by label text if not already checked.
 */
async function checkCheckboxByLabel(page, labelText, fieldName) {
  if (!labelText) return null;
  try {
    const checkbox = page.locator(`label:has-text("${labelText}") input[type="checkbox"], [data-automation-id="checkboxPanel"]:has-text("${labelText}") input[type="checkbox"]`).first();
    const isChecked = await checkbox.isChecked({ timeout: 3000 });
    if (!isChecked) {
      await checkbox.check({ timeout: 3000 });
      process.stderr.write(`INFO: Checked checkbox "${fieldName}"\n`);
      return fieldName;
    }
    process.stderr.write(`INFO: Checkbox "${fieldName}" already checked — skipping.\n`);
  } catch (e) {
    process.stderr.write(`WARN: Could not check "${fieldName}": ${e.message}\n`);
  }
  return null;
}

async function answerRadioInQuestion(page, questionText, answerText, fieldName) {
  if (!questionText || !answerText) return null;
  try {
    const container = page.locator(`[data-automation-id="formField"]:has-text("${questionText}")`).first();
    await container.waitFor({ state: 'visible', timeout: 2500 });
    const choices = [
      `label:has-text("${answerText}")`,
      `[data-automation-id="radioBtn"]:has-text("${answerText}")`,
      `div[role="radio"]:has-text("${answerText}")`,
    ];
    for (const sel of choices) {
      try {
        const el = container.locator(sel).first();
        await el.waitFor({ state: 'visible', timeout: 1200 });
        await el.click({ timeout: 1800, force: true });
        process.stderr.write(`INFO: Answered question "${fieldName}" with "${answerText}"\n`);
        return fieldName;
      } catch (_) {}
    }
  } catch (_) {}
  return null;
}

async function answerTextInQuestion(page, questionText, value, fieldName) {
  if (!questionText || !value) return null;
  try {
    const container = page.locator(`[data-automation-id="formField"]:has-text("${questionText}")`).first();
    await container.waitFor({ state: 'visible', timeout: 2500 });
    const field = container.locator('input[type="text"], input[type="email"], textarea, [contenteditable="true"]').first();
    await field.waitFor({ state: 'visible', timeout: 1500 });
    try {
      const tag = await field.evaluate((el) => el.tagName.toLowerCase());
      if (tag === 'input' || tag === 'textarea') {
        const currentVal = await field.inputValue({ timeout: 1000 }).catch(() => '');
        if ((currentVal || '').trim().length > 0) {
          return null;
        }
        await field.fill('');
        await humanType(page, 'input:focus, textarea:focus', String(value));
      } else {
        await field.click({ timeout: 1200 });
        await page.keyboard.type(String(value), { delay: 15 });
      }
    } catch (_) {
      await field.click({ timeout: 1200 });
      await page.keyboard.type(String(value), { delay: 15 });
    }
    process.stderr.write(`INFO: Filled question "${fieldName}"\n`);
    return fieldName;
  } catch (_) {}
  return null;
}

async function answerDropdownInQuestion(page, questionText, optionText, fieldName) {
  if (!questionText || !optionText) return null;
  try {
    const container = page.locator(`[data-automation-id="formField"]:has-text("${questionText}")`).first();
    await container.waitFor({ state: 'visible', timeout: 2500 });
    const openers = [
      '[data-automation-id="promptIcon"]',
      '[data-automation-id="dropdown"]',
      '[role="combobox"]',
      'input[aria-haspopup="listbox"]',
      'button[aria-haspopup="listbox"]',
    ];
    for (const opener of openers) {
      try {
        const el = container.locator(opener).first();
        await el.waitFor({ state: 'visible', timeout: 1000 });
        await el.click({ timeout: 1500, force: true });
        break;
      } catch (_) {}
    }

    const optionCandidates = [
      `[data-automation-id="promptOption"]:has-text("${optionText}")`,
      `li[role="option"]:has-text("${optionText}")`,
      `div[role="option"]:has-text("${optionText}")`,
    ];
    for (const sel of optionCandidates) {
      try {
        const opt = page.locator(sel).first();
        await opt.waitFor({ state: 'visible', timeout: 1800 });
        await opt.click({ timeout: 1800, force: true });
        process.stderr.write(`INFO: Selected question dropdown "${fieldName}" → "${optionText}"\n`);
        return fieldName;
      } catch (_) {}
    }

    try {
      const input = container.locator('input').first();
      await input.waitFor({ state: 'visible', timeout: 1000 });
      await input.fill('');
      await input.type(String(optionText), { delay: 12 });
      await page.keyboard.press('Enter');
      process.stderr.write(`INFO: Typed question dropdown "${fieldName}" → "${optionText}"\n`);
      return fieldName;
    } catch (_) {}
  } catch (_) {}
  return null;
}

async function answerQuestionByDomFallback(page, questionNeedle, answerValue, fieldName) {
  if (!questionNeedle || !answerValue) return null;
  try {
    const ok = await page.evaluate(({ questionNeedle, answerValue }) => {
      const q = String(questionNeedle).toLowerCase();
      const a = String(answerValue);
      const blocks = Array.from(document.querySelectorAll('div, fieldset, section, li'));
      const target = blocks.find((el) => {
        const txt = (el.innerText || '').toLowerCase();
        if (!txt.includes(q)) return false;
        return !!el.querySelector('input, textarea, select, [role="radio"], [role="combobox"], [data-automation-id*="prompt"]');
      });
      if (!target) return false;

      const radios = Array.from(target.querySelectorAll('label, [role="radio"], [data-automation-id="radioBtn"]'));
      const radioMatch = radios.find((el) => (el.innerText || '').trim().toLowerCase() === a.trim().toLowerCase());
      if (radioMatch) {
        radioMatch.click();
        return true;
      }

      const select = target.querySelector('select');
      if (select) {
        const opt = Array.from(select.options || []).find((o) => (o.text || '').toLowerCase().includes(a.toLowerCase()) || String(o.value || '').toLowerCase().includes(a.toLowerCase()));
        if (opt) {
          select.value = opt.value;
          select.dispatchEvent(new Event('input', { bubbles: true }));
          select.dispatchEvent(new Event('change', { bubbles: true }));
          return true;
        }
      }

      const input = target.querySelector('input[type="text"], input[type="email"], textarea, input:not([type])');
      if (input) {
        if ((input.value || '').trim().length === 0) {
          input.focus();
          input.value = a;
          input.dispatchEvent(new Event('input', { bubbles: true }));
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return true;
      }

      const combo = target.querySelector('[role="combobox"], input[aria-haspopup="listbox"], [data-automation-id*="prompt"]');
      if (combo) {
        combo.click();
        return false;
      }

      return false;
    }, { questionNeedle, answerValue });

    if (ok) {
      process.stderr.write(`INFO: Answered question via DOM fallback: ${fieldName}\n`);
      return fieldName;
    }
  } catch (_) {}
  return null;
}

async function answerQuestionByXPathContainer(page, questionNeedle, answerValue, fieldName, mode = 'text') {
  if (!questionNeedle || !answerValue) return null;
  const needle = String(questionNeedle).toLowerCase();
  const value = String(answerValue).trim();
  const xpath = `xpath=(//*[contains(translate(normalize-space(.), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), "${needle}")]/ancestor::*[.//input or .//*[@role='combobox'] or .//select][1])[1]`;

  try {
    const container = page.locator(xpath).first();
    await container.waitFor({ state: 'visible', timeout: 1800 });

    if (mode === 'radio') {
      const radioCandidates = [
        `label:has-text("${value}")`,
        `[data-automation-id="radioBtn"]:has-text("${value}")`,
        `[role="radio"]:has-text("${value}")`,
        `button:has-text("${value}")`,
      ];
      for (const sel of radioCandidates) {
        try {
          const el = container.locator(sel).first();
          await el.waitFor({ state: 'visible', timeout: 1000 });
          await el.click({ timeout: 1500, force: true });
          process.stderr.write(`INFO: XPath-resolved radio answer for ${fieldName}\n`);
          return fieldName;
        } catch (_) {}
      }
      return null;
    }

    if (mode === 'dropdown') {
      const comboOpeners = [
        '[role="combobox"]',
        'input[aria-haspopup="listbox"]',
        '[data-automation-id*="prompt"]',
        '[data-automation-id="promptIcon"]',
        'select',
      ];

      for (const sel of comboOpeners) {
        try {
          const opener = container.locator(sel).first();
          await opener.waitFor({ state: 'visible', timeout: 1000 });
          const tag = await opener.evaluate((el) => el.tagName.toLowerCase());
          if (tag === 'select') {
            const chosen = await opener.evaluate((el, val) => {
              const options = Array.from(el.options || []);
              const v = String(val).toLowerCase();
              const hit = options.find((o) => String(o.text || '').toLowerCase().includes(v) || String(o.value || '').toLowerCase().includes(v));
              if (!hit) return false;
              el.value = hit.value;
              el.dispatchEvent(new Event('input', { bubbles: true }));
              el.dispatchEvent(new Event('change', { bubbles: true }));
              return true;
            }, value);
            if (chosen) {
              process.stderr.write(`INFO: XPath-resolved select answer for ${fieldName}\n`);
              return fieldName;
            }
            continue;
          }

          await opener.click({ timeout: 1500, force: true });
          await humanDelay(150, 350);

          const options = [
            `[data-automation-id="promptOption"]:has-text("${value}")`,
            `li[role="option"]:has-text("${value}")`,
            `div[role="option"]:has-text("${value}")`,
          ];
          for (const optSel of options) {
            try {
              const opt = page.locator(optSel).first();
              await opt.waitFor({ state: 'visible', timeout: 1000 });
              await opt.click({ timeout: 1500, force: true });
              process.stderr.write(`INFO: XPath-resolved dropdown answer for ${fieldName}\n`);
              return fieldName;
            } catch (_) {}
          }

          // Type + Enter fallback for combobox inputs.
          try {
            const input = container.locator('input').first();
            await input.waitFor({ state: 'visible', timeout: 1000 });
            await input.click({ timeout: 1200 });
            await input.fill('');
            await input.type(value, { delay: 15 });
            await page.keyboard.press('Enter');
            process.stderr.write(`INFO: XPath-typed dropdown answer for ${fieldName}\n`);
            return fieldName;
          } catch (_) {}
        } catch (_) {}
      }
      return null;
    }

    // text mode
    const input = container.locator('input[type="text"], input[type="email"], textarea, input:not([type])').first();
    await input.waitFor({ state: 'visible', timeout: 1000 });
    const currentVal = await input.inputValue({ timeout: 800 }).catch(() => '');
    if ((currentVal || '').trim().length === 0) {
      await input.click({ timeout: 1000 });
      await input.fill('');
      await input.type(value, { delay: 15 });
      await input.dispatchEvent('change');
    }
    process.stderr.write(`INFO: XPath-resolved text answer for ${fieldName}\n`);
    return fieldName;
  } catch (_) {
    return null;
  }
}

async function answerRequiredQuestionsGlobalFallback(page, profile) {
  try {
    const result = await page.evaluate((profile) => {
      const filled = [];

      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };

      const setInput = (container, value) => {
        if (!container || !value) return false;
        const input = container.querySelector('input[type="text"], input[type="email"], textarea, input:not([type]), input[type="search"]');
        if (input && isVisible(input)) {
          if ((input.value || '').trim().length === 0) {
            input.focus();
            input.value = String(value);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return true;
        }
        return false;
      };

      const setRadioByText = (container, value) => {
        if (!container || !value) return false;
        const target = String(value).trim().toLowerCase();
        const candidates = Array.from(container.querySelectorAll('label, [role="radio"], [data-automation-id="radioBtn"], button, div[role="button"]'));
        const hit = candidates.find((el) => {
          if (!isVisible(el)) return false;
          const txt = (el.innerText || '').trim().toLowerCase();
          return txt === target || txt.startsWith(target + ' ');
        });
        if (hit) {
          hit.click();
          return true;
        }
        return false;
      };

      const setSelectLike = (container, value) => {
        if (!container || !value) return false;
        const select = container.querySelector('select');
        if (select && isVisible(select)) {
          const opt = Array.from(select.options || []).find((o) => (o.text || '').toLowerCase().includes(String(value).toLowerCase()) || String(o.value || '').toLowerCase().includes(String(value).toLowerCase()));
          if (opt) {
            select.value = opt.value;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
          }
        }

        const comboInput = container.querySelector('input[aria-haspopup="listbox"], [role="combobox"] input, [role="combobox"]');
        if (comboInput && isVisible(comboInput)) {
          comboInput.click();
          if (comboInput.tagName && comboInput.tagName.toLowerCase() === 'input') {
            comboInput.value = String(value);
            comboInput.dispatchEvent(new Event('input', { bubbles: true }));
            comboInput.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return true;
        }
        return false;
      };

      const findContainer = (needle) => {
        const n = String(needle).toLowerCase();
        const elems = Array.from(document.querySelectorAll('label, legend, p, div, span, h3, h4'));
        const textNode = elems.find((el) => {
          if (!isVisible(el)) return false;
          const txt = (el.innerText || '').toLowerCase();
          return txt.includes(n);
        });
        if (!textNode) return null;
        return textNode.closest('div, fieldset, section, li, form') || textNode.parentElement;
      };

      const hear = findContainer('how did you hear about us');
      if (hear && profile.hear_about_us) {
        if (setSelectLike(hear, profile.hear_about_us) || setInput(hear, profile.hear_about_us)) {
          filled.push('hear_about_us');
        }
      }

      const prior = findContainer('have you ever been employed');
      if (prior && profile.prior_company_employment) {
        if (setRadioByText(prior, profile.prior_company_employment)) {
          filled.push('prior_company_employment');
        }
      }

      const phoneType = findContainer('phone device type');
      if (phoneType && profile.phone_device_type) {
        if (setSelectLike(phoneType, profile.phone_device_type)) {
          filled.push('phone_device_type');
        }
      }

      return filled;
    }, profile);

    return Array.isArray(result) ? result : [];
  } catch (_) {
    return [];
  }
}

// ── Step-specific handlers ─────────────────────────────────────────────────────

/**
 * Step 2: My Information — verify/fill personal info.
 * Workday typically pre-fills this from the resume upload. We verify and fill gaps.
 */
async function handleMyInformation(page, profile, result) {
  const filled = [];
  const skipped = [];

  // Split full_name into first/last if not explicitly provided.
  let firstName = profile.first_name || '';
  let lastName  = profile.last_name || '';
  if (!firstName && !lastName && profile.full_name) {
    const parts = profile.full_name.trim().split(/\s+/);
    firstName = parts[0] || '';
    lastName  = parts.slice(1).join(' ') || '';
  }

  // Common Workday My Information fields (data-automation-id).
  const fieldMap = [
    { automationId: 'legalNameSection_firstName',  value: firstName,        name: 'first_name' },
    { automationId: 'legalNameSection_lastName',   value: lastName,         name: 'last_name' },
    { automationId: 'addressSection_addressLine1', value: profile.address || '', name: 'address_line1' },
    { automationId: 'addressSection_city',         value: profile.city || '',    name: 'city' },
    { automationId: 'addressSection_postalCode',   value: profile.zip || '',     name: 'postal_code' },
    { automationId: 'phone-number',                value: profile.phone || '',   name: 'phone' },
    { automationId: 'email',                       value: profile.email || '',   name: 'email' },
  ];

  for (const f of fieldMap) {
    const r = await fillFieldIfEmpty(page, f.automationId, f.value, f.name);
    if (r) filled.push(r);
    else skipped.push(f.name);
  }

  // Country dropdown.
  if (profile.country) {
    const r = await selectDropdownOption(page, 'addressSection_countryRegion', profile.country, 'country');
    if (r) filled.push(r);
    else skipped.push('country');
  }

  // State dropdown.
  if (profile.state) {
    const r = await selectDropdownOption(page, 'addressSection_countryRegionStateProvince', profile.state, 'state');
    if (r) filled.push(r);
    else skipped.push('state');
  }

  // LinkedIn URL.
  if (profile.linkedin) {
    const r = await fillFieldIfEmpty(page, 'linkedinQuestion', profile.linkedin, 'linkedin');
    if (r) filled.push(r);
    else skipped.push('linkedin');
  }

  if (profile.phone_device_type) {
    const r = await answerDropdownInQuestion(page, 'Phone Device Type', profile.phone_device_type, 'phone_device_type');
    if (r) filled.push(r);
    else skipped.push('phone_device_type');
  }

  if (profile.hear_about_us) {
    let r = await answerDropdownInQuestion(page, 'How Did You Hear About Us', profile.hear_about_us, 'hear_about_us');
    if (!r) {
      r = await answerTextInQuestion(page, 'How Did You Hear About Us', profile.hear_about_us, 'hear_about_us');
    }
    if (!r) {
      r = await answerQuestionByDomFallback(page, 'How Did You Hear About Us', profile.hear_about_us, 'hear_about_us');
    }
    if (r) filled.push(r);
    else skipped.push('hear_about_us');
  }

  if (profile.prior_company_employment) {
    let r = await answerRadioInQuestion(page, 'Have you ever been employed', profile.prior_company_employment, 'prior_company_employment');
    if (!r) {
      r = await answerQuestionByDomFallback(page, 'Have you ever been employed', profile.prior_company_employment, 'prior_company_employment');
    }
    if (r) filled.push(r);
    else skipped.push('prior_company_employment');
  }

  if ((profile.prior_company_employment || '').toLowerCase() === 'yes') {
    if (profile.prior_company_wwid) {
      const r = await answerTextInQuestion(page, 'WWID', profile.prior_company_wwid, 'prior_company_wwid');
      if (r) filled.push(r);
      else skipped.push('prior_company_wwid');
    }
    if (profile.prior_company_email) {
      const r = await answerTextInQuestion(page, 'Email', profile.prior_company_email, 'prior_company_email');
      if (r) filled.push(r);
      else skipped.push('prior_company_email');
    }
  }

  const globalFallbackFilled = await answerRequiredQuestionsGlobalFallback(page, profile);
  for (const f of globalFallbackFilled) {
    if (!filled.includes(f)) {
      filled.push(f);
    }
    const ix = skipped.indexOf(f);
    if (ix >= 0) {
      skipped.splice(ix, 1);
    }
  }

  result.fields_filled = filled;
  result.fields_skipped = skipped;
}

/**
 * Step 3: My Experience — verify work history and education from resume.
 * Workday pre-fills most of this from the resume. We verify sections exist.
 */
async function handleMyExperience(page, profile, result) {
  const filled = [];
  const skipped = [];

  // Check if work experience section has entries.
  try {
    const workEntries = page.locator('[data-automation-id="workExperienceSection"], [data-automation-id="experienceItem"]');
    const count = await workEntries.count();
    process.stderr.write(`INFO: Work experience entries found: ${count}\n`);
    if (count > 0) {
      filled.push('work_experience_present');
    } else {
      skipped.push('work_experience_empty');
    }
  } catch (_) {
    skipped.push('work_experience_check_failed');
  }

  // Validation/correction subflow: ensure required core experience fields are
  // present and align to profile data when fields are available.
  const correctedPrimary = await forceFillExperienceFields(page, profile);
  filled.push(...correctedPrimary.filled.filter((x) => !filled.includes(x)));
  for (const s of correctedPrimary.skipped) {
    if (!skipped.includes(s)) {
      skipped.push(s);
    }
  }
  if (correctedPrimary.filled.length > 0) {
    const committed = await commitMyExperienceEditor(page);
    if (committed) {
      filled.push('experience_editor_saved');
    }
  }

  // If no entry is detected, try opening the editor and applying corrections again.
  if (!filled.includes('work_experience_present')) {
    const opened = await openMyExperienceEditor(page);
    if (opened) {
      filled.push('opened_experience_editor');
      const correctedAfterOpen = await forceFillExperienceFields(page, profile);
      filled.push(...correctedAfterOpen.filled.filter((x) => !filled.includes(x)));
      for (const s of correctedAfterOpen.skipped) {
        if (!skipped.includes(s)) {
          skipped.push(s);
        }
      }
      if (correctedAfterOpen.filled.length > 0) {
        const committed = await commitMyExperienceEditor(page);
        if (committed) {
          filled.push('experience_editor_saved');
        }
      }
    }
  }

  // If the page demands a file upload, attempt to upload the tailored resume again.
  if (resume_pdf_path) {
    const errorText = (await page.locator('[data-automation-id="errorMessage"], [data-automation-id="inlineError"], .error-message-text, [data-automation-id*="error" i]').allTextContents().catch(() => [])).join(' ').toLowerCase();
    if (errorText.includes('upload a file') || errorText.includes('5mb max')) {
      const uploadEvidence = [];
      const uploaded = await uploadRequiredFileIfPresent(page, resume_pdf_path, uploadEvidence);
      if (uploaded) {
        filled.push('required_file_upload');
      } else {
        skipped.push('required_file_upload');
      }
    }
  }

  // Check if education section has entries.
  try {
    const eduEntries = page.locator('[data-automation-id="educationSection"], [data-automation-id="educationItem"]');
    const count = await eduEntries.count();
    process.stderr.write(`INFO: Education entries found: ${count}\n`);
    if (count > 0) {
      filled.push('education_present');
    } else {
      skipped.push('education_empty');
    }
  } catch (_) {
    skipped.push('education_check_failed');
  }

  // Check for skills section.
  try {
    const skillEntries = page.locator('[data-automation-id="skillsSection"], [data-automation-id="skillItem"]');
    const count = await skillEntries.count();
    if (count > 0) filled.push('skills_present');
  } catch (_) {}

  result.fields_filled = filled;
  result.fields_skipped = skipped;
}

/**
 * Step 4: Application Questions — these are per-job custom questions.
 * We cannot automate these generically. Take a screenshot and flag for review.
 */
async function handleApplicationQuestions(page, profile, result) {
  result.needs_manual_review = false;
  result.fields_filled = [];
  result.fields_skipped = [];

  // Count how many questions are displayed.
  let questionCount = 0;
  try {
    const questions = page.locator('[data-automation-id="questionSection"] [data-automation-id="formField"], [data-automation-id="questionField"]');
    questionCount = await questions.count();
    process.stderr.write(`INFO: Found ${questionCount} application question fields.\n`);
    result.fields_skipped.push(`${questionCount}_questions_found`);
  } catch (_) {}

  if (questionCount > 0) {
    result.needs_manual_review = true;
    result.fields_skipped.push('application_questions_require_manual_review');
  }

  // Try to answer common yes/no questions, using profile-provided answers only.
  const commonAnswers = [
    { text: 'legally authorized to work', answer: profile.work_authorized_us || '', field: 'work_authorization' },
    { text: 'authorized to work in the united states', answer: profile.work_authorized_us || '', field: 'work_authorization_alt' },
    { text: 'require sponsorship', answer: profile.requires_sponsorship || '', field: 'sponsorship' },
    { text: 'future require sponsorship', answer: profile.requires_sponsorship || '', field: 'sponsorship_alt' },
    { text: '18 years of age', answer: profile.age_18_or_older || '', field: 'age_18' },
    { text: 'at least 18', answer: profile.age_18_or_older || '', field: 'age_18_alt' },
  ].filter(qa => qa.answer && qa.answer.trim().length > 0);

  for (const qa of commonAnswers) {
    try {
      // Find the question container that has the text.
      const questionContainer = page.locator(`[data-automation-id="formField"]:has-text("${qa.text}")`).first();
      const isVisible = await questionContainer.isVisible({ timeout: 2000 });
      if (!isVisible) continue;

      // Try to find and select the answer within this container.
      const radioLabel = questionContainer.locator(`label:has-text("${qa.answer}"), [data-automation-id="radioBtn"]:has-text("${qa.answer}")`).first();
      await radioLabel.click({ timeout: 3000 });
      process.stderr.write(`INFO: Answered "${qa.field}": ${qa.answer}\n`);
      result.fields_filled.push(qa.field);
    } catch (_) {
      // Question not found or couldn't answer — that's OK.
    }
  }

  // Profile-driven explicit answers for common required company-specific questions.
  if (profile.hear_about_us) {
    const r = await answerDropdownInQuestion(page, 'How Did You Hear About Us', profile.hear_about_us, 'hear_about_us')
      || await answerTextInQuestion(page, 'How Did You Hear About Us', profile.hear_about_us, 'hear_about_us');
    if (r) {
      result.fields_filled.push(r);
    }
  }

  if (profile.prior_company_employment) {
    const r = await answerRadioInQuestion(page, 'Have you ever been employed', profile.prior_company_employment, 'prior_company_employment');
    if (r) {
      result.fields_filled.push(r);
    }
  }

  if ((profile.prior_company_employment || '').toLowerCase() === 'yes') {
    if (profile.prior_company_wwid) {
      const r = await answerTextInQuestion(page, 'WWID', profile.prior_company_wwid, 'prior_company_wwid');
      if (r) result.fields_filled.push(r);
    }
    if (profile.prior_company_email) {
      const r = await answerTextInQuestion(page, 'Email', profile.prior_company_email, 'prior_company_email');
      if (r) result.fields_filled.push(r);
    }
  }
}

/**
 * Step 5: Voluntary Disclosures — EEO gender, race/ethnicity, veteran status.
 */
async function handleVoluntaryDisclosures(page, profile, result) {
  const filled = [];
  const skipped = [];

  // Gender.
  if (profile.eeo_gender) {
    const r = await selectDropdownOption(page, 'genderDropdown', profile.eeo_gender, 'gender');
    if (!r) {
      // Try radio button pattern.
      const r2 = await selectRadioOption(page, profile.eeo_gender, 'gender');
      if (r2) filled.push(r2);
      else skipped.push('gender');
    } else {
      filled.push(r);
    }
  }

  // Race / Ethnicity.
  if (profile.eeo_ethnicity) {
    // Workday often uses checkboxes for race/ethnicity.
    const r = await checkCheckboxByLabel(page, profile.eeo_ethnicity, 'ethnicity');
    if (!r) {
      const r2 = await selectDropdownOption(page, 'ethnicityDropdown', profile.eeo_ethnicity, 'ethnicity');
      if (r2) filled.push(r2);
      else skipped.push('ethnicity');
    } else {
      filled.push(r);
    }
  }

  // Veteran status.
  if (profile.eeo_veteran) {
    const r = await selectDropdownOption(page, 'veteranStatusDropdown', profile.eeo_veteran, 'veteran_status');
    if (!r) {
      const r2 = await selectRadioOption(page, profile.eeo_veteran, 'veteran_status');
      if (r2) filled.push(r2);
      else skipped.push('veteran_status');
    } else {
      filled.push(r);
    }
  }

  result.fields_filled = filled;
  result.fields_skipped = skipped;
}

/**
 * Step 6: Self-Identify — disability self-identification.
 */
async function handleSelfIdentify(page, profile, result) {
  const filled = [];
  const skipped = [];

  if (profile.disability_status) {
    // Common patterns for disability self-identification.
    const selectors = [
      `label:has-text("${profile.disability_status}")`,
      `[data-automation-id="radioBtn"]:has-text("${profile.disability_status}")`,
      `div[role="radio"]:has-text("${profile.disability_status}")`,
    ];

    let found = false;
    for (const sel of selectors) {
      try {
        const el = page.locator(sel).first();
        await el.waitFor({ state: 'visible', timeout: 3000 });
        await el.click({ timeout: 2000 });
        found = true;
        process.stderr.write(`INFO: Selected disability status: "${profile.disability_status}"\n`);
        filled.push('disability_status');
        break;
      } catch (_) {
        continue;
      }
    }

    if (!found) {
      skipped.push('disability_status');
    }
  }

  // Some Workday forms also ask for a name and date on this page.
  if (profile.full_name) {
    const r = await fillFieldIfEmpty(page, 'name', profile.full_name, 'signature_name');
    if (r) filled.push(r);
  }

  result.fields_filled = filled;
  result.fields_skipped = skipped;
}

/**
 * Step 7: Review & Submit — take a screenshot of the review page and click Submit.
 */
async function handleReviewSubmit(page, profile, result) {
  result.fields_filled = [];
  result.fields_skipped = [];

  // Look for any required agreement checkboxes.
  try {
    const agreementCheckboxes = page.locator('[data-automation-id="agreementCheckbox"] input[type="checkbox"], [data-automation-id*="agreement"] input[type="checkbox"], label:has-text("I certify") input[type="checkbox"], label:has-text("I agree") input[type="checkbox"], label:has-text("Terms") input[type="checkbox"], label:has-text("Consent") input[type="checkbox"], input[type="checkbox"][required]');
    const count = await agreementCheckboxes.count();
    for (let i = 0; i < count; i++) {
      try {
        const box = agreementCheckboxes.nth(i);
        await box.scrollIntoViewIfNeeded({ timeout: 2000 });
        const isChecked = await box.isChecked({ timeout: 2000 });
        if (!isChecked) {
          try {
            await box.check({ timeout: 3000 });
          } catch (_) {
            await box.click({ timeout: 3000, force: true });
          }
          result.fields_filled.push(`agreement_checkbox_${i + 1}`);
          process.stderr.write(`INFO: Checked agreement checkbox ${i + 1}\n`);
        }
      } catch (_) {}
    }
  } catch (_) {}
}

// ── Main flow ──────────────────────────────────────────────────────────────────

const STEP_HANDLERS = {
  my_information:        handleMyInformation,
  my_experience:         handleMyExperience,
  application_questions: handleApplicationQuestions,
  voluntary_disclosures: handleVoluntaryDisclosures,
  self_identify:         handleSelfIdentify,
  review_submit:         handleReviewSubmit,
};

async function detectPageHeading(page) {
  let detectedPage = '';
  const headingSelectors = [
    '[data-automation-id="pageHeaderTitle"]',
    '[data-automation-id="stepTitle"]',
    'h2[data-automation-id]',
    '.current-step-title',
  ];

  for (const sel of headingSelectors) {
    try {
      const heading = page.locator(sel).first();
      const text = await heading.textContent({ timeout: 2500 });
      if (text && text.trim()) {
        detectedPage = text.trim();
        break;
      }
    } catch (_) {}
  }

  if (!detectedPage) {
    try {
      const stepIndicator = page.locator('[data-automation-id="activeStep"], .active-step, [aria-current="step"]').first();
      const stepText = await stepIndicator.textContent({ timeout: 2000 });
      detectedPage = (stepText || '').trim();
    } catch (_) {}
  }

  if (!detectedPage) {
    detectedPage = await page.title();
  }
  return detectedPage;
}

async function clickContinueButton(page, evidenceParts) {
  const continueSelectors = [
    'button[data-automation-id="bottom-navigation-next-button"]',
    '[data-automation-id="bottom-navigation"] button:has-text("Continue")',
    '[data-automation-id="bottom-navigation"] button:has-text("Next")',
    '[data-automation-id="bottom-navigation"] button:has-text("Save and Continue")',
    'button:has-text("Save and Continue")',
    '[role="button"]:has-text("Save and Continue")',
    'div[role="button"]:has-text("Save and Continue")',
    'button[data-automation-id="nextButton"]',
    'button[aria-label*="Continue" i]',
    '[role="button"]:has-text("Continue")',
    '[role="button"]:has-text("Next")',
    'div[role="button"]:has-text("Continue")',
    'button:has-text("Continue")',
    'button:has-text("Next")',
  ];

  for (const sel of continueSelectors) {
    try {
      const btn = page.locator(sel).first();
      await btn.waitFor({ state: 'visible', timeout: 700 });
      await btn.scrollIntoViewIfNeeded({ timeout: 1000 });
      const disabled = await btn.evaluate((el) => {
        const aria = (el.getAttribute('aria-disabled') || '').toLowerCase();
        return !!(el.disabled || aria === 'true');
      });
      if (disabled) {
        continue;
      }
      await humanDelay(150, 350);
      await btn.click({ timeout: 1800, force: true });
      evidenceParts.push(`Clicked Continue via ${sel}`);
      return true;
    } catch (_) {}
  }

  try {
    const clicked = await page.evaluate(() => {
      const candidates = Array.from(document.querySelectorAll('button, [role="button"], a, input[type="button"], input[type="submit"]'));
      const match = candidates.find((el) => {
        const txt = ((el.textContent || '') + ' ' + (el.getAttribute('value') || '') + ' ' + (el.getAttribute('aria-label') || '')).toLowerCase();
        const disabled = el.disabled || (el.getAttribute('aria-disabled') || '').toLowerCase() === 'true';
        return !disabled && /save and continue|continue|next/.test(txt);
      });
      if (match) {
        match.scrollIntoView({ behavior: 'instant', block: 'center' });
        match.click();
        return true;
      }
      return false;
    });
    if (clicked) {
      evidenceParts.push('Clicked Continue via DOM fallback');
      return true;
    }
  } catch (_) {}

  return false;
}

async function clickSubmitButton(page, evidenceParts) {
  const submitSelectors = [
    'button[data-automation-id="submitButton"]',
    'button[data-automation-id="bottom-navigation-next-button"]:has-text("Submit")',
    'button:has-text("Submit Application")',
    'button:has-text("Review and Submit")',
    'button:has-text("Submit")',
    '[role="button"][aria-label*="Submit" i]',
    'input[type="submit"]',
  ];

  const clickSubmitNow = async () => {
    for (const sel of submitSelectors) {
      try {
        const btn = page.locator(sel).first();
        await btn.waitFor({ state: 'visible', timeout: 700 });
        await btn.scrollIntoViewIfNeeded({ timeout: 1000 });
        const disabled = await btn.evaluate((el) => {
          const aria = (el.getAttribute('aria-disabled') || '').toLowerCase();
          return !!(el.disabled || aria === 'true');
        });
        if (disabled) {
          continue;
        }
        await humanDelay(150, 350);
        await btn.click({ timeout: 1800, force: true });
        evidenceParts.push(`Clicked Submit via ${sel}`);
        return true;
      } catch (_) {}
    }
    return false;
  };

  const enterApplyFlow = async () => {
    const applyEntrySelectors = [
      'a[href*="/apply/"]',
      'a[href*="autofillWithResume"]',
      'a:has-text("Apply")',
      'button:has-text("Apply")',
      'button[data-automation-id*="apply" i]',
      'a[data-automation-id*="apply" i]',
    ];
    for (const sel of applyEntrySelectors) {
      try {
        const btn = page.locator(sel).first();
        await btn.waitFor({ state: 'visible', timeout: 700 });
        await btn.scrollIntoViewIfNeeded({ timeout: 1000 });
        await btn.click({ timeout: 1800, force: true });
        evidenceParts.push(`Entered apply flow via ${sel}`);
        return true;
      } catch (_) {}
    }
    return false;
  };

  for (let attempt = 0; attempt < 6; attempt++) {
    if (await clickSubmitNow()) {
      return true;
    }

    const url = page.url().toLowerCase();
    if (url.includes('/job/') && !url.includes('/apply/')) {
      const entered = await enterApplyFlow();
      if (entered) {
        await humanDelay(500, 900);
        continue;
      }
    }

    const advanced = await clickContinueButton(page, evidenceParts);
    if (!advanced) {
      break;
    }
    await humanDelay(500, 900);
  }

  return await clickSubmitNow();
}

async function getVisibleActionLabels(page) {
  try {
    const labels = await page.evaluate(() => {
      const isVisible = (el) => {
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };
      return Array.from(document.querySelectorAll('button, a, [role="button"], input[type="submit"], input[type="button"]'))
        .filter((el) => isVisible(el))
        .map((el) => ((el.textContent || '') + ' ' + (el.getAttribute('value') || '') + ' ' + (el.getAttribute('aria-label') || '')).replace(/\s+/g, ' ').trim())
        .filter((text) => text.length > 0)
        .slice(0, 25);
    });
    return Array.isArray(labels) ? labels : [];
  } catch (_) {
    return [];
  }
}

async function resolveValidationErrorsFromProfile(page, profile, evidenceParts, resumePdfPath = '') {
  const resolved = [];
  try {
    const errorTexts = await page.locator('[data-automation-id="errorMessage"], [data-automation-id="inlineError"], .error-message-text, [data-automation-id*="error" i]')
      .allTextContents();
    const combined = (errorTexts || []).join(' ').toLowerCase();
    const hasExperienceErrors = combined.includes('error-job title')
      || combined.includes('error-company')
      || combined.includes('error-from')
      || combined.includes('error-to');

    if (hasExperienceErrors) {
      let opened = await openExperienceEditorFromErrorLinks(page);
      if (!opened) {
        opened = await openMyExperienceEditor(page);
      }
      if (opened) {
        resolved.push('opened_experience_editor');
      }

      const editorVisible = await isExperienceEditorVisible(page);
      const inlineRowsPresent = await page.locator('input[name="jobTitle"][id*="workExperience-"]').count().catch(() => 0);
      if (editorVisible || inlineRowsPresent > 0) {
        const corrected = await forceFillExperienceFields(page, profile);
        for (const f of corrected.filled) {
          if (!resolved.includes(f)) {
            resolved.push(f);
          }
        }

        if (corrected.filled.length > 0) {
          const committed = await commitMyExperienceEditor(page);
          if (committed) {
            resolved.push('experience_editor_saved');
          }
        }
      }
    }

    if (combined.includes('how did you hear about us') && profile.hear_about_us) {
      const linked = await clickErrorLinkAndAnswer(page, 'How Did You Hear About Us', profile.hear_about_us, 'dropdown');
      const r = (linked ? 'hear_about_us' : null)
        || await answerQuestionByXPathContainer(page, 'how did you hear about us', profile.hear_about_us, 'hear_about_us', 'dropdown')
        || await answerDropdownInQuestion(page, 'How Did You Hear About Us', profile.hear_about_us, 'hear_about_us')
        || await answerTextInQuestion(page, 'How Did You Hear About Us', profile.hear_about_us, 'hear_about_us')
        || await answerQuestionByDomFallback(page, 'How Did You Hear About Us', profile.hear_about_us, 'hear_about_us');
      if (r) {
        resolved.push(r);
      }
    }

    if (combined.includes('have you ever been employed') && profile.prior_company_employment) {
      const linked = await clickErrorLinkAndAnswer(page, 'Have you ever been employed', profile.prior_company_employment, 'radio');
      const r = (linked ? 'prior_company_employment' : null)
        || await answerQuestionByXPathContainer(page, 'have you ever been employed', profile.prior_company_employment, 'prior_company_employment', 'radio')
        || await answerRadioInQuestion(page, 'Have you ever been employed', profile.prior_company_employment, 'prior_company_employment')
        || await answerQuestionByDomFallback(page, 'Have you ever been employed', profile.prior_company_employment, 'prior_company_employment');
      if (r) {
        resolved.push(r);
      }

      if ((profile.prior_company_employment || '').toLowerCase() === 'yes') {
        if (profile.prior_company_wwid) {
          const ww = await answerQuestionByXPathContainer(page, 'wwid', profile.prior_company_wwid, 'prior_company_wwid', 'text')
            || await answerTextInQuestion(page, 'WWID', profile.prior_company_wwid, 'prior_company_wwid')
            || await answerQuestionByDomFallback(page, 'WWID', profile.prior_company_wwid, 'prior_company_wwid');
          if (ww) resolved.push(ww);
        }
        if (profile.prior_company_email) {
          const em = await answerQuestionByXPathContainer(page, 'if yes please provide wwid and email', profile.prior_company_email, 'prior_company_email', 'text')
            || await answerTextInQuestion(page, 'Email', profile.prior_company_email, 'prior_company_email')
            || await answerQuestionByDomFallback(page, 'Email', profile.prior_company_email, 'prior_company_email');
          if (em) resolved.push(em);
        }
      }
    }

    if (combined.includes('phone device type') && profile.phone_device_type) {
      const r = await answerQuestionByXPathContainer(page, 'phone device type', profile.phone_device_type, 'phone_device_type', 'dropdown')
        || await answerDropdownInQuestion(page, 'Phone Device Type', profile.phone_device_type, 'phone_device_type')
        || await answerQuestionByDomFallback(page, 'Phone Device Type', profile.phone_device_type, 'phone_device_type');
      if (r) {
        resolved.push(r);
      }
    }

    if (!hasExperienceErrors && combined.includes('error-job title') && profile.experience_job_title) {
      const r = await clickErrorLinkAndAnswer(page, 'Job Title', profile.experience_job_title, 'text')
        ? 'experience_job_title'
        : (await answerQuestionByXPathContainer(page, 'job title', profile.experience_job_title, 'experience_job_title', 'text'));
      if (r) resolved.push(r);
    }

    if (!hasExperienceErrors && combined.includes('error-company') && profile.experience_company) {
      const r = await clickErrorLinkAndAnswer(page, 'Company', profile.experience_company, 'text')
        ? 'experience_company'
        : (await answerQuestionByXPathContainer(page, 'company', profile.experience_company, 'experience_company', 'text'));
      if (r) resolved.push(r);
    }

    if (!hasExperienceErrors && combined.includes('error-from') && profile.experience_from) {
      const r = await clickErrorLinkAndAnswer(page, 'From', profile.experience_from, 'text')
        ? 'experience_from'
        : (await answerQuestionByXPathContainer(page, 'from', profile.experience_from, 'experience_from', 'text'));
      if (r) resolved.push(r);
    }

    if (!hasExperienceErrors && combined.includes('error-to') && profile.experience_to) {
      const r = await clickErrorLinkAndAnswer(page, 'To', profile.experience_to, 'text')
        ? 'experience_to'
        : (await answerQuestionByXPathContainer(page, 'to', profile.experience_to, 'experience_to', 'text'));
      if (r) resolved.push(r);
    }

    if (combined.includes('upload a file') && resumePdfPath) {
      const uploaded = await uploadRequiredFileIfPresent(page, resumePdfPath, evidenceParts);
      if (uploaded) {
        resolved.push('required_file_upload');
      }
    }

    if (resolved.some((f) => f.startsWith('experience_'))) {
      const committed = await commitMyExperienceEditor(page);
      if (committed) {
        resolved.push('experience_editor_saved');
      }
    }

    const global = await answerRequiredQuestionsGlobalFallback(page, profile);
    for (const f of global) {
      if (!resolved.includes(f)) {
        resolved.push(f);
      }
    }

    if (resolved.length > 0) {
      evidenceParts.push(`Resolved validation fields: [${resolved.join(', ')}]`);
    }
  } catch (_) {}

  return resolved;
}

async function answerFocusedField(page, answerValue, mode = 'text') {
  if (!answerValue) return false;
  try {
    const normalized = normalizeMonthYear(answerValue);
    const isDateMode = mode === 'date' || (mode === 'text' && !!normalized);

    // Prefer true keyboard input on the actively focused field so Workday's
    // internal bindings see keystrokes/blur events.
    const activeReady = await page.evaluate(() => {
      const el = document.activeElement;
      if (!el) return false;
      const tag = (el.tagName || '').toLowerCase();
      return tag === 'input' || tag === 'textarea' || el.isContentEditable || el.getAttribute('role') === 'combobox';
    }).catch(() => false);

    if (activeReady) {
      if (isDateMode && normalized) {
        await page.keyboard.press('Control+A').catch(() => {});
        await page.keyboard.press('Backspace').catch(() => {});
        await page.keyboard.type(String(normalized.month), { delay: 12 }).catch(() => {});
        await page.keyboard.press('Tab').catch(() => {});
        await page.keyboard.press('Control+A').catch(() => {});
        await page.keyboard.press('Backspace').catch(() => {});
        await page.keyboard.type(String(normalized.year), { delay: 12 }).catch(() => {});
      } else {
        await page.keyboard.press('Control+A').catch(() => {});
        await page.keyboard.press('Backspace').catch(() => {});
        await page.keyboard.type(String(answerValue), { delay: 12 }).catch(() => {});
      }

      if (mode === 'dropdown') {
        await page.keyboard.press('Enter').catch(() => {});
      }
      await page.keyboard.press('Tab').catch(() => {});
      await humanDelay(120, 260);
      return true;
    }

    return await page.evaluate(({ answerValue, mode }) => {
      const value = String(answerValue);
      const active = document.activeElement;
      const root = (active && active.closest && active.closest('div, fieldset, section, li, form')) || active || document.body;

      const dispatchSet = (el, val) => {
        el.focus();
        if ('value' in el) {
          el.value = val;
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      };

      if (mode === 'radio') {
        const target = value.trim().toLowerCase();
        const radios = Array.from(root.querySelectorAll('label, [role="radio"], [data-automation-id="radioBtn"], button, div[role="button"]'));
        const hit = radios.find((el) => ((el.innerText || '').trim().toLowerCase() === target));
        if (hit) {
          hit.click();
          return true;
        }
        return false;
      }

      if (mode === 'dropdown') {
        const select = root.querySelector('select');
        if (select) {
          const opt = Array.from(select.options || []).find((o) => (o.text || '').toLowerCase().includes(value.toLowerCase()) || String(o.value || '').toLowerCase().includes(value.toLowerCase()));
          if (opt) {
            select.value = opt.value;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
          }
        }
        const input = root.querySelector('input[aria-haspopup="listbox"], [role="combobox"] input, input[type="text"], input[type="search"]');
        if (input) {
          dispatchSet(input, value);
          return true;
        }
        return false;
      }

      const input = root.querySelector('input[type="text"], input[type="email"], textarea, input:not([type]), input[type="search"]');
      if (input) {
        dispatchSet(input, value);
        return true;
      }
      return false;
    }, { answerValue, mode });
  } catch (_) {
    return false;
  }
}

async function clickErrorLinkAndAnswer(page, errorTextNeedle, answerValue, mode = 'text') {
  if (!errorTextNeedle || !answerValue) return false;
  try {
    const linkCandidates = [
      `[data-automation-id="errorHeading"] button:has-text("${errorTextNeedle}")`,
      `button.css-tgkpvs:has-text("${errorTextNeedle}")`,
      `button:has-text("Error"):has-text("${errorTextNeedle}")`,
      `a:has-text("${errorTextNeedle}")`,
      `[role="link"]:has-text("${errorTextNeedle}")`,
      `text=/Error-.*${errorTextNeedle}.*/i`,
    ];

    for (const sel of linkCandidates) {
      try {
        const link = page.locator(sel).first();
        await link.waitFor({ state: 'visible', timeout: 1000 });
        await link.click({ timeout: 1200, force: true });
        await humanDelay(200, 450);
        try {
          // Workday sometimes moves focus after one tab from the error summary button.
          await page.keyboard.press('Tab');
          await humanDelay(80, 160);
        } catch (_) {}
        let resolvedMode = mode;
        if ((/from|to/i).test(String(errorTextNeedle))) {
          resolvedMode = 'date';
        }
        const ok = await answerFocusedField(page, answerValue, resolvedMode);
        if (ok) {
          if (resolvedMode === 'dropdown') {
            try { await page.keyboard.press('Enter'); } catch (_) {}
          }
          return true;
        }
      } catch (_) {}
    }
  } catch (_) {}
  return false;
}

async function uploadRequiredFileIfPresent(page, filePath, evidenceParts) {
  if (!filePath) return false;
  try {
    const fileInputs = [
      'input[type="file"]',
      '[data-automation-id*="file" i] input[type="file"]',
      '[data-automation-id*="upload" i] input[type="file"]',
    ];

    for (const sel of fileInputs) {
      try {
        const input = page.locator(sel).first();
        await input.waitFor({ state: 'attached', timeout: 1200 });
        await input.setInputFiles(filePath, { timeout: 3000 });
        evidenceParts.push('Uploaded required file via ' + sel);
        return true;
      } catch (_) {}
    }
  } catch (_) {}
  return false;
}

async function isExperienceEditorVisible(page) {
  try {
    const visible = await page.evaluate(() => {
      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };

      const roots = Array.from(document.querySelectorAll('div[role="dialog"], [data-automation-id*="workexperience" i], [data-automation-id*="workExperience" i]'))
        .filter(isVisible);
      const root = roots[0] || document;
      const txt = (root.innerText || '').toLowerCase();
      return txt.includes('job title') && txt.includes('company') && txt.includes('from') && txt.includes('to');
    });
    return !!visible;
  } catch (_) {
    return false;
  }
}

async function openExperienceEditorFromErrorLinks(page) {
  const labels = ['Job Title', 'Company', 'From', 'To'];
  for (const label of labels) {
    const selectors = [
      `[data-automation-id="errorHeading"] button:has-text("${label}")`,
      `button.css-tgkpvs:has-text("${label}")`,
      `button:has-text("Error"):has-text("${label}")`,
      `a:has-text("${label}")`,
      `[role="link"]:has-text("${label}")`,
      `text=/Error-.*${label}.*/i`,
    ];
    for (const sel of selectors) {
      try {
        const link = page.locator(sel).first();
        await link.waitFor({ state: 'visible', timeout: 700 });
        await link.click({ timeout: 1100, force: true });
        await humanDelay(220, 450);
        if (await isExperienceEditorVisible(page)) {
          return true;
        }
      } catch (_) {}
    }
  }
  return false;
}

async function openMyExperienceEditor(page) {
  if (await isExperienceEditorVisible(page)) {
    return true;
  }

  // Section-scoped path: click Add inside the Work Experience section.
  try {
    const sectionClicked = await page.evaluate(() => {
      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };

      const sections = Array.from(document.querySelectorAll('section, div, fieldset')).filter((el) => {
        const txt = (el.innerText || '').toLowerCase();
        return txt.includes('work experience');
      });

      for (const section of sections) {
        const btn = section.querySelector('[data-automation-id="add-button"], button[data-automation-id="add-button"], button');
        if (!btn || !isVisible(btn)) {
          continue;
        }
        const txt = ((btn.textContent || '') + ' ' + (btn.getAttribute('aria-label') || '')).toLowerCase();
        if (txt.includes('add')) {
          btn.click();
          return true;
        }
      }
      return false;
    });

    if (sectionClicked) {
      await humanDelay(320, 650);
      if (await isExperienceEditorVisible(page)) {
        return true;
      }
    }
  } catch (_) {}

  const directSelectors = [
    'button:has-text("Add Work Experience")',
    'button:has-text("Edit Work Experience")',
    '[aria-label*="Add Work Experience" i]',
    '[data-automation-id*="workExperience" i] button:has-text("Add")',
    '[data-automation-id*="workExperience" i] button:has-text("Edit")',
    'button:has-text("Add Experience")',
  ];

  for (const sel of directSelectors) {
    try {
      const btn = page.locator(sel).first();
      await btn.waitFor({ state: 'visible', timeout: 1000 });
      await btn.scrollIntoViewIfNeeded({ timeout: 800 });
      await btn.click({ timeout: 1600, force: true });
      await humanDelay(350, 700);
      if (await isExperienceEditorVisible(page)) {
        return true;
      }
    } catch (_) {}
  }

  // Generic Add button fallback: click Add, then choose Work Experience option.
  const genericAddSelectors = [
    'button:has-text("Add")',
    '[aria-label*="Add" i]',
    '[data-automation-id*="add" i] button',
  ];

  for (const sel of genericAddSelectors) {
    try {
      const btn = page.locator(sel).first();
      await btn.waitFor({ state: 'visible', timeout: 900 });
      await btn.scrollIntoViewIfNeeded({ timeout: 800 });
      await btn.click({ timeout: 1400, force: true });
      await humanDelay(200, 450);

      const menuSelectors = [
        'li[role="menuitem"]:has-text("Work Experience")',
        '[role="option"]:has-text("Work Experience")',
        '[data-automation-id="promptOption"]:has-text("Work Experience")',
        'div[role="button"]:has-text("Work Experience")',
        'button:has-text("Work Experience")',
      ];
      for (const menuSel of menuSelectors) {
        try {
          const opt = page.locator(menuSel).first();
          await opt.waitFor({ state: 'visible', timeout: 900 });
          await opt.click({ timeout: 1300, force: true });
          await humanDelay(300, 600);
          if (await isExperienceEditorVisible(page)) {
            return true;
          }
        } catch (_) {}
      }

      if (await isExperienceEditorVisible(page)) {
        return true;
      }
    } catch (_) {}
  }

  return false;
}

async function commitMyExperienceEditor(page) {
  const saveSelectors = [
    'button:has-text("Save")',
    'button:has-text("Done")',
    'button:has-text("Apply")',
    'button[data-automation-id="wd-CommandButton_uic_okButton"]',
    '[data-automation-id*="save" i] button',
    'div[role="dialog"] button:has-text("Save")',
  ];

  for (const sel of saveSelectors) {
    try {
      const btn = page.locator(sel).first();
      await btn.waitFor({ state: 'visible', timeout: 1200 });
      const disabled = await btn.evaluate((el) => {
        const aria = (el.getAttribute('aria-disabled') || '').toLowerCase();
        return !!(el.disabled || aria === 'true');
      });
      if (disabled) continue;
      await btn.scrollIntoViewIfNeeded({ timeout: 800 });
      await btn.click({ timeout: 1800, force: true });
      await humanDelay(350, 700);
      return true;
    } catch (_) {}
  }
  return false;
}

function normalizeMonthYear(value) {
  const raw = String(value || '').trim();
  if (!raw) return null;

  const m1 = raw.match(/^(\d{1,2})\/(\d{4})$/);
  if (m1) {
    const month = Math.max(1, Math.min(12, parseInt(m1[1], 10)));
    return { month: String(month).padStart(2, '0'), year: m1[2] };
  }

  const m2 = raw.match(/^(\d{4})-(\d{1,2})(?:-\d{1,2})?$/);
  if (m2) {
    const month = Math.max(1, Math.min(12, parseInt(m2[2], 10)));
    return { month: String(month).padStart(2, '0'), year: m2[1] };
  }

  const m3 = raw.match(/^(\d{4})$/);
  if (m3) {
    return { month: '01', year: m3[1] };
  }

  const monthMap = {
    jan: '01', feb: '02', mar: '03', apr: '04', may: '05', jun: '06',
    jul: '07', aug: '08', sep: '09', sept: '09', oct: '10', nov: '11', dec: '12',
  };
  const m4 = raw.toLowerCase().match(/^([a-z]{3,9})\s+(\d{4})$/);
  if (m4) {
    const key = m4[1].slice(0, 4);
    const short = m4[1].slice(0, 3);
    const month = monthMap[key] || monthMap[short] || '01';
    return { month, year: m4[2] };
  }

  return null;
}

async function fillExperienceDateField(page, labelNeedle, value, fieldKey) {
  const normalized = normalizeMonthYear(value);
  if (!normalized) {
    const generic = await answerQuestionByXPathContainer(page, labelNeedle, value, fieldKey, 'text')
      || await answerQuestionByDomFallback(page, labelNeedle, value, fieldKey)
      || await clickErrorLinkAndAnswer(page, labelNeedle, value, 'text');
    return !!generic;
  }

  const didFill = await page.evaluate(({ labelNeedle, month, year }) => {
    const needle = String(labelNeedle || '').toLowerCase();

    const isVisible = (el) => {
      if (!el) return false;
      const rect = el.getBoundingClientRect();
      const style = window.getComputedStyle(el);
      return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
    };

    const setInput = (el, val) => {
      if (!el || !isVisible(el)) return false;
      el.focus();
      el.value = '';
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.value = String(val);
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
      el.dispatchEvent(new Event('blur', { bubbles: true }));
      return true;
    };

    const containers = Array.from(document.querySelectorAll('div, section, fieldset, li, form')).filter((el) => {
      const txt = (el.innerText || '').toLowerCase();
      return txt.includes(needle) && (el.querySelector('input, textarea, select') || null);
    });
    if (!containers.length) {
      return false;
    }

    const container = containers[0];
    const monthInput = container.querySelector('input[data-automation-id*="month" i], input[placeholder*="MM" i], input[aria-label*="month" i]');
    const yearInput = container.querySelector('input[data-automation-id*="year" i], input[placeholder*="YYYY" i], input[aria-label*="year" i]');

    if (monthInput && yearInput) {
      const a = setInput(monthInput, month);
      const b = setInput(yearInput, year);
      return a && b;
    }

    const textInputs = Array.from(container.querySelectorAll('input[type="text"], input:not([type]), input[type="search"]')).filter(isVisible);
    if (textInputs.length >= 2) {
      const a = setInput(textInputs[0], month);
      const b = setInput(textInputs[1], year);
      return a && b;
    }

    if (textInputs.length === 1) {
      return setInput(textInputs[0], `${month}/${year}`);
    }

    return false;
  }, { labelNeedle, month: normalized.month, year: normalized.year });

  if (didFill) {
    return true;
  }

  const generic = await answerQuestionByXPathContainer(page, labelNeedle, `${normalized.month}/${normalized.year}`, fieldKey, 'text')
    || await answerQuestionByDomFallback(page, labelNeedle, `${normalized.month}/${normalized.year}`, fieldKey)
    || await clickErrorLinkAndAnswer(page, labelNeedle, `${normalized.month}/${normalized.year}`, 'text');
  return !!generic;
}

async function fillExperienceDialogNative(page, profile) {
  const toParts = normalizeMonthYear(profile.experience_to || '');
  const fromParts = normalizeMonthYear(profile.experience_from || '');
  const jobTitle = String(profile.experience_job_title || '').trim();
  const company = String(profile.experience_company || '').trim();
  const toRaw = String(profile.experience_to || '').trim();

  try {
    return await page.evaluate(({ jobTitle, company, fromParts, toParts, toRaw }) => {
      const out = [];

      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };

      const setInput = (el, val) => {
        if (!el || !isVisible(el)) return false;
        el.focus();
        el.value = '';
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.value = String(val);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.dispatchEvent(new Event('blur', { bubbles: true }));
        return true;
      };

      const findFieldContainer = (root, needle) => {
        const n = String(needle).toLowerCase();
        const blocks = Array.from(root.querySelectorAll('div, section, fieldset, li')).filter((el) => {
          const txt = (el.innerText || '').toLowerCase();
          return txt.includes(n) && !!el.querySelector('input, textarea, select');
        });
        return blocks[0] || null;
      };

      const dialogs = Array.from(document.querySelectorAll('div[role="dialog"], [data-automation-id*="workexperience" i], [data-automation-id*="workExperience" i]'));
      const root = dialogs.find(isVisible) || document;

      if (jobTitle) {
        const c = findFieldContainer(root, 'job title') || findFieldContainer(root, 'title');
        if (c) {
          const input = c.querySelector('input[type="text"], input:not([type]), textarea');
          if (setInput(input, jobTitle)) out.push('experience_job_title');
        }
      }

      if (company) {
        const c = findFieldContainer(root, 'company');
        if (c) {
          const input = c.querySelector('input[type="text"], input:not([type]), textarea');
          if (setInput(input, company)) out.push('experience_company');
        }
      }

      if (fromParts && fromParts.month && fromParts.year) {
        const c = findFieldContainer(root, 'from');
        if (c) {
          const monthInput = c.querySelector('input[data-automation-id*="month" i], input[placeholder*="MM" i], input[aria-label*="month" i]');
          const yearInput = c.querySelector('input[data-automation-id*="year" i], input[placeholder*="YYYY" i], input[aria-label*="year" i]');
          if (monthInput && yearInput) {
            const a = setInput(monthInput, fromParts.month);
            const b = setInput(yearInput, fromParts.year);
            if (a && b) out.push('experience_from');
          } else {
            const inputs = Array.from(c.querySelectorAll('input[type="text"], input:not([type]), input[type="search"]')).filter(isVisible);
            if (inputs.length >= 2) {
              const a = setInput(inputs[0], fromParts.month);
              const b = setInput(inputs[1], fromParts.year);
              if (a && b) out.push('experience_from');
            } else if (inputs.length === 1 && setInput(inputs[0], `${fromParts.month}/${fromParts.year}`)) {
              out.push('experience_from');
            }
          }
        }
      }

      const present = /present|current/i.test(toRaw);
      if (present) {
        const checks = Array.from(root.querySelectorAll('label, [role="checkbox"], [data-automation-id*="checkbox" i], [data-automation-id*="current" i]')).filter(isVisible);
        const hit = checks.find((el) => /currently work|present/i.test((el.innerText || '').toLowerCase()));
        if (hit) {
          hit.click();
          out.push('experience_to_current');
        }
      }

      if (!present && toParts && toParts.month && toParts.year) {
        const c = findFieldContainer(root, 'to');
        if (c) {
          const monthInput = c.querySelector('input[data-automation-id*="month" i], input[placeholder*="MM" i], input[aria-label*="month" i]');
          const yearInput = c.querySelector('input[data-automation-id*="year" i], input[placeholder*="YYYY" i], input[aria-label*="year" i]');
          if (monthInput && yearInput) {
            const a = setInput(monthInput, toParts.month);
            const b = setInput(yearInput, toParts.year);
            if (a && b) out.push('experience_to');
          } else {
            const inputs = Array.from(c.querySelectorAll('input[type="text"], input:not([type]), input[type="search"]')).filter(isVisible);
            if (inputs.length >= 2) {
              const a = setInput(inputs[0], toParts.month);
              const b = setInput(inputs[1], toParts.year);
              if (a && b) out.push('experience_to');
            } else if (inputs.length === 1 && setInput(inputs[0], `${toParts.month}/${toParts.year}`)) {
              out.push('experience_to');
            }
          }
        }
      }

      return out;
    }, { jobTitle, company, fromParts, toParts, toRaw });
  } catch (_) {
    return [];
  }
}

async function fillWorkExperienceByFieldNames(page, profile) {
  const parsedFrom = normalizeMonthYear(profile.experience_from || '');
  const parsedTo = normalizeMonthYear(profile.experience_to || '');
  const now = new Date();
  const fallbackFrom = { month: '01', year: String(now.getFullYear() - 1) };
  const fallbackTo = { month: String(now.getMonth() + 1).padStart(2, '0'), year: String(now.getFullYear()) };
  const fromParts = parsedFrom || fallbackFrom;
  const toParts = parsedTo || fallbackTo;
  const toRaw = String(profile.experience_to || '').trim();
  const present = /present|current/i.test(toRaw) || (!parsedTo && toRaw === '');
  const jobTitle = String(profile.experience_job_title || '').trim();
  const company = String(profile.experience_company || '').trim();
  const filled = [];

  const valueMatches = (actual, expected) => {
    const a = String(actual || '').trim().toLowerCase();
    const e = String(expected || '').trim().toLowerCase();
    if (!a || !e) return false;
    if (a === e) return true;
    if ((a === '1' || a === '01') && (e === '1' || e === '01')) return true;
    if ((a === '2' || a === '02') && (e === '2' || e === '02')) return true;
    if ((a === '3' || a === '03') && (e === '3' || e === '03')) return true;
    if ((a === '4' || a === '04') && (e === '4' || e === '04')) return true;
    if ((a === '5' || a === '05') && (e === '5' || e === '05')) return true;
    if ((a === '6' || a === '06') && (e === '6' || e === '06')) return true;
    if ((a === '7' || a === '07') && (e === '7' || e === '07')) return true;
    if ((a === '8' || a === '08') && (e === '8' || e === '08')) return true;
    if ((a === '9' || a === '09') && (e === '9' || e === '09')) return true;
    return false;
  };

  const readById = async (id) => {
    try {
      const input = page.locator(`[id="${id}"]`).first();
      await input.waitFor({ state: 'visible', timeout: 700 });
      return await input.inputValue({ timeout: 700 });
    } catch (_) {
      return '';
    }
  };

  const setByNativeSetter = async (id, value) => {
    if (!id || value === undefined || value === null) return false;
    try {
      return await page.evaluate(({ id, value }) => {
        const el = document.getElementById(id);
        if (!el) return false;
        el.focus();
        const proto = Object.getPrototypeOf(el);
        const desc = Object.getOwnPropertyDescriptor(proto, 'value')
          || Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
        if (!desc || typeof desc.set !== 'function') return false;
        desc.set.call(el, String(value));
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        el.dispatchEvent(new Event('blur', { bubbles: true }));
        return true;
      }, { id, value: String(value) });
    } catch (_) {
      return false;
    }
  };

  const setCurrentHere = async (key, desired) => {
    const id = `workExperience-${key}--currentlyWorkHere`;
    try {
      const checked = await page.evaluate((id) => {
        const el = document.getElementById(id);
        return !!(el && el.checked);
      }, id).catch(() => false);

      if (checked === desired) {
        return true;
      }

      const checkbox = page.locator(`[id="${id}"]`).first();
      const label = page.locator(`label[for="${id}"]`).first();
      if (await label.isVisible({ timeout: 300 }).catch(() => false)) {
        await label.click({ timeout: 900, force: true });
      } else {
        await checkbox.click({ timeout: 900, force: true });
      }
      await humanDelay(80, 180);

      const after = await page.evaluate((id) => {
        const el = document.getElementById(id);
        return !!(el && el.checked);
      }, id).catch(() => false);
      return after === desired;
    } catch (_) {
      return false;
    }
  };

  const typeById = async (id, value) => {
    if (!id || !value) return false;
    try {
      const input = page.locator(`[id="${id}"]`).first();
      await input.waitFor({ state: 'visible', timeout: 900 });
      await input.scrollIntoViewIfNeeded({ timeout: 700 });
      await input.click({ timeout: 900, force: true });
      await page.keyboard.press('Control+A').catch(() => {});
      await page.keyboard.press('Backspace').catch(() => {});
      await input.fill('').catch(() => {});
      await input.type(String(value), { delay: 12 }).catch(async () => {
        await page.keyboard.type(String(value), { delay: 12 });
      });
      await input.dispatchEvent('input').catch(() => {});
      await input.dispatchEvent('change').catch(() => {});
      await page.keyboard.press('Tab').catch(() => {});
      await humanDelay(80, 170);

      let after = await readById(id);
      if (!valueMatches(after, value)) {
        await setByNativeSetter(id, value);
        await humanDelay(70, 140);
        after = await readById(id);
      }
      return valueMatches(after, value);
    } catch (_) {
      return false;
    }
  };

  try {
    const rowKeys = await page.evaluate(() => {
      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };
      const inputs = Array.from(document.querySelectorAll('input[name="jobTitle"][id*="workExperience-"]')).filter(isVisible);
      const rows = [];
      for (const el of inputs) {
        const m = String(el.id || '').match(/workExperience-(\d+)--/i);
        if (m) {
          rows.push({ key: m[1], y: el.getBoundingClientRect().y });
        }
      }
      rows.sort((a, b) => a.y - b.y);
      return rows.map((r) => r.key).filter((v, i, a) => a.indexOf(v) === i);
    });

    process.stderr.write(`INFO: Inline workExperience row keys: ${JSON.stringify(rowKeys)}\n`);

    for (const key of rowKeys) {
      const perRow = { key, title: false, company: false, from: false, to: false, current: false };
      if (jobTitle) {
        const ok = await typeById(`workExperience-${key}--jobTitle`, jobTitle);
        perRow.title = ok;
        if (ok) filled.push('experience_job_title');
      }
      if (company) {
        const ok = await typeById(`workExperience-${key}--companyName`, company);
        perRow.company = ok;
        if (ok) filled.push('experience_company');
      }
      if (fromParts && fromParts.month && fromParts.year) {
        const a = await typeById(`workExperience-${key}--startDate-dateSectionMonth-input`, fromParts.month);
        const b = await typeById(`workExperience-${key}--startDate-dateSectionYear-input`, fromParts.year);
        perRow.from = !!(a && b);
        if (perRow.from) filled.push('experience_from');
      }

      if (present) {
        const ok = await setCurrentHere(key, true);
        perRow.current = ok;
        if (ok) filled.push('experience_to_current');
      } else {
        await setCurrentHere(key, false);
      }

      if (!present && toParts && toParts.month && toParts.year) {
        const a = await typeById(`workExperience-${key}--endDate-dateSectionMonth-input`, toParts.month);
        const b = await typeById(`workExperience-${key}--endDate-dateSectionYear-input`, toParts.year);
        perRow.to = !!(a && b);
        if (perRow.to) filled.push('experience_to');
      }

      process.stderr.write(`INFO: Inline row fill result: ${JSON.stringify(perRow)}\n`);
    }
  } catch (_) {}

  return filled;
}

async function cleanupEmptyWorkExperienceRows(page) {
  try {
    const rows = await page.evaluate(() => {
      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };

      const extractRowKey = (id) => {
        const m = String(id || '').match(/workExperience-(\d+)--/i);
        return m ? m[1] : '';
      };

      const titleInputs = Array.from(document.querySelectorAll('input[name="jobTitle"][id*="workExperience-"]')).filter(isVisible);
      const map = {};
      for (const input of titleInputs) {
        const key = extractRowKey(input.id);
        if (!key) continue;
        const company = document.getElementById(`workExperience-${key}--companyName`);
        const titleVal = String(input.value || '').trim();
        const companyVal = String(company?.value || '').trim();
        map[key] = {
          key,
          titleVal,
          companyVal,
          y: input.getBoundingClientRect().y,
        };
      }

      return Object.values(map)
        .sort((a, b) => a.y - b.y)
        .map((r) => ({ key: r.key, empty: r.titleVal === '' && r.companyVal === '' }));
    });

    if (!Array.isArray(rows) || rows.length <= 1) {
      return [];
    }

    const nonEmpty = rows.filter((r) => !r.empty);
    const keepKey = (nonEmpty[0] && nonEmpty[0].key) || (rows[0] && rows[0].key) || '';
    const deleted = [];

    for (const row of rows) {
      if (!row.empty || row.key === keepKey) {
        continue;
      }

      try {
        const rowContainer = page.locator(`[id="workExperience-${row.key}--jobTitle"]`).locator('xpath=ancestor::*[.//button[contains(normalize-space(),"Delete")]][1]').first();
        await rowContainer.waitFor({ state: 'visible', timeout: 900 });
        const delBtn = rowContainer.locator('button:has-text("Delete")').first();
        await delBtn.click({ timeout: 1200, force: true });
        await humanDelay(220, 450);

        // Some Workday variants show confirmation dialogs.
        try {
          const confirm = page.locator('div[role="dialog"] button:has-text("Delete"), div[role="dialog"] button:has-text("Remove"), button:has-text("Delete"):visible').first();
          await confirm.waitFor({ state: 'visible', timeout: 500 });
          await confirm.click({ timeout: 900, force: true });
        } catch (_) {}

        deleted.push(`work_experience_row_${row.key}`);
        await humanDelay(250, 500);
      } catch (_) {}
    }

    return deleted;
  } catch (_) {
    return [];
  }
}

async function forceFillExperienceFields(page, profile) {
  const filled = [];
  const skipped = [];

  const inlineRowCount = await page.locator('input[name="jobTitle"][id*="workExperience-"]').count().catch(() => 0);

  const deletedRows = await cleanupEmptyWorkExperienceRows(page);
  for (const d of deletedRows) {
    if (!filled.includes(d)) {
      filled.push(d);
    }
  }

  const namedFilled = await fillWorkExperienceByFieldNames(page, profile);
  for (const f of namedFilled) {
    if (!filled.includes(f)) {
      filled.push(f);
    }
  }

  if (inlineRowCount === 0) {
    const nativeFilled = await fillExperienceDialogNative(page, profile);
    for (const f of nativeFilled) {
      if (!filled.includes(f)) {
        filled.push(f);
      }
    }
  }

  let jobTitle = (profile.experience_job_title || '').toString().trim();
  if (!jobTitle) {
    try {
      const heading = await detectPageHeading(page);
      const normalized = (heading || '').trim();
      if (normalized && !/my experience|careers|review|application/i.test(normalized)) {
        jobTitle = normalized;
      }
    } catch (_) {}
  }
  if (inlineRowCount === 0 && jobTitle && !filled.includes('experience_job_title')) {
    const solved = await answerQuestionByXPathContainer(page, 'job title', jobTitle, 'experience_job_title', 'text')
      || await answerQuestionByDomFallback(page, 'Job Title', jobTitle, 'experience_job_title')
      || await clickErrorLinkAndAnswer(page, 'Job Title', jobTitle, 'text');
    if (solved) filled.push('experience_job_title');
    else skipped.push('experience_job_title');
  } else {
    skipped.push('experience_job_title');
  }

  const company = (profile.experience_company || '').toString().trim();
  if (inlineRowCount === 0 && company && !filled.includes('experience_company')) {
    const solved = await answerQuestionByXPathContainer(page, 'company', company, 'experience_company', 'text')
      || await answerQuestionByDomFallback(page, 'Company', company, 'experience_company')
      || await clickErrorLinkAndAnswer(page, 'Company', company, 'text');
    if (solved) filled.push('experience_company');
    else skipped.push('experience_company');
  } else {
    skipped.push('experience_company');
  }

  const fromVal = (profile.experience_from || '').toString().trim();
  if (inlineRowCount === 0 && fromVal && !filled.includes('experience_from')) {
    const solved = await fillExperienceDateField(page, 'from', fromVal, 'experience_from');
    if (solved) filled.push('experience_from');
    else skipped.push('experience_from');
  } else {
    skipped.push('experience_from');
  }

  const toVal = (profile.experience_to || '').toString().trim();
  if (inlineRowCount === 0 && toVal && !filled.includes('experience_to') && !filled.includes('experience_to_current')) {
    const isPresent = /present|current/i.test(toVal);
    let solved = false;
    if (isPresent) {
      solved = await answerQuestionByXPathContainer(page, 'currently work', 'Yes', 'experience_to_current', 'radio')
        || await answerQuestionByDomFallback(page, 'I currently work here', 'Yes', 'experience_to_current')
        || await clickErrorLinkAndAnswer(page, 'I currently work here', 'Yes', 'radio');
      if (solved) {
        filled.push('experience_to_current');
      }
    }
    if (!solved) {
      solved = await fillExperienceDateField(page, 'to', toVal, 'experience_to');
      if (solved) filled.push('experience_to');
      else skipped.push('experience_to');
    }
  } else {
    skipped.push('experience_to');
  }

  return { filled, skipped };
}

async function validateStepWithPlaywright(page, stepKey) {
  if (stepKey !== 'my_experience') {
    return { ok: true, issues: [] };
  }

  try {
    const details = await page.evaluate(() => {
      const out = { issues: [], rows: [] };
      const extractKey = (id) => {
        const m = String(id || '').match(/workExperience-(\d+)--/i);
        return m ? m[1] : '';
      };

      const visible = (el) => {
        if (!el) return false;
        const r = el.getBoundingClientRect();
        const s = window.getComputedStyle(el);
        return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none';
      };

      const titleInputs = Array.from(document.querySelectorAll('input[name="jobTitle"][id*="workExperience-"]')).filter(visible);
      const keys = [];
      for (const input of titleInputs) {
        const key = extractKey(input.id);
        if (key && !keys.includes(key)) keys.push(key);
      }

      if (!keys.length) {
        out.issues.push('no_work_experience_rows_detected');
      }

      for (const key of keys) {
        const job = document.getElementById(`workExperience-${key}--jobTitle`);
        const company = document.getElementById(`workExperience-${key}--companyName`);
        const startM = document.getElementById(`workExperience-${key}--startDate-dateSectionMonth-input`);
        const startY = document.getElementById(`workExperience-${key}--startDate-dateSectionYear-input`);
        const endM = document.getElementById(`workExperience-${key}--endDate-dateSectionMonth-input`);
        const endY = document.getElementById(`workExperience-${key}--endDate-dateSectionYear-input`);
        const current = document.getElementById(`workExperience-${key}--currentlyWorkHere`);

        const jobVal = String(job?.value || '').trim();
        const companyVal = String(company?.value || '').trim();
        const startMonthVal = String(startM?.value || '').trim();
        const startYearVal = String(startY?.value || '').trim();
        const endMonthVal = String(endM?.value || '').trim();
        const endYearVal = String(endY?.value || '').trim();
        const currentChecked = !!current?.checked;

        out.rows.push({ key, jobVal, companyVal, startMonthVal, startYearVal, endMonthVal, endYearVal, currentChecked });

        if (!jobVal) out.issues.push(`row_${key}_job_title_empty`);
        if (!companyVal) out.issues.push(`row_${key}_company_empty`);
        if (!startMonthVal || !startYearVal) out.issues.push(`row_${key}_from_empty`);
        if (!currentChecked && (!endMonthVal || !endYearVal)) out.issues.push(`row_${key}_to_empty`);
      }

      const errorButtons = Array.from(document.querySelectorAll('[data-automation-id="errorHeading"] button, button.css-tgkpvs')).filter(visible);
      for (const btn of errorButtons) {
        const t = (btn.textContent || '').toLowerCase();
        if (t.includes('job title') || t.includes('company') || t.includes('from') || t.includes('to')) {
          out.issues.push('experience_error_summary_present');
          break;
        }
      }

      return out;
    });

    const issues = Array.isArray(details.issues) ? details.issues : [];
    return { ok: issues.length === 0, issues, rows: details.rows || [] };
  } catch (_) {
    return { ok: false, issues: ['experience_validation_check_failed'] };
  }
}

async function captureExperienceFieldSnapshot(page) {
  try {
    const snap = await page.evaluate(() => {
      const out = {};
      const labels = [
        { key: 'job_title', needle: 'job title' },
        { key: 'company', needle: 'company' },
        { key: 'from', needle: 'from' },
        { key: 'to', needle: 'to' },
      ];

      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };

      const roots = Array.from(document.querySelectorAll('div[role="dialog"], [data-automation-id*="workexperience" i], [data-automation-id*="workExperience" i]'));
      const root = roots.find(isVisible) || document;

      for (const l of labels) {
        const blocks = Array.from(root.querySelectorAll('div, section, fieldset, li')).filter((el) => {
          const txt = (el.innerText || '').toLowerCase();
          return txt.includes(l.needle) && !!el.querySelector('input, textarea, select');
        });
        const b = blocks[0] || null;
        if (!b) {
          out[l.key] = 'MISSING_CONTAINER';
          continue;
        }
        const vals = Array.from(b.querySelectorAll('input, textarea, select'))
          .filter(isVisible)
          .map((el) => {
            const tag = (el.tagName || '').toLowerCase();
            let v = '';
            if (tag === 'select') {
              v = (el.options && el.selectedIndex >= 0 && el.options[el.selectedIndex]) ? (el.options[el.selectedIndex].text || '') : '';
            } else {
              v = el.value || '';
            }
            return String(v).trim();
          })
          .filter((v) => v.length > 0);
        out[l.key] = vals.length ? vals.join('/') : 'EMPTY';
      }

      const fileInput = root.querySelector('input[type="file"]') || document.querySelector('input[type="file"]');
      out.upload_present = !!fileInput;
      return out;
    });
    return snap || null;
  } catch (_) {
    return null;
  }
}

async function captureExperienceActionSnapshot(page) {
  try {
    const data = await page.evaluate(() => {
      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };

      const controls = Array.from(document.querySelectorAll('button, a, [role="button"], [role="link"], div[role="button"]'))
        .filter(isVisible)
        .map((el) => {
          const txt = ((el.textContent || '') + ' ' + (el.getAttribute('aria-label') || '')).replace(/\s+/g, ' ').trim();
          const aid = el.getAttribute('data-automation-id') || '';
          return { txt, aid };
        })
        .filter((x) => x.txt.length > 0)
        .filter((x) => {
          const t = x.txt.toLowerCase();
          const a = x.aid.toLowerCase();
          return t.includes('experience') || t.includes('add') || t.includes('job title') || t.includes('company') || t.includes('from') || t.includes('to') || a.includes('experience') || a.includes('add');
        })
        .slice(0, 30);

      return controls;
    });
    return Array.isArray(data) ? data : [];
  } catch (_) {
    return [];
  }
}

async function writeExperienceDebugDump(page, screenshotDir, applicationId, evidenceParts) {
  try {
    const dump = await page.evaluate(() => {
      const isVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const style = window.getComputedStyle(el);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      };

      const toNode = (el) => {
        if (!el) return null;
        const rect = el.getBoundingClientRect();
        const txt = ((el.textContent || '') + ' ' + (el.getAttribute('aria-label') || '')).replace(/\s+/g, ' ').trim();
        const outer = (el.outerHTML || '').slice(0, 2000);
        return {
          tag: (el.tagName || '').toLowerCase(),
          aid: el.getAttribute('data-automation-id') || '',
          role: el.getAttribute('role') || '',
          id: el.id || '',
          className: el.className || '',
          text: txt.slice(0, 250),
          rect: { x: Math.round(rect.x), y: Math.round(rect.y), w: Math.round(rect.width), h: Math.round(rect.height) },
          outerHTML: outer,
        };
      };

      const errors = Array.from(document.querySelectorAll('a, [role="link"], div, span'))
        .filter(isVisible)
        .filter((el) => /error-(job title|company|from|to)/i.test((el.textContent || '').trim()))
        .slice(0, 20)
        .map((el) => toNode(el));

      const addButtons = Array.from(document.querySelectorAll('[data-automation-id="add-button"], button, [role="button"], div[role="button"]'))
        .filter(isVisible)
        .filter((el) => {
          const txt = ((el.textContent || '') + ' ' + (el.getAttribute('aria-label') || '')).toLowerCase();
          const aid = (el.getAttribute('data-automation-id') || '').toLowerCase();
          return aid.includes('add-button') || txt.includes('add');
        })
        .slice(0, 30)
        .map((el) => {
          const parent = el.closest('section, div, fieldset, li, form');
          const trail = [];
          let cur = el;
          for (let i = 0; i < 6 && cur; i++) {
            const txt = (cur.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 120);
            trail.push({
              tag: (cur.tagName || '').toLowerCase(),
              aid: cur.getAttribute?.('data-automation-id') || '',
              role: cur.getAttribute?.('role') || '',
              cls: cur.className || '',
              text: txt,
            });
            cur = cur.parentElement;
          }

          let heading = '';
          try {
            let p = el.parentElement;
            while (p && !heading) {
              const h = p.querySelector('h1, h2, h3, h4, [data-automation-id*="section" i], label');
              if (h) {
                heading = (h.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 150);
              }
              p = p.parentElement;
            }
          } catch (_) {}

          const node = toNode(el);
          node.parentText = ((parent?.innerText || '') + '').replace(/\s+/g, ' ').trim().slice(0, 450);
          node.parentAid = parent?.getAttribute?.('data-automation-id') || '';
          node.nearHeading = heading;
          node.ancestorTrail = trail;
          return node;
        });

      const dialogs = Array.from(document.querySelectorAll('div[role="dialog"], [data-automation-id*="workexperience" i], [data-automation-id*="workExperience" i]'))
        .filter(isVisible)
        .slice(0, 8)
        .map((el) => toNode(el));

      const workGroups = Array.from(document.querySelectorAll('[data-automation-id="applyFlowMyExpPage"] [role="group"], [data-automation-id="applyFlowMyExpPage"], [role="group"]'))
        .filter(isVisible)
        .filter((el) => ((el.innerText || '').toLowerCase().includes('work experience')))
        .slice(0, 3);

      const workExpInputs = [];
      for (const group of workGroups) {
        const inputs = Array.from(group.querySelectorAll('input, textarea, select, [role="combobox"], button'))
          .filter(isVisible)
          .slice(0, 60)
          .map((el) => ({
            tag: (el.tagName || '').toLowerCase(),
            aid: el.getAttribute('data-automation-id') || '',
            role: el.getAttribute('role') || '',
            name: el.getAttribute('name') || '',
            id: el.id || '',
            ariaLabel: el.getAttribute('aria-label') || '',
            ariaValueText: el.getAttribute('aria-valuetext') || '',
            ariaValueNow: el.getAttribute('aria-valuenow') || '',
            placeholder: el.getAttribute('placeholder') || '',
            text: ((el.textContent || '') + '').replace(/\s+/g, ' ').trim().slice(0, 120),
            value: ('value' in el ? String(el.value || '') : '').slice(0, 120),
            checked: ('checked' in el) ? !!el.checked : null,
            disabled: ('disabled' in el) ? !!el.disabled : null,
            readOnly: ('readOnly' in el) ? !!el.readOnly : null,
          }));
        workExpInputs.push({
          heading: ((group.querySelector('h1,h2,h3,h4,label,[data-automation-id*="section" i]')?.textContent || '') + '').replace(/\s+/g, ' ').trim().slice(0, 120),
          inputs,
        });
      }

      return {
        url: location.href,
        title: document.title,
        timestamp: new Date().toISOString(),
        errors,
        addButtons,
        dialogs,
        workExpInputs,
      };
    });

    const stamp = Date.now();
    const fileName = `wd_exp_debug_${applicationId || 'na'}_${stamp}.json`;
    const targetDir = (screenshotDir && fs.existsSync(screenshotDir) && fs.statSync(screenshotDir).isDirectory())
      ? screenshotDir
      : '/tmp';
    const filePath = path.join(targetDir, fileName);
    fs.writeFileSync(filePath, JSON.stringify(dump, null, 2), 'utf8');
    evidenceParts.push(`Experience debug dump: ${filePath}`);
    return filePath;
  } catch (e) {
    try {
      const fallbackPath = path.join('/tmp', `wd_exp_debug_${applicationId || 'na'}_${Date.now()}_fallback_error.txt`);
      fs.writeFileSync(fallbackPath, String(e && e.message ? e.message : e), 'utf8');
      evidenceParts.push(`Experience debug dump failed: ${fallbackPath}`);
    } catch (_) {}
    return '';
  }
}

async function run() {
  const result = {
    ok: false,
    target_step: target_step,
    detected_page: '',
    page_matched: false,
    fields_filled: [],
    fields_skipped: [],
    continue_clicked: false,
    post_continue_url: '',
    page_title: '',
    needs_manual_review: false,
    evidence: '',
    screenshots: [],
    error: '',
  };

  let browser;
  try {
    const launchOpts = { headless: true };
    if (EXEC_PATH) {
      launchOpts.executablePath = EXEC_PATH;
    }

    const launched = await launchBrowser(launchOpts);
    browser = launched.browser;
    const page = launched.page;
    const urls = parseWorkdayUrls(apply_url);
    const evidenceParts = [];

    // ── Step A: Log in to Workday ──────────────────────────────────────────
    process.stderr.write(`INFO: [${target_step}] Logging in to Workday...\n`);
    await page.goto(urls.signInUrl, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    await humanDelay(1500, 3000);

    const emailSelector    = '[data-automation-id="email"]';
    const passwordSelector = '[data-automation-id="password"]';
    const signInButton     = '[data-automation-id="click_filter"]';

    // Click "Sign In" link if needed.
    const signInLink = page.locator('a[data-automation-id="signInLink"], a:has-text("Sign In"), button:has-text("Sign In")');
    try {
      await signInLink.first().click({ timeout: 5000 });
      await humanDelay(1000, 2000);
    } catch (_) {}

    // Wait for email field.
    try {
      await page.waitForSelector(emailSelector, { timeout: 15000 });
    } catch (_) {
      try {
        await page.waitForSelector('input[type="email"], input[name="email"]', { timeout: 5000 });
      } catch (e2) {
        result.error = 'Login form not found.';
        writeResult(result);
        return;
      }
    }

    // Fill credentials.
    try {
      await humanType(page, emailSelector, username);
    } catch (_) {
      await humanType(page, 'input[type="email"], input[name="email"]', username);
    }
    await humanDelay(300, 800);

    try {
      await humanType(page, passwordSelector, password);
    } catch (_) {
      await humanType(page, 'input[type="password"]', password);
    }
    await humanDelay(500, 1000);

    // Submit login.
    try {
      await page.click(signInButton, { timeout: 5000 });
    } catch (_) {
      try {
        await page.click('button[type="submit"], [data-automation-id="signInSubmitButton"]', { timeout: 3000 });
      } catch (_2) {
        await page.keyboard.press('Enter');
      }
    }
    await humanDelay(3000, 5000);

    // Check for login errors.
    const errorBanner = page.locator('[data-automation-id="errorMessage"], .error-message, [role="alert"]');
    try {
      const errorText = await errorBanner.first().textContent({ timeout: 3000 });
      if (errorText && errorText.trim().length > 0) {
        result.error = 'Login failed: ' + errorText.trim();
        writeResult(result);
        return;
      }
    } catch (_) {}

    evidenceParts.push('Login OK');
    const ssLogin = await takeScreenshot(page, screenshot_dir, application_id, `wd_${target_step}_login`);
    if (ssLogin) result.screenshots.push(ssLogin);

    // ── Step B: Navigate to the apply URL ──────────────────────────────────
    // Workday should restore wizard position when we navigate back to the apply URL.
    process.stderr.write(`INFO: [${target_step}] Navigating to apply URL: ${apply_url}\n`);
    await page.goto(apply_url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });
    await humanDelay(3000, 5000);

    if (target_step === 'wizard_auto' || target_step === 'wizard_validate') {
      const strictValidation = target_step === 'wizard_validate';
      const startIndex = AUTO_STEP_ORDER.indexOf(start_step);
      const stepsToRun = AUTO_STEP_ORDER.slice(startIndex >= 0 ? startIndex : 0);
      const stepResults = {};
      let lastUrl = page.url();
      let failedStep = '';

      for (const stepKey of stepsToRun) {
        const stepResult = {
          status: 'not_started',
          detected_page: '',
          page_title: '',
          post_continue_url: '',
          fields_filled: [],
          fields_skipped: [],
          needs_manual_review: false,
          error: '',
        };

        process.stderr.write(`INFO: [wizard_auto] Running ${stepKey}...\n`);
        await humanDelay(500, 1100);

        try {
          const detectedPage = await detectPageHeading(page);
          stepResult.detected_page = detectedPage;
          evidenceParts.push(`${stepKey}: detected page "${detectedPage}"`);
        } catch (_) {}

        const local = {
          fields_filled: [],
          fields_skipped: [],
          needs_manual_review: false,
        };

        try {
          await STEP_HANDLERS[stepKey](page, profile_data, local);
          stepResult.fields_filled = local.fields_filled || [];
          stepResult.fields_skipped = local.fields_skipped || [];
          stepResult.needs_manual_review = !!local.needs_manual_review;

          if (strictValidation && stepKey === 'my_experience') {
            const check1 = await validateStepWithPlaywright(page, stepKey);
            if (!check1.ok) {
              const corrected = await forceFillExperienceFields(page, profile_data);
              for (const f of corrected.filled || []) {
                if (!stepResult.fields_filled.includes(f)) {
                  stepResult.fields_filled.push(f);
                }
              }
              if ((corrected.filled || []).length > 0) {
                await commitMyExperienceEditor(page);
              }
              const check2 = await validateStepWithPlaywright(page, stepKey);
              if (!check2.ok) {
                stepResult.error = 'Strict step validation failed: ' + check2.issues.join(', ');
                stepResult.status = 'failed';
                stepResults[stepKey] = stepResult;
                failedStep = stepKey;
                break;
              }
            }
          }
        } catch (e) {
          stepResult.error = 'Step handler failed: ' + e.message;
          stepResult.status = 'failed';
          stepResults[stepKey] = stepResult;
          failedStep = stepKey;
          break;
        }

        const preClickUrl = page.url();
        let clicked = false;
        if (stepKey === 'review_submit') {
          clicked = await clickSubmitButton(page, evidenceParts);
        } else {
          clicked = await clickContinueButton(page, evidenceParts);
        }

        await humanDelay(1400, 2600);
        stepResult.post_continue_url = page.url();
        stepResult.page_title = await page.title();
        if (stepResult.post_continue_url) {
          lastUrl = stepResult.post_continue_url;
        }

        try {
          const validationErrors = page.locator('[data-automation-id="errorMessage"], [data-automation-id="inlineError"], .error-message-text, [data-automation-id*="error" i]');
          const errorCount = await validationErrors.count();
          const noAdvanceYet = stepResult.post_continue_url === preClickUrl;
          if (errorCount > 0 && noAdvanceYet) {
            const firstError = await validationErrors.first().textContent({ timeout: 1000 }).catch(() => '');
            if (stepKey === 'my_experience') {
              const actions = await captureExperienceActionSnapshot(page);
              if (actions.length > 0) {
                evidenceParts.push('Experience actions snapshot: ' + JSON.stringify(actions));
              }
              await writeExperienceDebugDump(page, screenshot_dir, application_id, evidenceParts);
            }

            const resolvedFields = await resolveValidationErrorsFromProfile(page, profile_data, evidenceParts, resume_pdf_path);
            if (resolvedFields.length > 0) {
              for (const fieldName of resolvedFields) {
                if (!stepResult.fields_filled.includes(fieldName)) {
                  stepResult.fields_filled.push(fieldName);
                }
              }

              if (stepKey === 'my_experience') {
                const expSnap = await captureExperienceFieldSnapshot(page);
                if (expSnap) {
                  evidenceParts.push('Experience snapshot after resolver: ' + JSON.stringify(expSnap));
                }
                  await writeExperienceDebugDump(page, screenshot_dir, application_id, evidenceParts);
              }

              const retryPreUrl = page.url();
              let retryClicked = false;
              if (stepKey === 'review_submit') {
                retryClicked = await clickSubmitButton(page, evidenceParts);
              } else {
                retryClicked = await clickContinueButton(page, evidenceParts);
              }

              await humanDelay(1200, 2200);
              stepResult.post_continue_url = page.url();
              stepResult.page_title = await page.title();
              if (stepResult.post_continue_url) {
                lastUrl = stepResult.post_continue_url;
              }

              clicked = !!retryClicked && stepResult.post_continue_url !== retryPreUrl;
              if (!clicked) {
                stepResult.error = 'Validation blocked step progression after resolver: ' + String(firstError || '').trim();
              }
            } else {
              clicked = false;
              stepResult.error = 'Validation blocked step progression: ' + String(firstError || '').trim();
            }
          }
        } catch (_) {}

        if (!clicked) {
          const visibleActions = await getVisibleActionLabels(page);
          stepResult.status = 'failed';
          if (!stepResult.error) {
            stepResult.error = stepKey === 'review_submit'
              ? 'Could not locate Submit control in single-session flow. Visible actions: ' + (visibleActions.join(' | ') || 'none')
              : 'Could not locate Continue/Next control in single-session flow. Visible actions: ' + (visibleActions.join(' | ') || 'none');
          }
          stepResults[stepKey] = stepResult;
          failedStep = stepKey;
          break;
        }

        stepResult.status = 'pass';
        stepResults[stepKey] = stepResult;

        const ssStep = await takeScreenshot(page, screenshot_dir, application_id, `wd_wizard_auto_${stepKey}`);
        if (ssStep) result.screenshots.push(ssStep);
      }

      const completedSteps = Object.keys(stepResults).filter((k) => stepResults[k].status === 'pass');
      const reviewSubmitPass = stepResults.review_submit && stepResults.review_submit.status === 'pass';
      result.ok = !!reviewSubmitPass;
      result.target_step = 'wizard_auto';
      result.fields_filled = [];
      result.fields_skipped = [];
      result.continue_clicked = completedSteps.length > 0;
      result.post_continue_url = lastUrl;
      result.page_title = await page.title();
      result.detected_page = await detectPageHeading(page);
      result.page_matched = !!reviewSubmitPass;
      result.needs_manual_review = Object.values(stepResults).some((s) => !!s.needs_manual_review);
      result.step_results = stepResults;
      result.completed_steps = completedSteps;
      result.error = failedStep ? ((stepResults[failedStep] && stepResults[failedStep].error) || `Failed at ${failedStep}`) : '';
      evidenceParts.push(`wizard_auto completed: [${completedSteps.join(', ')}]`);
      result.evidence = evidenceParts.join(' | ');

      payload.username = '';
      payload.password = '';
      writeResult(result);
      return;
    }

    // ── Step C: Detect which page we're on ─────────────────────────────────
    process.stderr.write(`INFO: [${target_step}] Detecting current wizard page...\n`);

    let detectedPage = '';

    // Workday wizard pages have step indicators and headings.
    // Primary: look for the page heading using data-automation-id.
    const headingSelectors = [
      '[data-automation-id="pageHeaderTitle"]',
      '[data-automation-id="stepTitle"]',
      'h2[data-automation-id]',
      '.current-step-title',
    ];

    for (const sel of headingSelectors) {
      try {
        const heading = page.locator(sel).first();
        const text = await heading.textContent({ timeout: 5000 });
        if (text && text.trim()) {
          detectedPage = text.trim();
          process.stderr.write(`INFO: Detected page heading: "${detectedPage}" via ${sel}\n`);
          break;
        }
      } catch (_) {}
    }

    // Fallback: look for step progress indicators.
    if (!detectedPage) {
      try {
        const stepIndicator = page.locator('[data-automation-id="activeStep"], .active-step, [aria-current="step"]').first();
        const stepText = await stepIndicator.textContent({ timeout: 5000 });
        detectedPage = (stepText || '').trim();
        process.stderr.write(`INFO: Detected page from step indicator: "${detectedPage}"\n`);
      } catch (_) {}
    }

    // Fallback: check the page title.
    if (!detectedPage) {
      detectedPage = await page.title();
      process.stderr.write(`INFO: Using page title as fallback: "${detectedPage}"\n`);
    }

    result.detected_page = detectedPage;
    evidenceParts.push(`Detected page: "${detectedPage}"`);

    // Check if detected page matches the target step.
    const expectedHeadings = STEP_PAGE_HEADINGS[target_step] || [];
    const pageMatchesTarget = expectedHeadings.some(h =>
      detectedPage.toLowerCase().includes(h.toLowerCase())
    );

    result.page_matched = pageMatchesTarget;

    if (pageMatchesTarget) {
      process.stderr.write(`INFO: Page matches target step "${target_step}". Proceeding.\n`);
      evidenceParts.push(`Page matches target step: ${target_step}`);
    } else {
      process.stderr.write(`WARN: Page "${detectedPage}" may not match target "${target_step}". Attempting anyway.\n`);
      evidenceParts.push(`Page may not match target — detected "${detectedPage}", expected one of: ${expectedHeadings.join(', ')}`);
    }

    const ssPage = await takeScreenshot(page, screenshot_dir, application_id, `wd_${target_step}_page`);
    if (ssPage) result.screenshots.push(ssPage);

    // ── Step D: Execute the step-specific handler ──────────────────────────
    process.stderr.write(`INFO: [${target_step}] Running step handler...\n`);
    const handler = STEP_HANDLERS[target_step];
    await handler(page, profile_data, result);

    evidenceParts.push(`Fields filled: [${result.fields_filled.join(', ')}]`);
    if (result.fields_skipped.length > 0) {
      evidenceParts.push(`Fields skipped: [${result.fields_skipped.join(', ')}]`);
    }

    const ssAfterFill = await takeScreenshot(page, screenshot_dir, application_id, `wd_${target_step}_filled`);
    if (ssAfterFill) result.screenshots.push(ssAfterFill);

    // ── Step E: Click Continue / Submit ─────────────────────────────────────
    // For application_questions with needs_manual_review, still try to continue
    // but the step may need manual intervention.
    if (target_step === 'review_submit') {
      // On/near review page, click Submit; if not yet on review, advance through Continue.
      process.stderr.write('INFO: Looking for Submit button on review page...\n');
      const submitSelectors = [
        'button[data-automation-id="submitButton"]',
        'button[data-automation-id="bottom-navigation-next-button"]:has-text("Submit")',
        'button:has-text("Submit Application")',
        'button:has-text("Review and Submit")',
        'button:has-text("Submit")',
        '[role="button"][aria-label*="Submit" i]',
        'input[type="submit"]',
      ];
      const continueSelectors = [
        'button[data-automation-id="bottom-navigation-next-button"]',
        '[data-automation-id="bottom-navigation"] button:has-text("Continue")',
        '[data-automation-id="bottom-navigation"] button:has-text("Next")',
        '[data-automation-id="bottom-navigation"] button:has-text("Save and Continue")',
        'button[data-automation-id="nextButton"]',
        'button[aria-label*="Continue" i]',
      ];

      const clickFirstEnabled = async (selectors, label) => {
        for (const sel of selectors) {
          try {
            const btn = page.locator(sel).first();
            await btn.waitFor({ state: 'visible', timeout: 1200 });
            await btn.scrollIntoViewIfNeeded({ timeout: 1000 });
            const disabled = await btn.evaluate((el) => {
              const aria = (el.getAttribute('aria-disabled') || '').toLowerCase();
              return !!(el.disabled || aria === 'true');
            });
            if (disabled) {
              continue;
            }
            await humanDelay(150, 350);
            try {
              await btn.click({ timeout: 2500 });
            } catch (_) {
              await btn.click({ timeout: 2500, force: true });
            }
            process.stderr.write(`INFO: Clicked ${label} via: ${sel}\n`);
            return true;
          } catch (_) {
            continue;
          }
        }
        return false;
      };

      const hasSubmittedConfirmation = async () => {
        try {
          const confirmationLocators = [
            page.locator('text=/thank you for applying/i').first(),
            page.locator('text=/application submitted/i').first(),
            page.locator('[data-automation-id="applicationConfirmation"]').first(),
          ];
          for (const loc of confirmationLocators) {
            try {
              if (await loc.isVisible({ timeout: 1000 })) {
                return true;
              }
            } catch (_) {}
          }
        } catch (_) {}
        const u = page.url().toLowerCase();
        return u.includes('submitted') || u.includes('confirmation');
      };

      const currentUrlLower = page.url().toLowerCase();
      if (currentUrlLower.includes('/job/') && !currentUrlLower.includes('/apply/')) {
        process.stderr.write('INFO: Detected job detail page; attempting to enter apply flow...\n');
        const applyEntrySelectors = [
          'a[href*="/apply/"]',
          'a[href*="autofillWithResume"]',
          'a[href*="/apply"]:has-text("Apply")',
          'a:has-text("Apply Now")',
          'button:has-text("Apply")',
          'a:has-text("Apply")',
          'button[data-automation-id*="apply" i]',
          'a[data-automation-id*="apply" i]',
          'button[data-automation-id="applyButton"]',
          'a[data-automation-id="applyButton"]',
        ];
        const enteredApplyFlow = await clickFirstEnabled(applyEntrySelectors, 'Apply Entry');
        if (!enteredApplyFlow) {
          try {
            const clicked = await page.evaluate(() => {
              const candidates = Array.from(document.querySelectorAll('a, button, [role="button"]'));
              const match = candidates.find((el) => {
                const txt = ((el.textContent || '') + ' ' + (el.getAttribute('aria-label') || '')).toLowerCase();
                const href = (el.getAttribute('href') || '').toLowerCase();
                const disabled = el.disabled || (el.getAttribute('aria-disabled') || '').toLowerCase() === 'true';
                return !disabled && (href.includes('/apply') || href.includes('autofillwithresume') || /apply now|apply/.test(txt));
              });
              if (match) {
                match.scrollIntoView({ behavior: 'instant', block: 'center' });
                match.click();
                return true;
              }
              return false;
            });
            if (clicked) {
              process.stderr.write('INFO: Entered apply flow via DOM fallback.\n');
            }
          } catch (_) {}
        }
        await humanDelay(1200, 2200);
      }

      for (let attempt = 0; attempt < 10 && !result.continue_clicked; attempt++) {
        try {
          await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
          await humanDelay(200, 500);
        } catch (_) {}

        await handleReviewSubmit(page, profile_data, result);

        if (await clickFirstEnabled(submitSelectors, 'Submit')) {
          result.continue_clicked = true;
          evidenceParts.push('Clicked Submit');
          await humanDelay(3000, 5000);
          if (await hasSubmittedConfirmation()) {
            evidenceParts.push('Submission confirmation detected');
          }
          break;
        }

        if (await hasSubmittedConfirmation()) {
          result.continue_clicked = true;
          evidenceParts.push('Submission confirmation detected without explicit click');
          break;
        }

        const advanced = await clickFirstEnabled(continueSelectors, 'Continue');
        if (!advanced) {
          break;
        }
        evidenceParts.push('Advanced wizard while seeking Submit');
        await humanDelay(1300, 2200);
      }

      if (!result.continue_clicked) {
        let availableActions = [];
        try {
          availableActions = await page.evaluate(() => {
            const isVisible = (el) => {
              const rect = el.getBoundingClientRect();
              const style = window.getComputedStyle(el);
              return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
            };
            const nodes = Array.from(document.querySelectorAll('button, a, [role="button"], input[type="submit"], input[type="button"]'));
            return nodes
              .filter((el) => isVisible(el))
              .map((el) => ((el.textContent || '') + ' ' + (el.getAttribute('value') || '') + ' ' + (el.getAttribute('aria-label') || '')).trim())
              .map((t) => t.replace(/\s+/g, ' ').trim())
              .filter((t) => t.length > 0)
              .slice(0, 20);
          });
        } catch (_) {}
        result.error = 'Could not locate a Workday Submit action from the current application flow state. Available actions: ' + (availableActions.join(' | ') || 'none');
        try {
          const clicked = await page.evaluate(() => {
            const candidates = Array.from(document.querySelectorAll('button, [role="button"], input[type="submit"], input[type="button"], a'));
            const match = candidates.find((el) => {
              const txt = ((el.textContent || '') + ' ' + (el.getAttribute('value') || '') + ' ' + (el.getAttribute('aria-label') || '')).toLowerCase();
              const disabled = el.disabled || (el.getAttribute('aria-disabled') || '').toLowerCase() === 'true';
              return !disabled && /submit application|review and submit|submit/.test(txt);
            });
            if (match) {
              match.scrollIntoView({ behavior: 'instant', block: 'center' });
              match.click();
              return true;
            }
            return false;
          });
          if (clicked) {
            result.continue_clicked = true;
            process.stderr.write('INFO: Clicked Submit via DOM evaluate fallback.\n');
            evidenceParts.push('Clicked Submit (DOM fallback)');
          }
        } catch (_) {}
      }
    } else {
      // Standard Continue/Next button.
      process.stderr.write('INFO: Looking for Continue/Next button...\n');
      await humanDelay(500, 1000);

      const continueSelectors = [
        'button[data-automation-id="bottom-navigation-next-button"]',
        'button:has-text("Continue")',
        'button:has-text("Next")',
        'button:has-text("Save and Continue")',
        '[data-automation-id="nextButton"]',
      ];

      for (const sel of continueSelectors) {
        try {
          const btn = page.locator(sel).first();
          await btn.waitFor({ state: 'visible', timeout: 5000 });
          await humanDelay(500, 1000);
          await btn.click({ timeout: 5000 });
          result.continue_clicked = true;
          process.stderr.write(`INFO: Clicked Continue via: ${sel}\n`);
          evidenceParts.push('Clicked Continue');
          break;
        } catch (_) {
          continue;
        }
      }
    }

    if (!result.continue_clicked) {
      process.stderr.write('WARN: Could not find Continue/Submit button.\n');
      evidenceParts.push('Continue/Submit button NOT found');
    }

    await humanDelay(3000, 5000);

    // ── Step F: Capture post-action state ──────────────────────────────────
    const postUrl = page.url();
    const postTitle = await page.title();
    result.post_continue_url = postUrl;
    result.page_title = postTitle;
    evidenceParts.push(`Post-action URL: ${postUrl}`);
    evidenceParts.push(`Post-action title: "${postTitle}"`);

    const ssPost = await takeScreenshot(page, screenshot_dir, application_id, `wd_${target_step}_done`);
    if (ssPost) result.screenshots.push(ssPost);

    // Check for validation errors on the page.
    try {
      const validationErrors = page.locator('[data-automation-id="errorMessage"], [data-automation-id="inlineError"], .error-message-text');
      const errorCount = await validationErrors.count();
      if (errorCount > 0) {
        const firstError = await validationErrors.first().textContent({ timeout: 3000 });
        evidenceParts.push(`Validation error(s): ${errorCount}. First: "${(firstError || '').trim()}"`);
        process.stderr.write(`WARN: Found ${errorCount} validation error(s) on page.\n`);
        // If there are errors after clicking Continue, we didn't actually advance.
        // Check if URL changed.
        if (postUrl === apply_url || postUrl === page.url()) {
          result.continue_clicked = false;
          evidenceParts.push('Page did not advance (validation errors)');
        }
      }
    } catch (_) {}

    // ── Final result ───────────────────────────────────────────────────────
    result.ok = result.continue_clicked;
    result.evidence = evidenceParts.join(' | ');

    // Zero credentials.
    payload.username = '';
    payload.password = '';

    writeResult(result);
  } catch (e) {
    writeResult({
      ok: false,
      target_step: target_step,
      detected_page: '',
      page_matched: false,
      fields_filled: [],
      fields_skipped: [],
      continue_clicked: false,
      post_continue_url: '',
      page_title: '',
      needs_manual_review: false,
      evidence: '',
      screenshots: [],
      error: 'Unhandled error: ' + e.message,
    });
  } finally {
    if (browser) {
      try { await browser.close(); } catch (_) {}
    }
  }
}

run();
