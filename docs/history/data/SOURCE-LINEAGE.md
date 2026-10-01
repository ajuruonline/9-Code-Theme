# 9.10 Data Edition — Source Lineage

9.10 Data Edition is a product-facing rename and workflow redesign built directly from the verified 9.55 Ultron Data v9.55.4 release. Internal legacy constants/classes/module paths are intentionally retained where they protect WordPress upgrade and data compatibility.

## Bundled engines preserved

- 9Code ACF Data Engine 0.16.0
- 9 Post Manager 4.0.0
- 9 Category Manager 4.0.0
- Nine AI Manager 2.0.0

## 9.10 addition

9Form is a thin form/output layer. It does not own post, ACF/meta, taxonomy or message storage. Post create/edit operations call 9 Post Manager public safety bridges; data submissions call Data Engine atomic import/version capture; author messages use WordPress mail delivery.

---

# 9.55 Ultron Data — Source Lineage

This release consolidates the following verified source packages without renaming their established storage keys:

| Engine | Source version | Original package |
|---|---:|---|
| Data | 0.16.0 | ninecode-acf-data-engine-v0.16.0.zip |
| Posts | 4.0.0 | 9-post-manager-v4.0.0.zip |
| Categories | 4.0.0 | 9-category-manager-v4.0.0.zip |
| AI | 2.0.0 | nine-ai-manager-v2.0.0.zip |

## Compatibility principle

The engines are bundled as internal modules but retain their existing class names, WordPress hooks, option keys, metadata keys and database storage. The Ultron shell owns product navigation and cross-engine routing; it does not copy content into a fifth data store.
