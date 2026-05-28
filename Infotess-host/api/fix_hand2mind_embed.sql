-- =============================================================
-- Fix: hand2mind.com now sends X-Frame-Options: SAMEORIGIN
-- All hand2mind resources must use 'redirect' embed type.
-- Kiddoworksheets and PBS Kids remain unchanged.
-- =============================================================

-- Preview which rows will change
SELECT id, title, source, embed_type, url
FROM resource_links
WHERE source = 'hand2mind' AND embed_type = 'iframe';

-- Apply the fix
UPDATE resource_links
SET embed_type = 'redirect',
    updated_at = CURRENT_TIMESTAMP
WHERE source = 'hand2mind' AND embed_type = 'iframe';

-- Verify
SELECT id, title, source, embed_type
FROM resource_links
WHERE source = 'hand2mind';
