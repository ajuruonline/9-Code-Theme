// Ordinary (unprotected) file support. Pure functions only, so they are unit-testable.
export const PLAIN_KINDS = {
  pdf: ['.pdf'],
  video: ['.mp4', '.webm'],
  audio: ['.mp3', '.m4a', '.wav', '.ogg', '.oga'],
  image: ['.png', '.jpg', '.jpeg'],
  web: ['.html', '.htm'],
  text: ['.txt', '.md']
};
// Word, PowerPoint and spreadsheet files cannot be shown in a browser. They can be kept in a module as a copy.
export const OFFICE_EXTENSIONS = ['.doc', '.docx', '.ppt', '.pptx', '.xls', '.xlsx', '.odt', '.odp', '.ods', '.rtf'];
export const PLAYABLE_KINDS = Object.keys(PLAIN_KINDS);
export const PLAIN_ACCEPT = Object.values(PLAIN_KINDS).flat().join(',');
export const UNSUPPORTED_MESSAGE = 'This file type cannot be opened here yet. Try a PDF, an MP4 or WebM video, an MP3, M4A, WAV or OGG audio file, a PNG or JPG picture, a text file (.txt or .md), or a saved web page (.html). Word and PowerPoint files can be kept in a module as copies, after saving them as PDF.';

function extensionOf(name = '') {
  const lower = String(name).toLowerCase();
  const dot = lower.lastIndexOf('.');
  return dot < 0 ? '' : lower.slice(dot);
}

export function plainKind(name = '') {
  const extension = extensionOf(name);
  for (const [kind, extensions] of Object.entries(PLAIN_KINDS)) {
    if (extensions.includes(extension)) return kind;
  }
  return null;
}

// Kind used inside a module: playable kinds, Office documents, or anything else kept as a copy.
export function moduleKind(name = '') {
  const playable = plainKind(name);
  if (playable) return playable;
  return OFFICE_EXTENSIONS.includes(extensionOf(name)) ? 'office' : 'other';
}

// Stable key for remembering where someone stopped reading or watching (stored only on this device).
export function resumeKey(file) {
  return `ossp-place:${file.name}:${file.size}:${file.lastModified}`;
}
