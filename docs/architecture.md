# AgroAide diagnosis architecture

Crop scans are accepted by the authenticated API, validated from their real file signature, stored on the private disk, and persisted before `ProcessCropScan` is dispatched to the database queue. Mobile clients receive HTTP 202 and poll the owner-scoped scan detail endpoint.

`CropDiagnosisService` is the sole inference/parser/label-resolution path for production scans and evaluation runs. Every result records immutable model, prompt, and confidence-policy provenance, the provider's raw response and checksum, normalized confidence, canonical predicted/effective labels, latency, processing state, and verification state.

The verification policy is fixed at `<0.60 needs_retake`, `0.60–<0.85 pending_review`, and `>=0.85 auto_verified` only for canonical valid results. Farmer incorrect/unsure feedback disputes a scan. Expert transitions preserve the raw prediction in review history. Only canonical diseased scans in `auto_verified` or `expert_verified` can affect field health or outbreak aggregates. Legacy scans default to `legacy_ineligible`.

Staff access uses Laravel's server-side session, CSRF middleware, throttled login, local Vite/Tailwind assets, and role middleware. Agronomists can read aggregate metrics and review scans. Only administrators can queue evaluation runs, create/activate immutable confidence-policy versions, assign roles, and view audit details. Private dataset files remain importable only through the authenticated Artisan workflow; dataset metadata and import protocol are available in the dashboard. No staff credentials are seeded or read from environment variables.

Queue and scheduler health is persisted without exception messages, request bodies, GPS, phone numbers, or email addresses. The staff overview displays only outbreak aggregates with at least three distinct farms.

The active-farm metric counts distinct users with a scan, journal entry, completed calendar task, or transaction in the last 30 days. Counts below three are suppressed. Farmer scan feedback is throttled and stored as one current record per user/scan; repeat submissions update that record without inflating feedback totals.
