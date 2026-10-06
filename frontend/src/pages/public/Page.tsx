import PublicLayout from "../../layouts/PublicLayout";
import SectionRenderer from "../../components/sections/SectionRenderer";
import type { PublicProps } from "../../types";
import { ApplicationForm } from "./Application";
export default function Page(data: PublicProps) {
  return (
    <PublicLayout page={data.page} preview={data.preview}>
      <div className={data.page.slug === "home" ? "home-sections" : undefined}>
        {data.sections.map((section) => (
          <SectionRenderer key={section.id} section={section} data={data} />
        ))}
      </div>
      {data.page.slug === "home" && <ApplicationForm />}
    </PublicLayout>
  );
}
