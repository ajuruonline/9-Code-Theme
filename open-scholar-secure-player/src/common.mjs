// Helpers shared by the player and the maker. Pure browser helpers, no encryption logic here.
export const $ = id => document.getElementById(id);

export function showMessage(text, error = false) {
  const box = $('message');
  box.textContent = text;
  box.classList.toggle('error', error);
  box.hidden = false;
  clearTimeout(showMessage.timer);
  showMessage.timer = setTimeout(() => { box.hidden = true; }, error ? 9000 : 5000);
}

export function setBusy(button, busy, text = 'Please wait…') {
  if (busy) {
    button.dataset.original = button.innerHTML;
    button.textContent = text;
    button.disabled = true;
  } else {
    button.innerHTML = button.dataset.original || button.innerHTML;
    button.disabled = false;
  }
}

export function saveFile(blob, name) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = name;
  document.body.append(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 60000);
}

export function safeName(name) {
  return name.replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]+/g, '_').slice(0, 70) || 'Open_Scholar_Lesson';
}

export function fileTitle(file) {
  return file.name.replace(/\.[^.]+$/, '').replaceAll('_', ' ').trim().slice(0, 200) || 'Open Scholar lesson';
}

export function localDate(date) {
  return new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
}

export function formatTime(seconds) {
  const total = Math.max(0, Math.floor(Number.isFinite(seconds) ? seconds : 0));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const rest = String(total % 60).padStart(2, '0');
  return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`;
}

export function formatDate(time) {
  if (!Number.isFinite(time)) return 'no set end date';
  return new Date(time).toLocaleString(undefined, {dateStyle: 'medium', timeStyle: 'short'});
}
