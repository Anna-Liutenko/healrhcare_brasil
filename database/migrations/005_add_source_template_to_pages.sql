-- Migration: add source_template_slug to pages
-- Created: 2025-10-07

ALTER TABLE pages
  ADD COLUMN source_template_slug VARCHAR(255) NULL
  COMMENT 'Slug of static template from which page was imported' AFTER created_by;

CREATE INDEX idx_source_template ON pages(source_template_slug);
