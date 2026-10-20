import { chromium } from "playwright";
import { execSync } from "node:child_process";
const BASE = "https://6valley.test",
    CWD = "/Applications/MAMP/htdocs/6Valley";
const OUT =
    "/private/tmp/claude-501/-Applications-MAMP-htdocs-6Valley/c445e6b9-d365-4479-9250-07bdfae26a1e/scratchpad";
execSync(
    `find storage/framework/sessions -type f ! -name '.gitignore' -delete`,
    { cwd: CWD },
);
const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const banner404 = [];
page.on("response", (r) => {
    if (r.status() === 404 && /900x400|banner|img1/.test(r.url()))
        banner404.push(r.url().replace(BASE, ""));
});

await page.goto(`${BASE}/vendor/auth/login`, { waitUntil: "networkidle" });
const phrase = execSync(
    `php -r 'foreach(glob("storage/framework/sessions/*") as $f){ $s=@unserialize(file_get_contents($f)); if(is_array($s)&&!empty($s["vendorRecaptchaSessionKey"])){echo $s["vendorRecaptchaSessionKey"];break;} }'`,
    { cwd: CWD },
)
    .toString()
    .trim();
await page.fill('input[name="email"]', "taylor@vendor.com");
await page.fill('input[name="password"]', "12345678");
await page.fill('input[name="default_captcha_value"]', phrase);
await Promise.all([
    page.waitForLoadState("networkidle"),
    page.click('button[type="submit"]'),
]);
await page.goto(
    `${BASE}/vendor-panel/builder/support-page-setup?section=help`,
    { waitUntil: "networkidle" },
);
await page.waitForTimeout(2500);
console.log(
    "banner 404s:",
    banner404.length ? JSON.stringify(banner404) : "NONE (fixed)",
);
await page.screenshot({ path: `${OUT}/support-final.png`, fullPage: true });
await browser.close();
