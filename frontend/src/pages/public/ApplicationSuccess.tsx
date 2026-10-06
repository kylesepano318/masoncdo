import { Link } from "../../lib/ui";
import PublicLayout from "../../layouts/PublicLayout";
import { Check } from "lucide-react";
export default function ApplicationSuccess({
  reference,
}: {
  reference: string;
}) {
  return (
    <PublicLayout>
      <section className="section success-section">
        <Check size={42} />
        <p className="eyebrow">APPLICATION RECEIVED</p>
        <h1>Thank you for introducing yourself.</h1>
        <p>
          Your application has been received for private review by our lodge
          administrator.
        </p>
        <p className="eyebrow">YOUR REFERENCE NUMBER</p>
        <strong className="reference-number">{reference}</strong>
        <p>Please keep this reference for your records.</p>
        <Link href="/" className="ceremonial-button">
          Return home
        </Link>
      </section>
    </PublicLayout>
  );
}
