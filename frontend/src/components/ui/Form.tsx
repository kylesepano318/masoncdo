import type { ReactNode } from "react";
import { useId, Children, cloneElement, isValidElement } from "react";
export function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: ReactNode;
}) {
  const labelId = useId();
  return (
    <label className="field">
      <span id={labelId}>{label}</span>
      {Children.map(children, (child) =>
        isValidElement<Record<string, unknown>>(child) &&
        ["input", "select", "textarea"].includes(String(child.type))
          ? cloneElement(child, { "aria-labelledby": labelId })
          : child,
      )}
      {error && (
        <span className="field-error" role="alert">
          {error}
        </span>
      )}
    </label>
  );
}
export function Errors({ errors }: { errors: Record<string, string> }) {
  return Object.keys(errors).length ? (
    <div className="error-box" role="alert">
      {Object.entries(errors).map(([k, v]) => (
        <p key={k}>{v}</p>
      ))}
    </div>
  ) : null;
}
export function Checkbox({
  label,
  checked,
  onChange,
}: {
  label: string;
  checked: boolean;
  onChange: (v: boolean) => void;
}) {
  return (
    <label className="checkbox-label">
      <input
        type="checkbox"
        checked={checked}
        onChange={(e) => onChange(e.target.checked)}
      />
      <span>{label}</span>
    </label>
  );
}
