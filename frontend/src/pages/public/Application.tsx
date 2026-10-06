import { useApiForm, navigate } from "../../lib/ui";
import PublicLayout from "../../layouts/PublicLayout";
import SectionRenderer from "../../components/sections/SectionRenderer";
import { Field, Checkbox, Errors } from "../../components/ui/Form";
import type { PublicProps } from "../../types";
import { ArrowUpRight, LockKeyhole } from "lucide-react";
export function ApplicationForm() {
  const form = useApiForm({
    first_name: "",
    middle_name: "",
    last_name: "",
    suffix: "",
    date_of_birth: "",
    place_of_birth: "",
    civil_status: "",
    occupation: "",
    employer: "",
    complete_address: "",
    city: "",
    province: "",
    postal_code: "",
    mobile_number: "",
    email: "",
    reason_for_joining: "",
    how_did_you_hear: "",
    has_referrer: false,
    referring_member_name: "",
    declaration: false,
    consent: false,
    website: "",
  });
  type Key = keyof typeof form.data;
  const input = (name: Key, label: string, type = "text", required = false) => (
    <Field label={label + (required ? " *" : "")} error={form.errors[name]}>
      <input
        name={name}
        type={type}
        required={required}
        value={String(form.data[name])}
        onChange={(e) => form.setData(name, e.target.value as never)}
        maxLength={name === "mobile_number" ? 30 : 255}
        autoComplete={
          name === "first_name"
            ? "given-name"
            : name === "last_name"
              ? "family-name"
              : name === "email"
                ? "email"
                : undefined
        }
      />
    </Field>
  );
  return (
    <>
      <div className="application-intro">
        <p className="eyebrow">AN INTRODUCTION TO GOLDEN FRIENDSHIP</p>
        <h2>Tell us about yourself.</h2>
        <p>
          Please complete the form below. Your information will be reviewed
          privately by the lodge administrator.
        </p>
        <p className="privacy-note">
          <LockKeyhole size={15} /> Your details are kept confidential. Required
          fields are marked *.
        </p>
      </div>
      <form
        className="application-form"
        onSubmit={(e) => {
          e.preventDefault();
          form.post("/applications", {
            onSuccess: (data) =>
              navigate("/application/received", {
                reference: data.reference_number,
              }),
          });
        }}
      >
        <Errors errors={form.errors} />
        <fieldset>
          <legend>
            <span>01</span> Personal information
          </legend>
          <div className="form-grid">
            {input("first_name", "First name", "text", true)}
            {input("middle_name", "Middle name")}
            {input("last_name", "Last name", "text", true)}
            {input("suffix", "Suffix")}
            {input("date_of_birth", "Date of birth", "date", true)}
            {input("place_of_birth", "Place of birth")}
            <Field label="Civil status" error={form.errors.civil_status}>
              <select
                value={form.data.civil_status}
                onChange={(e) => form.setData("civil_status", e.target.value)}
              >
                <option value="">Select status</option>
                {["single", "married", "widowed", "separated"].map((s) => (
                  <option key={s} value={s}>
                    {s}
                  </option>
                ))}
              </select>
            </Field>
            {input("occupation", "Occupation / profession")}
            {input("employer", "Employer / business")}
          </div>
        </fieldset>
        <fieldset>
          <legend>
            <span>02</span> Contact information
          </legend>
          <Field
            label="Complete address *"
            error={form.errors.complete_address}
          >
            <textarea
              required
              value={form.data.complete_address}
              onChange={(e) => form.setData("complete_address", e.target.value)}
              maxLength={1000}
            />
          </Field>
          <div className="form-grid">
            {input("city", "City / municipality", "text", true)}
            {input("province", "Province", "text", true)}
            {input("postal_code", "Postal code")}
            {input("mobile_number", "Mobile number", "tel", true)}
            {input("email", "Email address", "email", true)}
          </div>
        </fieldset>
        <fieldset>
          <legend>
            <span>03</span> Application information
          </legend>
          <Field
            label="Why do you wish to join? *"
            error={form.errors.reason_for_joining}
          >
            <textarea
              required
              rows={5}
              value={form.data.reason_for_joining}
              onChange={(e) =>
                form.setData("reason_for_joining", e.target.value)
              }
              maxLength={5000}
            />
          </Field>
          <Field label="How did you learn about the lodge?">
            <select
              value={form.data.how_did_you_hear}
              onChange={(e) => form.setData("how_did_you_hear", e.target.value)}
            >
              <option value="">Select an option</option>
              <option value="member">A lodge member</option>
              <option value="social_media">Social media</option>
              <option value="website">Website</option>
              <option value="event">An event</option>
              <option value="other">Other</option>
            </select>
          </Field>
          <Field label="Were you referred by an existing member?">
            <select
              value={form.data.has_referrer ? "yes" : "no"}
              onChange={(e) =>
                form.setData("has_referrer", e.target.value === "yes")
              }
            >
              <option value="no">No</option>
              <option value="yes">Yes</option>
            </select>
          </Field>
          {form.data.has_referrer &&
            input("referring_member_name", "Referring member", "text", true)}
        </fieldset>
        <fieldset>
          <legend>
            <span>04</span> Declaration
          </legend>
          <Checkbox
            label="I certify that the information I have provided is true and correct."
            checked={form.data.declaration}
            onChange={(v) => form.setData("declaration", v)}
          />
          <Checkbox
            label="I consent to the collection and processing of my personal information for purposes connected with this membership application."
            checked={form.data.consent}
            onChange={(v) => form.setData("consent", v)}
          />
          <div className="honeypot" aria-hidden="true">
            <input
              tabIndex={-1}
              name="website"
              value={form.data.website}
              onChange={(e) => form.setData("website", e.target.value)}
            />
          </div>
        </fieldset>
        <button className="ceremonial-button" disabled={form.processing}>
          {form.processing ? "Submitting…" : "Submit application"}
          <ArrowUpRight size={16} />
        </button>
      </form>
    </>
  );
}

export default function Application(props: PublicProps) {
  return (
    <PublicLayout page={props.page} preview={props.preview}>
      {props.sections.map((section) => (
        <SectionRenderer key={section.id} section={section} data={props} />
      ))}
      <ApplicationForm />
    </PublicLayout>
  );
}
