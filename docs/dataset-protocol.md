# Private evaluation dataset protocol

## Collection and consent

- Use images with documented permission for private evaluation.
- Record the source collection, license/use restriction, collection period, and annotator process outside filenames.
- Remove EXIF metadata before import. Do not encode farmer names, phone numbers, farms, or coordinates in filenames or CSV fields.
- Keep the source directory private and access-controlled.

## Ground truth

- Use a qualified agronomist or a documented laboratory/field standard.
- Resolve crop and disease to AgroAide canonical labels before import.
- Record concise provenance per item, such as `agronomist consensus; two reviewers; 2026-07-12`.
- Use an empty disease only for confirmed healthy examples. Uncertain examples belong in a separately adjudicated dataset.

## CSV contract

UTF-8 CSV headers are:

```text
external_id,image,crop,disease,provenance
```

`image` must be a relative path within the supplied private image directory. Supported real image types are JPEG, PNG, and WebP. Duplicate external IDs, unknown labels, missing provenance, path traversal, unsupported image bytes, and empty datasets reject the complete import.

## Integrity and lifecycle

The importer copies images to private storage, computes SHA-256 checksums for the CSV and every image, and locks the dataset after a successful atomic import. Locked items and metadata cannot be edited. Corrections require a new dataset version. Do not delete a dataset referenced by a run.
