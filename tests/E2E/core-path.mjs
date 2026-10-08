// One automated browser test of the core path on a running site: a client books a job with Siya, a pro
// sends an estimate, the client chooses the pro, the job is marked done, the client reviews and the pro replies.
//
//   E2E_BASE_URL=https://usesorted.co.za E2E_CUSTOMER=… E2E_PRO=… E2E_PASSWORD=… npm run e2e
//
// Needs two existing accounts: a client, and an approved pro near the address used below (Musgrave, Durban)
// who offers plumbing. Never run it against production: it creates a real job. Uses Brave by default.
import { chromium } from 'playwright-core';

const base = process.env.E2E_BASE_URL ?? 'http://localhost:8000';
const customerEmail = process.env.E2E_CUSTOMER;
const proEmail = process.env.E2E_PRO;
const password = process.env.E2E_PASSWORD;
const browserPath = process.env.E2E_BROWSER ?? '/Applications/Brave Browser.app/Contents/MacOS/Brave Browser';
const headless = process.env.E2E_HEADED !== '1';

if (!customerEmail || !proEmail || !password) {
  console.error('Set E2E_CUSTOMER, E2E_PRO and E2E_PASSWORD (and E2E_BASE_URL).');
  process.exit(2);
}

const steps = [];
const browser = await chromium.launch({ executablePath: browserPath, headless });

async function step(name, fn) {
  const started = Date.now();
  try {
    await fn();
    steps.push(`ok   ${name} (${((Date.now() - started) / 1000).toFixed(1)}s)`);
  } catch (error) {
    steps.push(`FAIL ${name}: ${String(error.message).split('\n')[0]}`);
    throw error;
  }
}

async function session(email) {
  const context = await browser.newContext({ viewport: { width: 420, height: 900 } });
  const page = await context.newPage();
  page.setDefaultTimeout(30_000);
  await page.goto(`${base}/login`);
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.waitForURL(/\/(app|pros)/);
  return { context, page };
}

const text = async (page) => page.locator('body').innerText();
const expectText = async (page, needle, what) => {
  await page.waitForFunction((n) => document.body.innerText.includes(n), needle, { timeout: 30_000 }).catch(() => {
    throw new Error(`expected to see "${needle}" (${what})`);
  });
};

