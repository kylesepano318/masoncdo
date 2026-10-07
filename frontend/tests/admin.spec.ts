import { test, expect } from "@playwright/test";
test("administrator manages members, birthday visibility, applications, and CMS publishing", async ({
  page,
}) => {
  test.setTimeout(180000);
  test.skip(
    !process.env.TEST_ADMIN_EMAIL,
    "Use scripts/verify-admin.ps1 for isolated admin tests.",
  );
  const errors: string[] = [];
  const apiOrigins: string[] = [];
  page.on("request", (request) => {
    const url = new URL(request.url());
    if (
      url.pathname.startsWith("/api/") ||
      url.pathname.startsWith("/sanctum/")
    )
      apiOrigins.push(url.origin);
  });
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto("/");
  await expect(
    page.getByRole("link", { name: "Admin Login", exact: true }),
  ).toHaveAttribute("href", "/admin/login");
  await page.getByRole("link", { name: "Admin Login", exact: true }).click();
  await expect(
    page.getByRole("heading", { name: "Administrator login", exact: true }),
  ).toBeVisible();
  await page.getByLabel("Email address").fill(process.env.TEST_ADMIN_EMAIL!);
  await page
    .getByLabel("Password", { exact: true })
    .fill(process.env.TEST_ADMIN_PASSWORD!);
  await page.getByRole("button", { name: "Login", exact: true }).click();
  await expect(page).toHaveURL(/admin\/dashboard/);
  await page.goto("/");
  await expect(
    page.getByRole("link", { name: "Admin", exact: true }),
  ).toHaveAttribute("href", "/admin/dashboard");
  await page.reload();
  await expect(
    page.getByRole("link", { name: "Admin", exact: true }),
  ).toBeVisible();
  await page.getByRole("link", { name: "Admin", exact: true }).click();
  await expect(page).toHaveURL(/admin\/dashboard/);
  await page.goto("/admin/members");
  for (const position of [
    { name: "Honorary Member", officer: false },
    { name: "Secretary", officer: true },
  ]) {
    await page
      .getByRole("button", { name: "Add position", exact: true })
      .click();
    await page.getByLabel("Position name", { exact: true }).fill(position.name);
    if (position.officer)
      await page
        .getByLabel("Officer position — one active member at a time")
        .check();
    if (!position.officer) {
      let attempts = 0;
      let release!: () => void;
      let fail = true;
      await page.route("**/api/admin/membership-positions", async (route) => {
        if (route.request().method() !== "POST") return route.continue();
        attempts++;
        await new Promise<void>((resolve) => {
          release = resolve;
        });
        if (fail) {
          await route.fulfill({
            status: 422,
            contentType: "application/json",
            body: JSON.stringify({
              errors: { name: ["Please try saving again."] },
            }),
          });
        } else await route.continue();
      });
      const submitTwice = () =>
        page
          .locator("button[type=submit], button.admin-button")
          .filter({ hasText: "Save position" })
          .evaluate((button) => {
            const form = (button as HTMLButtonElement).form!;
            form.requestSubmit();
            form.requestSubmit();
          });
      await submitTwice();
      await expect(
        page.getByRole("status").filter({ hasText: "Submitting" }),
      ).toBeVisible();
      await expect(
        page.locator("button").filter({ hasText: "Saving…" }),
      ).toBeDisabled();
      await expect.poll(() => attempts).toBe(1);
      release();
      await expect(
        page
          .locator(".field-error")
          .filter({ hasText: "Please try saving again." }),
      ).toBeVisible();
      await expect(
        page.getByRole("button", { name: "Save position", exact: true }),
      ).toBeEnabled();
      fail = false;
      await submitTwice();
      await expect.poll(() => attempts).toBe(2);
      release();
    } else {
      await page
        .getByRole("button", { name: "Save position", exact: true })
        .click();
    }
    await expect(
      page.getByRole("heading", {
        name: "Add membership position",
        exact: true,
      }),
    ).toHaveCount(0);
    await expect(
      page.getByText("Membership position added.", { exact: true }),
    ).toBeVisible();
    await page.unroute("**/api/admin/membership-positions");
  }
  await page.goto("/admin/members/create");
  await page
    .getByLabel("Membership position", { exact: true })
    .selectOption({ label: "Honorary Member" });
  await page.getByLabel("First name").fill("Browser");
  await page.getByLabel("Last name").fill("Member");
  await page.getByRole("button", { name: "Save member" }).click();
  await expect(page).toHaveURL(/admin\/members$/);
  await expect(
    page.getByRole("cell", { name: "Browser Member" }),
  ).toBeVisible();
  await expect(
    page.getByRole("row").filter({ hasText: "Browser Member" }),
  ).toContainText("Honorary Member");
  await page.goto("/admin/members/create");
  await page.getByLabel("First name").fill("Secretary");
  await page.getByLabel("Last name").fill("LodgeOfficer");
  await page
    .getByLabel("Membership position", { exact: true })
    .selectOption({ label: "Secretary" });
  await page.getByRole("button", { name: "Save member", exact: true }).click();
  await expect(page).toHaveURL(/admin\/members$/);
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await expect(
    page
      .locator(".officers-section")
      .getByRole("heading", { name: "Secretary LodgeOfficer", exact: true }),
  ).toBeVisible();
  await page.goto("/admin/celebrations");
  for (const name of ["Installation Ceremony", "Temporary Type"]) {
    await page
      .getByRole("button", { name: "Manage types", exact: true })
      .click();
    await page.getByLabel("Celebration type name", { exact: true }).fill(name);
    await page.getByRole("button", { name: "Add type", exact: true }).click();
    await expect(
      page.getByText("Celebration type added.", { exact: true }),
    ).toBeVisible();
    await expect(
      page.getByRole("heading", { name: "Celebration types", exact: true }),
    ).toHaveCount(0);
  }
  await page.getByRole("button", { name: "Manage types", exact: true }).click();
  page.once("dialog", (dialog) => dialog.accept());
  await page
    .getByRole("button", { name: "Delete type Temporary Type", exact: true })
    .click();
  await expect(
    page.getByText("Celebration type deleted.", { exact: true }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Add celebration" }).click();
  await expect(
    page
      .getByLabel("Celebration type", { exact: true })
      .locator("option")
      .filter({ hasText: "Installation Ceremony" }),
  ).toHaveCount(1);
  await expect(
    page.getByLabel("Public — display this celebration on the website"),
  ).toBeChecked();
  const year = new Date().getFullYear();
  await page.getByLabel("Title *", { exact: true }).fill("Birthday this year");
  await page.getByLabel("Celebration date").fill(`${year}-12-31`);
  let uploadedImages = 0;
  await page.route("**/api/admin/media", async (route) => {
    if (route.request().method() !== "POST") return route.continue();
    uploadedImages++;
    await route.fulfill({
      status: 201,
      contentType: "application/json",
      body: JSON.stringify({
        message: "Image uploaded.",
        media: {
          id: 1000 + uploadedImages,
          path:
            uploadedImages === 1
              ? "/images/lodge-brethren.jpg"
              : "/images/lodge-ceremony.jpg",
          original_name: "greeting.png",
          alt_text: "Uploaded greeting",
          caption: null,
        },
      }),
    });
  });
  const imageFile = {
    name: "greeting.png",
    mimeType: "image/png",
    buffer: Buffer.from(
      "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII=",
      "base64",
    ),
  };
  await page
    .getByLabel("Choose featured photograph file", { exact: true })
    .setInputFiles(imageFile);
  await page
    .getByRole("button", { name: "Upload featured photograph", exact: true })
    .click();
  await expect(
    page.getByLabel("Featured photograph", { exact: true }),
  ).toHaveValue("/images/lodge-brethren.jpg");
  await expect(page.getByLabel("Title *", { exact: true })).toHaveValue(
    "Birthday this year",
  );
  await page
    .getByRole("button", { name: "Add photograph", exact: true })
    .click();
  await page
    .getByLabel("Choose photograph file", { exact: true })
    .setInputFiles(imageFile);
  await page
    .getByRole("button", { name: "Upload photograph", exact: true })
    .click();
  await expect(page.getByLabel("Photograph", { exact: true })).toHaveValue(
    "/images/lodge-ceremony.jpg",
  );
  await page
    .getByLabel("Photo caption", { exact: true })
    .fill("Birthday gathering");
  await page
    .getByRole("button", { name: "Add photograph", exact: true })
    .click();
  await page
    .getByLabel("Photograph", { exact: true })
    .nth(1)
    .selectOption("/images/lodge-brethren.jpg");
  await page
    .getByLabel("Photo caption", { exact: true })
    .nth(1)
    .fill("With the brethren");
  expect(uploadedImages).toBe(2);
  await page.unroute("**/api/admin/media");
  await page.getByRole("button", { name: "Save celebration" }).click();
  await expect(
    page.getByRole("cell", { name: "Birthday this year", exact: true }),
  ).toBeVisible();
  await page
    .getByRole("row")
    .filter({ hasText: "Birthday this year" })
    .getByRole("button", { name: "Edit", exact: true })
    .click();
  await expect(
    page.getByLabel("Featured photograph", { exact: true }),
  ).toHaveValue("/images/lodge-brethren.jpg");
  await expect(page.getByLabel("Photo caption", { exact: true })).toHaveCount(
    2,
  );
  await expect(
    page.getByLabel("Photo caption", { exact: true }).first(),
  ).toHaveValue("Birthday gathering");
  await page.getByRole("button", { name: "Close ×", exact: true }).click();
  await page.getByRole("button", { name: "Add celebration" }).click();
  await page
    .getByLabel("Title *", { exact: true })
    .fill("Birthday next December");
  await page.getByLabel("Celebration date").fill(`${year + 1}-12-20`);
  await page.getByRole("button", { name: "Save celebration" }).click();
  await expect(
    page.getByRole("cell", { name: "Birthday next December" }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Add celebration" }).click();
  await page.getByLabel("Title *", { exact: true }).fill("Private birthday");
  await page.getByLabel("Celebration date").fill(`${year}-12-25`);
  await page
    .getByLabel("Public — display this celebration on the website")
    .uncheck();
  await page.getByRole("button", { name: "Save celebration" }).click();
  await expect(
    page.getByRole("cell", { name: "Private birthday" }),
  ).toBeVisible();
  await page.goto("/celebrations");
  await expect(
    page.getByRole("heading", { name: "Birthday this year", exact: true }),
  ).toBeVisible();
  await expect(page.getByText("Birthday next December")).toHaveCount(0);
  await expect(page.getByText("Private birthday")).toHaveCount(0);
  await page.goto("/application");
  await page.getByLabel("First name").fill("Browser");
  await page.getByLabel("Last name").fill("Applicant");
  await page.getByLabel("Date of birth").fill("1990-01-01");
  await page
    .getByLabel("Complete address")
    .fill("Private browser test address");
  await page.getByLabel("City / municipality").fill("Cagayan de Oro");
  await page.getByLabel("Province").fill("Misamis Oriental");
  await page.getByLabel("Mobile number").fill("09123456789");
  await page.getByLabel("Email address").fill("browser-applicant@example.test");
  await page
    .getByLabel("Why do you wish to join?")
    .fill("To serve my community.");
  await page.getByLabel("I certify").check();
  await page.getByLabel("I consent").check();
  await page.getByRole("button", { name: "Submit application" }).click();
  await expect(page).toHaveURL(/application\/received/);
  await expect(page.locator(".reference-number")).toContainText(`APP-${year}-`);
  await page.goto("/admin/applications");
  await expect(page.getByText("NEW", { exact: true })).toBeVisible();
  await page.getByRole("link", { name: "View application" }).click();
  await expect(
    page.getByText("Private browser test address", { exact: true }),
  ).toBeVisible();
  await page.getByLabel("Status", { exact: true }).selectOption("under_review");
  await page.getByLabel("Internal notes").fill("Browser private review notes");
  await page.getByRole("button", { name: "Save review" }).click();
  await expect(
    page.getByText("Application status updated.", { exact: true }),
  ).toBeVisible({ timeout: 15000 });
  await page.reload();
  await expect(page.getByLabel("Internal notes")).toHaveValue(
    "Browser private review notes",
  );
  await expect(page.getByLabel("Status", { exact: true })).toHaveValue(
    "under_review",
  );
  page.once("dialog", (d) => d.accept());
  await page.getByRole("button", { name: "Approve & create member" }).click();
  await expect(
    page.getByText("Already converted to a private member record."),
  ).toBeVisible();
  await expect(
    page.getByRole("button", { name: "Approve & create member" }),
  ).toHaveCount(0);
  await page.getByRole("link", { name: "Home", exact: true }).click();
  await page.getByRole("button", { name: "Add section" }).click();
  await page.getByLabel("Section type").selectOption("quote");
  await page
    .getByLabel("Heading", { exact: true })
    .fill("Browser draft quotation");
  await page.getByRole("button", { name: "Save section to draft" }).click();
  await expect(
    page
      .locator(".section-label")
      .getByText("Browser draft quotation", { exact: true }),
  ).toBeVisible();
  const editorUrl = page.url();
  await page.goto("/");
  await expect(page.getByText("Browser draft quotation")).toHaveCount(0);
  await page.goto("/preview/home");
  await expect(page.getByText("Browser draft quotation")).toBeVisible();
  await page.goto(editorUrl);
  await page.getByRole("button", { name: "Publish", exact: true }).click();
  await expect(
    page.getByText("Page published.", { exact: true }),
  ).toBeVisible();
  await page.goto("/");
  await expect(page.getByText("Browser draft quotation")).toBeVisible();
  await page.goto("/admin/settings/notifications");
  await expect(
    page.getByLabel("Send email for new applications"),
  ).toBeChecked();
  await expect(
    page.getByLabel("Send an acknowledgment email"),
  ).not.toBeChecked();
  await page
    .getByLabel("Application notification email")
    .fill("lodge-test@example.test");
  await page.getByRole("button", { name: "Save notifications" }).click();
  await expect(
    page.getByText("Settings saved.", { exact: true }),
  ).toBeVisible();
  let testEmailRequests = 0;
  let releaseTestEmail!: () => void;
  await page.route(
    "**/api/admin/settings/notifications/test-email",
    async (route) => {
      testEmailRequests++;
      await new Promise<void>((resolve) => {
        releaseTestEmail = resolve;
      });
      await route.continue();
    },
  );
  await page
    .getByRole("button", { name: "Send test email" })
    .evaluate((button) => {
      button.dispatchEvent(new MouseEvent("click", { bubbles: true }));
      button.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    });
  await expect(
    page.getByRole("status").filter({ hasText: "Submitting" }),
  ).toBeVisible();
  await expect.poll(() => testEmailRequests).toBe(1);
  releaseTestEmail();
  await expect(
    page.getByText("Test email sent.", { exact: true }),
  ).toBeVisible();
  expect(testEmailRequests).toBe(1);
  await page.unroute("**/api/admin/settings/notifications/test-email");
  await page
    .getByRole("link", { name: "Login credentials", exact: true })
    .click();
  await expect(page.getByLabel("Login email address")).toHaveValue(
    process.env.TEST_ADMIN_EMAIL!,
  );
  const updatedEmail = "updated-browser-admin@example.test";
  const updatedPassword = process.env.TEST_ADMIN_PASSWORD! + "New";
  await page.getByLabel("Login email address").fill(updatedEmail);
  await page
    .getByLabel("Current password", { exact: true })
    .fill(process.env.TEST_ADMIN_PASSWORD!);
  await page
    .getByLabel("New password (optional)", { exact: true })
    .fill(updatedPassword);
  await page
    .getByLabel("Confirm new password", { exact: true })
    .fill(updatedPassword);
  await page
    .getByRole("button", { name: "Save login credentials", exact: true })
    .click();
  await expect(
    page.getByText("Login credentials updated.", { exact: true }),
  ).toBeVisible();
  await page.reload();
  await expect(page.getByLabel("Login email address")).toHaveValue(
    updatedEmail,
  );
  await page.getByRole("button", { name: "Logout" }).click();
  await expect(page).toHaveURL(/admin\/login/);
  await expect(
    page.getByRole("heading", { name: "Administrator login", exact: true }),
  ).toBeVisible();
  await expect(page.locator("div[aria-busy]").first()).toHaveAttribute(
    "aria-busy",
    "false",
  );
  await page.getByLabel("Email address").fill(updatedEmail);
  await page.getByLabel("Password", { exact: true }).fill(updatedPassword);
  await page.getByRole("button", { name: "Login", exact: true }).click();
  await expect(page).toHaveURL(/admin\/dashboard/);
  // Restore the isolated account so the following performance checks can log in.
  await page.goto("/admin/settings/account");
  await page
    .getByLabel("Login email address")
    .fill(process.env.TEST_ADMIN_EMAIL!);
  await page
    .getByLabel("Current password", { exact: true })
    .fill(updatedPassword);
  await page
    .getByLabel("New password (optional)", { exact: true })
    .fill(process.env.TEST_ADMIN_PASSWORD!);
  await page
    .getByLabel("Confirm new password", { exact: true })
    .fill(process.env.TEST_ADMIN_PASSWORD!);
  await page
    .getByRole("button", { name: "Save login credentials", exact: true })
    .click();
  await expect(
    page.getByText("Login credentials updated.", { exact: true }),
  ).toBeVisible();

  await page.getByRole("button", { name: "Logout" }).click();
  await expect(page).toHaveURL(/admin\/login/);
  await page.goto("/admin/dashboard");
  await expect(page).toHaveURL(/admin\/login/);
  await page.goto("/");
  await expect(
    page.getByRole("link", { name: "Admin Login", exact: true }),
  ).toBeVisible();
  expect(errors).toEqual([]);
  if (process.env.TEST_SAME_ORIGIN) {
    expect(apiOrigins.length).toBeGreaterThan(0);
    expect([...new Set(apiOrigins)]).toEqual([
      new URL(process.env.TEST_BASE_URL!).origin,
    ]);
  }
});


test("both seeded administrators can log in by username", async ({ page }) => {
  test.skip(!process.env.TEST_ADMIN_PASSWORD, "Use scripts/verify-admin.ps1");
  for (const username of ["browser-master", "browser-warden"]) {
    await page.goto("/admin/login");
    await expect(page.locator("div[aria-busy]").first()).toHaveAttribute("aria-busy", "false");
    await page.getByLabel("Email address or username").fill(username);
    await page.getByLabel("Password", { exact: true }).fill(process.env.TEST_ADMIN_PASSWORD!);
    await page.getByRole("button", { name: "Login", exact: true }).click();
    await expect(page).toHaveURL(/admin\/dashboard/);
    const response = await page.request.get("/api/admin/me", { headers: { Origin: new URL(page.url()).origin, Accept: "application/json" } });
    expect(response.status()).toBe(200);
    expect((await response.json()).user.username).toBe(username);
    await page.getByRole("button", { name: "Logout", exact: true }).click();
    await expect(page).toHaveURL(/admin\/login/);
  }
});
