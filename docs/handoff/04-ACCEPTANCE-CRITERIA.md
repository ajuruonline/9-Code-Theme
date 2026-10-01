# Acceptance Criteria

A release is acceptable only when all are true:

1. Open an existing post in Gutenberg on a phone-width viewport. Post title/content can be edited immediately.
2. Open metadata/plugin fields. They are visible and interactive.
3. Save/Update/Publish is visible without opening the hamburger.
4. The 9CODE hamburger is visible when enabled.
5. Opening the hamburger shows a bounded popover; the rest of the editor remains visible around it.
6. Closing the hamburger removes the popover completely; no transparent/white click-blocking layer remains.
7. Body scrolling is not locked merely because Editor Tools exists.
8. Classic Editor content textarea and meta boxes remain editable.
9. 9 Data -> Post Editor shows explicit **Post Content** and **Post Metadata** tabs.
10. Normal WordPress post edit screen shows **9 Data - Post Content & Metadata** recovery meta box when 9 Data Manager is active.
11. WordPress Media Library modal still works.
12. ACF/Rank Math/other provider fields retain native save behavior.
13. Reloading after an upgrade from 15.0.0/15.0.1 clears stale overlay classes/nodes.
14. No 9CODE post-editor CSS/JS creates a viewport-sized white shell/backdrop.
15. No fatal error occurs with Conference.Lat/provider plugins active.
