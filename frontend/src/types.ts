export interface Position {
  id: number;
  name: string;
  slug: string;
  rank: number;
  is_officer: boolean;
}
export interface PublicMember {
  id: number;
  name: string;
  profile_photo: string | null;
  biography: string | null;
  member_since: string | null;
  position: Position;
}
export interface MediaItem {
  mime_type?: string;
  resource_type?: string;
  id: number;
  path: string;
  original_name: string;
  alt_text: string;
  caption: string | null;
}
export type SectionItem = {
  year?: string;
  title?: string;
  description?: string;
  image?: string;
  url?: string;
};
export type SectionSettings = {
  lodge_hero?: boolean;
  layout?: "half" | "documents";
  display_mode?: "preview" | "all";
  eyebrow?: string;
  background?: string;
  text_color?: string;
  background_image?: string;
  background_position?: string;
  background_fit?: "cover" | "contain";
  video_url?: string;
  video_title?: string;
  button_text?: string;
  button_url?: string;
  caption?: string;
  alt?: string;
  use_lodge_emblem?: boolean;
  style?: string;
  height?: number;
  overlay_opacity?: number;
  alignment?: "left" | "center" | "right";
  full_width?: boolean;
  items?: SectionItem[];
  reverse?: boolean;
};
export interface Section {
  id: number;
  section_type: string;
  title: string | null;
  subtitle: string | null;
  body: string | null;
  image: string | null;
  mobile_image: string | null;
  settings: SectionSettings | null;
  display_order: number;
  is_visible: boolean;
}
export interface PageData {
  id?: number;
  name: string;
  slug: string;
  meta_title: string | null;
  meta_description: string | null;
  seo?: { og_title?: string; og_description?: string; og_image?: string };
  sections?: Section[];
}
export interface Affiliation {
  id: number;
  name: string;
  logo: string | null;
  subtitle: string | null;
  description: string | null;
  website_url: string | null;
  display_order: number;
  is_visible: boolean;
}
export interface Celebration {
  id: number;
  title: string;
  category: string;
  category_label?: string;
  event_date: string;
  location: string | null;
  description: string | null;
  image: string | null;
  gallery: SectionItem[] | null;
  member_id?: number | null;
  is_public?: boolean;
}
export interface CelebrationType {
  id: number;
  name: string;
  slug: string;
  celebrations_count: number;
}
export interface Shared {
  [key: string]: unknown;
  site: Record<string, Record<string, string>>;
  adminPages: { id: number; slug: string; name: string }[];
  auth: { user: { id: number; name: string; email: string; username?: string | null } | null };
  flash: { success?: string; reference?: string };
}
export interface PublicProps {
  page: PageData;
  sections: Section[];
  members: PublicMember[];
  affiliations: Affiliation[];
  celebrations: Celebration[];
  today: string;
  preview: boolean;
}
export interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
  total: number;
}
export interface AdminMember {
  id: number;
  first_name: string;
  middle_name: string | null;
  last_name: string;
  suffix: string | null;
  member_number: string | null;
  membership_position_id: number;
  profile_photo: string | null;
  member_since: string | null;
  biography: string | null;
  status: string;
  display_order: number;
  is_public: boolean;
  position?: Position;
}
export interface ApplicationRecord {
  id: number;
  reference_number: string;
  first_name: string;
  last_name: string;
  status: string;
  admin_notes: string | null;
  converted_at: string | null;
  converted_member_id: number | null;
  [key: string]: string | number | boolean | null;
}
