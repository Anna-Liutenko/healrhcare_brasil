-- Minimal sqlite schema for integration tests

CREATE TABLE IF NOT EXISTS users (
  id TEXT PRIMARY KEY,
  username TEXT,
  email TEXT,
  password_hash TEXT,
  role TEXT,
  is_active INTEGER DEFAULT 1,
  created_at TEXT,
  last_login_at TEXT
);

CREATE TABLE IF NOT EXISTS sessions (
  id TEXT PRIMARY KEY,
  user_id TEXT,
  expires_at TEXT
);

CREATE TABLE IF NOT EXISTS pages (
  id TEXT PRIMARY KEY,
  title TEXT,
  slug TEXT UNIQUE,
  status TEXT,
  type TEXT,
  collection_config TEXT,
  seo_title TEXT,
  seo_description TEXT,
  seo_keywords TEXT,
  page_specific_code TEXT,
  show_in_menu INTEGER,
  menu_title TEXT,
  rendered_html TEXT,
  show_in_sitemap INTEGER,
  menu_order INTEGER,
  created_at TEXT,
  updated_at TEXT,
  published_at TEXT,
  trashed_at TEXT,
  created_by TEXT,
  source_template_slug TEXT
);

CREATE TABLE IF NOT EXISTS blocks (
  id TEXT PRIMARY KEY,
  page_id TEXT,
  type TEXT,
  position INTEGER,
  data TEXT,
  custom_name TEXT,
  client_id TEXT,
  created_at TEXT,
  updated_at TEXT
);

