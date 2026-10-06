import { test, expect } from "@playwright/test";

function publicPage(slug: string, video = false) {
  return {
    page: { slug, name: slug, meta_title: slug, meta_description: null },
    sections: [
      {
        id: 1,
        section_type: "rich_text",
        title: `${slug} content`,
        body: "Content",
        is_visible: true,
      },
      ...(video
        ? [
            {
              id: 2,
              section_type: "video",
              title: "Lodge video",
              settings: {
                video_url: "https://www.youtube.com/watch?v=uACxhycnzC0&t=7s",
                video_title: "Lodge video",
              },
              is_visible: true,
            },
          ]
        : []),
    ],
    members: [],
    affiliations: [],
    celebrations: [],
    today: "2026-10-06",
  };
}

test("page, identity and settings load together; navigation reuses shared data and videos wait for a click", async ({
  page,
}) => {
  let siteCalls = 0,
    sessionCalls = 0;
  const pageCalls: string[] = [];
  let releaseSite!: () => void;
  await page.route("**/api/public/site", async (route) => {
    siteCalls++;
    await new Promise<void>((resolve) => {
      releaseSite = resolve;
    });
    await route.fulfill({ json: { site: {} } });
  });
  await page.route("**/api/public/session", async (route) => {
    sessionCalls++;
    await route.fulfill({ json: { user: null } });
  });
  await page.route("**/api/public/pages/*", async (route) => {
    const slug = new URL(route.request().url()).pathname.split("/").pop()!;
    pageCalls.push(slug);
    await route.fulfill({ json: publicPage(slug, true) });
  });
  await page.goto("/history", { waitUntil: "domcontentloaded" });
  await expect
    .poll(() => ({ siteCalls, sessionCalls, pageCalls }))
    .toEqual({ siteCalls: 1, sessionCalls: 1, pageCalls: ["history"] });
  releaseSite();
  await expect(
    page.getByRole("heading", { name: "history content", exact: true }),
  ).toBeVisible();
  await expect(page.locator(".youtube-frame iframe")).toHaveCount(0);
  await page
    .getByRole("navigation", { name: "Main navigation" })
    .getByRole("link", { name: "members", exact: true })
    .click();
  await expect(
    page.getByRole("heading", { name: "members content", exact: true }),
  ).toBeVisible();
  await page
    .getByRole("navigation", { name: "Main navigation" })
    .getByRole("link", { name: "history", exact: true })
    .click();
  await expect(
    page.getByRole("heading", { name: "history content", exact: true }),
  ).toBeVisible();
  expect(siteCalls).toBe(1);
  expect(sessionCalls).toBe(1);
  expect(pageCalls).toEqual(["history", "members"]);
  await page.route("https://www.youtube-nocookie.com/**", (route) =>
    route.fulfill({ contentType: "text/html", body: "Video player" }),
  );
  await page
    .getByRole("button", { name: "Play Lodge video", exact: true })
    .click();
  await expect(page.locator(".youtube-frame iframe")).toHaveAttribute(
    "src",
    /autoplay=1&start=7/,
  );
});

test("settings writes invalidate branding, older picker images stay available, and logout clears identity", async ({
  page,
}) => {
  let name = "Original Lodge",
    loggedIn = true,
    siteCalls = 0,
    csrfCalls = 0,
    writes = 0;
  await page.context().addCookies([
    {
      name: "XSRF-TOKEN",
      value: "expired",
      url: process.env.TEST_BASE_URL || "http://127.0.0.1:5173",
    },
  ]);
  const user = { id: 1, name: "Admin", email: "admin@example.test" };
  const emblem = "/images/golden-friendship-lodge-no-40.png";
  await page.route("**/sanctum/csrf-cookie", async (route) => {
    csrfCalls++;
    await route.fulfill({
      status: 204,
      headers: { "Set-Cookie": "XSRF-TOKEN=fresh; Path=/" },
    });
  });
  await page.route("**/api/**", async (route) => {
    const url = new URL(route.request().url()),
      path = url.pathname;
    const json = (value: unknown) => route.fulfill({ json: value });
    if (path === "/api/public/site") {
      siteCalls++;
      return json({ site: { branding: { name, emblem } } });
    }
    if (path === "/api/admin/me")
      return loggedIn
        ? json({ user, pages: [], unread: 0 })
        : route.fulfill({ status: 401, json: { message: "Unauthenticated." } });
    if (path === "/api/public/session")
      return json({ user: loggedIn ? user : null });
    if (path === "/api/admin/settings/branding") {
      if (route.request().method() === "PUT") {
        writes++;
        if (writes === 1)
          return route.fulfill({
            status: 419,
            json: { message: "CSRF token mismatch." },
          });
        name = route.request().postDataJSON().name;
        return json({ message: "Settings saved." });
      }
      return json({
        group: "branding",
        settings: { branding: { name, location: "CDO", emblem } },
        media: [],
      });
    }
    if (path === "/api/admin/media")
      return json({
        media: {
          data: [
            {
              id: 99,
              path: emblem,
              original_name: "older-emblem.png",
              alt_text: "Older emblem",
              caption: null,
            },
          ],
          last_page: 1,
        },
      });
    if (path.startsWith("/api/public/pages/"))
      return json(publicPage(path.split("/").pop()!));
    if (path === "/api/admin/logout") {
      loggedIn = false;
      return json({ message: "Logged out." });
    }
    return json({});
  });
  await page.goto("/admin/settings/branding", {
    waitUntil: "domcontentloaded",
  });
  await expect(page.getByLabel("Lodge emblem", { exact: true })).toHaveValue(
    emblem,
  );
  await page
    .getByRole("button", { name: "Browse media library", exact: true })
    .click();
  await page
    .getByLabel("Search lodge emblem in media library", { exact: true })
    .fill("Older");
  await expect(
    page
      .getByLabel("Lodge emblem", { exact: true })
      .locator("option")
      .filter({ hasText: "Older emblem" }),
  ).toHaveCount(1);
  await page.getByLabel("name", { exact: true }).fill("Updated Lodge");
  await page
    .getByRole("button", { name: "Save settings", exact: true })
    .click();
  await expect(
    page.getByText("Settings saved.", { exact: true }),
  ).toBeVisible();
  await expect(page.getByLabel("name", { exact: true })).toHaveValue(
    "Updated Lodge",
  );
  expect(siteCalls).toBe(2);
  expect(writes).toBe(2);
  expect(csrfCalls).toBe(1);
  await page.getByRole("button", { name: "Logout", exact: true }).click();
  await expect(page).toHaveURL(/admin\/login/);
  await page
    .getByRole("link", { name: "Return to the lodge website", exact: false })
    .click();
  await expect(
    page.getByRole("link", { name: "Admin Login", exact: true }),
  ).toBeVisible();
  expect(csrfCalls).toBe(1);
});
