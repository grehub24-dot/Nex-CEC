-- ============================================================
-- fix_khanacademy_embed.sql
-- 
-- Khan Academy uses Fastly Shield (bot/DDoS protection) which
-- requires real origin cookies. Embedded iframes get a unique
-- origin (sandbox) or inherit the parent's origin -- neither
-- works with Fastly's challenge page.
--
-- Since the iframe viewer can't pass the Fastly challenge,
-- all Khan Academy resources must use 'redirect' embed type.
--
-- Run this if you've already applied migrate_resource_links.sql
-- or migrate_resource_links_v2.sql and want to fix existing rows.
-- ============================================================

UPDATE resource_links
SET embed_type = 'redirect'
WHERE source = 'khanacademy'
  AND embed_type = 'iframe';
