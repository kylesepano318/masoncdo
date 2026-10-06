import { useState, useEffect } from "react";

export default function YouTubeEmbed({
  url,
  title = "YouTube video",
}: {
  url?: string;
  title?: string;
}) {
  const [playing, setPlaying] = useState(false);
  const [thumbnailFailed, setThumbnailFailed] = useState(false);
  useEffect(() => {
    setPlaying(false);
    setThumbnailFailed(false);
  }, [url]);
  if (!url) return null;
  let id: string | null = null;
  let start = 0;
  try {
    const parsed = new URL(url);
    if (parsed.protocol !== "https:") return null;
    if (
      [
        "youtube.com",
        "www.youtube.com",
        "youtube-nocookie.com",
        "www.youtube-nocookie.com",
      ].includes(parsed.hostname)
    ) {
      id =
        parsed.pathname === "/watch"
          ? parsed.searchParams.get("v")
          : parsed.pathname.match(/^\/embed\/([\w-]{11})$/)?.[1] || null;
    } else if (parsed.hostname === "youtu.be") id = parsed.pathname.slice(1);
    const time =
      parsed.searchParams.get("start") || parsed.searchParams.get("t") || "0";
    if (/^\d+s?$/.test(time)) start = parseInt(time, 10);
  } catch {
    return null;
  }
  if (!id || !/^[\w-]{11}$/.test(id)) return null;
  return (
    <div className="youtube-video">
      <div className="youtube-frame">
        {playing ? (
          <iframe
            src={`https://www.youtube-nocookie.com/embed/${id}?autoplay=1${start ? `&start=${start}` : ""}`}
            title={title}
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowFullScreen
            referrerPolicy="strict-origin-when-cross-origin"
          />
        ) : (
          <button
            type="button"
            className="youtube-poster"
            aria-label={`Play ${title}`}
            onClick={() => setPlaying(true)}
          >
            {!thumbnailFailed && (
              <img
                src={`https://i.ytimg.com/vi/${id}/hqdefault.jpg`}
                alt=""
                loading="lazy"
                decoding="async"
                width={480}
                height={360}
                onError={() => setThumbnailFailed(true)}
              />
            )}
            <span className="youtube-play" aria-hidden="true">
              ▶ Play video
            </span>
          </button>
        )}
      </div>
      <a
        href={`https://www.youtube.com/watch?v=${id}${start ? `&t=${start}s` : ""}`}
        target="_blank"
        rel="noopener noreferrer"
      >
        Watch on YouTube
      </a>
    </div>
  );
}
