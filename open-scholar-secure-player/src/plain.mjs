// Ordinary (unprotected) file support. Pure functions only, so they are unit-testable.
export const PLAIN_KINDS = {
  pdf: ['.pdf'],
  video: ['.mp4', '.webm'],
  image: ['.png', '.jpg', '.jpeg'],
  web: ['.html', '.htm']
};
export const PLAIN_ACCEPT = Object.values(PLAIN_KINDS).flat().join(',');
export const UNSUPPORTED_MESSAGE = 'This file type cannot be opened here yet. Try a PDF, an MP4 or WebM video, a PNG or JPG picture, or a saved web page (.html).';

export function plainKind(name = '') {
  const lower = String(name).toLowerCase();
  const dot = lower.lastIndexOf('.');
  if (dot < 0) return null;
  const extension = lower.slice(dot);
  for (const [kind, extensions] of Object.entries(PLAIN_KINDS)) {
    if (extensions.includes(extension)) return kind;
  }
  return null;
}

// Stable key for remembering where someone stopped reading or watching (stored only on this device).
export function resumeKey(file) {
  return `ossp-place:${file.name}:${file.size}:${file.lastModified}`;
}
