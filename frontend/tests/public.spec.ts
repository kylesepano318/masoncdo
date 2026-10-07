import { test, expect } from "@playwright/test";
test("homepage fits mobile, tablet, and desktop widths", async ({
  page,
  isMobile,
}) => {
  test.skip(isMobile);
  for (const width of [360, 390, 768, 1366, 1920]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto("/");
    await expect(page.locator(".hero-content h1")).toHaveText(
      "Golden Friendship",
    );
    await expect(page.locator(".hero-subtitle")).toHaveText(
      "Masonic Lodge No. 40",
    );
    await expect(page.locator(".hero-content .rich-content")).toHaveText(
      "Cagayan de Oro City",
    );
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBeTruthy();
    const size = await page.locator(".lodge-emblem").boundingBox();
    expect(size?.width).toBeGreaterThan(width < 500 ? 250 : 400);
    expect(size?.width).toBeLessThanOrEqual(650);
  }
});
test("public pages load with working imagery and no horizontal overflow", async ({
  page,
}) => {
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));
  for (const url of [
    "/",
    "/members",
    "/history",
    "/celebrations",
    "/application",
    "/admin/login",
  ]) {
    await page.goto(url);
    await expect(page.locator("h1")).toBeVisible();
    await page.locator("img").evaluateAll(async (images) => {
      await Promise.all(
        images.map(async (image) => {
          const img = image as HTMLImageElement;
          img.loading = "eager";
          try {
            await img.decode();
          } catch {
            /* The broken-image assertion below reports failures. */
          }
        }),
      );
    });
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBeTruthy();
    const broken = await page
      .locator("img")
      .evaluateAll((imgs) =>
        imgs
          .filter(
            (i) =>
              !(i as HTMLImageElement).complete ||
              (i as HTMLImageElement).naturalWidth === 0,
          )
          .map((i) => (i as HTMLImageElement).src),
      );
    expect(broken).toEqual([]);
  }
  expect(errors).toEqual([]);
});
test("homepage emblem is prominent and archival lightbox is keyboard accessible", async ({
  page,
}) => {
  await page.goto("/");
  await expect(page.locator(".lodge-emblem")).toBeVisible();
  await page
    .getByRole("button", {
      name: "Enlarge Brethren gathered in the Golden Friendship lodge",
    })
    .click();
  await expect(page.getByRole("dialog")).toBeVisible();
  await page.keyboard.press("Escape");
  await expect(page.getByRole("dialog")).not.toBeVisible();
});
test("mobile navigation opens and leads to celebrations", async ({
  page,
  isMobile,
}) => {
  test.skip(!isMobile);
  await page.goto("/");
  await page.getByRole("button", { name: "Open navigation" }).click();
  await page
    .locator("#main-nav")
    .getByRole("link", { name: "Celebrations" })
    .click();
  await expect(page).toHaveURL(/celebrations/);
  await expect(page.locator("h1")).toHaveText("Celebrating together");
});
