import { useEffect, useRef } from "react";
import { X } from "lucide-react";
export default function Lightbox({
  image,
  alt,
  onClose,
}: {
  image: string;
  alt: string;
  onClose: () => void;
}) {
  const ref = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    ref.current?.showModal();
    const current = ref.current;
    return () => current?.close();
  }, []);
  return (
    <dialog
      className="lightbox"
      ref={ref}
      onCancel={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <button autoFocus aria-label="Close image" onClick={onClose}>
        <X />
      </button>
      <img src={image} alt={alt} />
      <p>{alt}</p>
    </dialog>
  );
}
