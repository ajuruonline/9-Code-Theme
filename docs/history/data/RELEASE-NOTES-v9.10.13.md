# 9 Data Manager 9.10.13 - Post Content and Metadata Recovery

## Main correction

Post Content and Post Metadata are now first-class editing surfaces in 9 Data Manager Post Editor.

- **Post Content** exposes the real WordPress `post_content` value directly, with Gutenberg block markup preserved.
- **Post Metadata** lists all stored post meta for the selected post.
- Ordinary public/custom meta can be edited directly.
- Private/system meta remains visible for diagnosis but is protected from unsafe raw editing.
- Metadata already owned by 9 Data Site Fields, ACF or a provider/plugin control is shown read-only in the raw metadata view so its dedicated field owner remains authoritative.

## Normal WordPress post screen recovery

Data Manager now also registers a high-priority **9 Data - Post Content & Metadata** recovery box on normal WordPress post editing screens.

The recovery layer:

- exposes the actual saved `post_content`;
- exposes ordinary post meta for direct editing;
- shows private/system metadata read-only;
- provides an explicit **Save Post Content & Metadata** action using the existing 9 Post Editor permission, sanitization and recovery-snapshot path;
- provides **Restore Native Editor Visibility**;
- automatically removes only stale 9CODE full-screen/focus classes and backdrops from earlier editor-shell experiments;
- does not force open fields intentionally hidden by WordPress or their owning provider plugin.

## Version alignment

- 9 Data Manager: **9.10.13**
- Embedded 9 Post Editor: **4.0.2**

No CPT, taxonomy, stored-option, form, backup, AI/9CF, or provider data contract was renamed.
