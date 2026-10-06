import { test, expect } from "@playwright/test";
test("administrator manages members, birthday visibility, applications, and CMS publishing", async ({
  page,
}) => {
  test.setTimeout(120000);
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
  await page.goto("/admin/login");
  await page.getByLabel("Email address").fill(process.env.TEST_ADMIN_EMAIL!);
  await page
    .getByLabel("Password", { exact: true })
    .fill(process.env.TEST_ADMIN_PASSWORD!);
  await page.getByRole("button", { name: "Login", exact: true }).click();
  await expect(page).toHaveURL(/admin\/dashboard/);
  await page.goto("/admin/members/create");
  await page.getByLabel("First name").fill("Browser");
  await page.getByLabel("Last name").fill("Member");
  await page.getByRole("button", { name: "Save member" }).click();
  await expect(page).toHaveURL(/admin\/members$/);
  await expect(
    page.getByRole("cell", { name: "Browser Member" }),
  ).toBeVisible();
  await page.goto("/admin/celebrations");
  await page.getByRole("button", { name: "Add celebration" }).click();
  await expect(
    page.getByLabel("Public — display this celebration on the website"),
  ).toBeChecked();
  const year = new Date().getFullYear();
  await page.getByLabel("Title *", { exact: true }).fill("Birthday this year");
  await page.getByLabel("Celebration date").fill(`${year}-12-31`);
  await page.getByRole("button", { name: "Save celebration" }).click();
  await expect(
    page.getByRole("cell", { name: "Birthday this year", exact: true }),
  ).toBeVisible();
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
  ).toBeVisible();
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
  await page.getByRole("button", { name: "Send test email" }).click();
  await expect(
    page.getByText("Test email sent.", { exact: true }),
  ).toBeVisible();
  await page.getByRole("button", { name: "Logout" }).click();
  await expect(page).toHaveURL(/admin\/login/);
  await page.goto("/admin/dashboard");
  await expect(page).toHaveURL(/admin\/login/);
  expect(errors).toEqual([]);
  if (process.env.TEST_SAME_ORIGIN) {
    expect(apiOrigins.length).toBeGreaterThan(0);
    expect([...new Set(apiOrigins)]).toEqual([
      new URL(process.env.TEST_BASE_URL!).origin,
    ]);
  }
});
