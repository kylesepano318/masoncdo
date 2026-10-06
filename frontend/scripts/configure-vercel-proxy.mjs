import { readFile, writeFile } from "node:fs/promises";

const input = process.argv[2];
let target;
try {
  target = new URL(input);
  if (
    target.protocol !== "https:" ||
    target.username ||
    target.password ||
    target.pathname !== "/" ||
    target.search ||
    target.hash
  )
    throw new Error();
} catch {
  console.error(
    "Usage: npm run configure:vercel -- https://YOUR-SERVICE.onrender.com (HTTPS origin only)",
  );
  process.exit(1);
}
const file = new URL("../vercel.json", import.meta.url);
const config = JSON.parse(await readFile(file, "utf8"));
config.rewrites = [
  { source: "/api/:path*", destination: `${target.origin}/api/:path*` },
  { source: "/sanctum/:path*", destination: `${target.origin}/sanctum/:path*` },
  ...(config.rewrites || []).filter(
    (rule) =>
      !rule.source.startsWith("/api/") && !rule.source.startsWith("/sanctum/"),
  ),
];
await writeFile(file, JSON.stringify(config, null, 2) + "\n");
console.log(
  `Configured Vercel API and CSRF proxy to ${target.origin}. Commit frontend/vercel.json and redeploy Vercel. Set VITE_API_BASE_URL=/ in Vercel.`,
);
