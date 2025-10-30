-- Migration: add card_image column to pages
-- Date: 2025-10-30

ALTER TABLE pages
  ADD COLUMN IF NOT EXISTS card_image VARCHAR(512) NULL;

-- End migration
