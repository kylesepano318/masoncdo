import { Head, useSiteContext } from "../lib/ui";
import type { CSSProperties, ReactNode } from "react";
import Header from "../components/public/Header";
import Footer from "../components/public/Footer";
import type { PageData, Shared } from "../types";
export default function PublicLayout({
  children,
  page,
  preview = false,
}: {
  children: ReactNode;
  page?: PageData;
  preview?: boolean;
}) {
  const { site } = useSiteContext<Shared>().props;
  const theme = Object.fromEntries(
    Object.entries(site.theme || {}).map(([k, v]) => [`--color-${k}`, v]),
  ) as CSSProperties;
  return (
    <div
      className={`public-site ${page?.slug === "home" ? "siglo-home" : ""}`}
      style={theme}
    >
      <Head title={page?.meta_title || page?.name || "Golden Friendship"}>
        {page?.meta_description && (
          <meta name="description" content={page.meta_description} />
        )}
        <meta
          property="og:title"
          content={
            page?.seo?.og_title || page?.meta_title || "Golden Friendship"
          }
        />
        <meta
          property="og:description"
          content={page?.seo?.og_description || page?.meta_description || ""}
        />
        {page?.seo?.og_image && (
          <meta property="og:image" content={page.seo.og_image} />
        )}
      </Head>
      <a className="skip-link" href="#main-content">
        Skip to content
      </a>
      {preview && (
        <div className="preview-bar">
          Draft preview · Changes are visible only to the administrator.
        </div>
      )}
      <Header />
      <main id="main-content">{children}</main>
      <Footer />
    </div>
  );
}
