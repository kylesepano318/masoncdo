import { chromium } from "@playwright/test";
import { mkdir } from "node:fs/promises";
const browser = await chromium.launch();
await mkdir("../.local/screenshots", { recursive: true });
for (const [name, width, height] of [
  ["desktop", 1366, 900],
  ["mobile", 390, 844],
]) {
  const page = await browser.newPage({ viewport: { width, height } });
  await page.goto(process.env.TEST_BASE_URL || "http://127.0.0.1:5173/");
  await page.locator(".lodge-emblem").waitFor();
  await page.locator("img").evaluateAll(async (images) =>
    Promise.all(
      images.map(async (img) => {
        img.loading = "eager";
        await img.decode();
      }),
    ),
  );
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({
    path: `../.local/screenshots/${name}.png`,
    fullPage: true,
  });
  await page.close();
}
await browser.close();