let failed = false;
try {
  const customer = await session(customerEmail);
  const pro = await session(proEmail);
  const c = customer.page;
  const p = pro.page;

  const inviteLinks = async () => {
    await p.goto(`${base}/pros/jobs`);
    await p.waitForLoadState('networkidle');
    return p.locator('a[href*="/pros/jobs/"]').evaluateAll((links) => links.map((link) => link.getAttribute('href')));
  };
  const invitesBefore = new Set(await inviteLinks());

  await step('client describes the problem to Siya', async () => {
    await c.goto(`${base}/book`);
    await c.waitForLoadState('networkidle');
    await c.fill('#siya-message', 'My kitchen tap drips constantly even when fully closed. Sometime this week is fine.');
    await c.keyboard.press('Enter');
    await c.getByText(/Add your address|Add another property/).first().waitFor({ timeout: 60_000 });
  });

  await step('client picks an address (a saved one, or a new one)', async () => {
    const saved = c.getByText('100 Musgrave Road, Musgrave').first();
    if (await saved.isVisible().catch(() => false)) {
      await saved.click();
    } else {
      await c.getByText('+ Add your address').click();
      await c.getByLabel('Find your address').fill('100 Musgrave Road, Durban');
      await c.getByText('100 Musgrave Road, Musgrave, Durban, South Africa').first().click();
      await c.getByText('House', { exact: true }).first().click();
      await c.getByRole('button', { name: /use this address/i }).click();
    }
    await c.getByText('Choose a date and time').waitFor({ timeout: 60_000 });
  });

  await step('client chooses tomorrow morning', async () => {
    await c.getByText('Choose a date and time').first().click();
    await c.getByRole('button', { name: 'Tomorrow' }).click();
    await c.getByRole('button', { name: /Morning/ }).click();
    await c.getByRole('button', { name: /skip for now/i }).waitFor();
  });

  await step('client skips photos and confirms the booking', async () => {
    await c.getByRole('button', { name: /skip for now/i }).click();
    await c.getByRole('button', { name: /confirm booking/i }).click();
    await expectText(c, 'Your job is booked', 'booking confirmation');
  });

  await step('pro accepts the new invite', async () => {
    // The invite is created by the queue a few seconds after the booking. Earlier runs leave their own open
    // invites behind, so wait for a link that was not there before the booking.
    let fresh;
    for (let attempt = 0; attempt < 12 && !fresh; attempt++) {
      fresh = (await inviteLinks()).find((href) => !invitesBefore.has(href));
      if (!fresh) await p.waitForTimeout(5_000);
    }
    if (!fresh) throw new Error('the pro was not invited to the new job within a minute');
    await p.goto(new URL(fresh, base).toString());
    await p.getByRole('button', { name: 'Accept job' }).click();
    await p.getByRole('button', { name: 'Send an estimate' }).waitFor();
  });

  await step('pro sends an estimate', async () => {
    await p.getByRole('button', { name: 'Send an estimate' }).click();
    const form = p.locator('input[wire\\:model*="lines.0.description"]');
    const popup = p.getByText('Add credit to send this estimate');
    await form.or(popup).first().waitFor();
    if (await popup.isVisible()) {
      throw new Error('the pro has no introduction credit and the fee is on: top up the pro, or switch the fee off in Admin, Introduction fee');
    }
    await form.fill('Replace tap cartridge and washers');
    await p.locator('input[wire\\:model*="lines.0.quantity"]').fill('1');
    await p.locator('input[wire\\:model*="lines.0.unitPrice"]').fill('450');
    await p.locator('input[type=date]').fill(new Date(Date.now() + 2 * 864e5).toISOString().slice(0, 10));
    await p.locator('input[wire\\:model*="validityDays"]').fill('7');
    await p.getByRole('button', { name: 'Preview' }).click();
    await p.getByRole('button', { name: 'Send quote' }).click();
    await expectText(p, 'Quote sent', 'estimate confirmation');
  });

  await step('client chooses the pro to visit', async () => {
    await c.goto(`${base}/app/jobs`);
    await c.locator('a[href*="/app/jobs/"]').first().click();
    await c.getByRole('button', { name: 'Choose this pro to visit' }).click();
    await c.getByRole('button', { name: 'Choose this pro', exact: true }).click();
    await expectText(c, 'Booked with', 'introduction');
  });

  await step('the pro now sees the client\'s contact details', async () => {
    await p.reload();
    await expectText(p, 'Your quote was accepted', 'pro contact reveal');
    await expectText(p, 'Mobile', 'client mobile shown');
  });

  await step('client marks the job done', async () => {
    await c.reload();
    await c.getByRole('button', { name: 'Mark as done' }).click();
    await c.getByRole('button', { name: 'Yes, it is done' }).click();
    await expectText(c, 'This job is done', 'job done');
  });

  await step('client reviews the pro', async () => {
    await c.getByRole('radio', { name: '5 stars' }).click();
    await c.fill('#reviewComment', 'Quick and tidy (automated test).');
    await c.getByRole('button', { name: 'Send review' }).click();
    await expectText(c, 'Your review', 'review saved');
  });

  await step('pro replies to the review', async () => {
    await p.reload();
    await p.fill('#replyText', 'Thank you!');
    await p.getByRole('button', { name: 'Send reply' }).click();
    await expectText(p, 'Your reply', 'reply saved');
  });
} catch {
  failed = true;
} finally {
  await browser.close();
  console.log(steps.join('\n'));
  console.log(failed ? '\nCORE PATH FAILED' : '\nCORE PATH PASSED');
  process.exit(failed ? 1 : 0);
}
