<?php
/* Platform (control-plane) schema. One file: data/platform.sqlite.
   Every business limit (prices, quotas, durations, grace days) lives in these tables, never in code. */
return [
1 => <<<SQL
CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT);

CREATE TABLE subscription_plans (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  price REAL NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'EUR',
  setup_fee REAL NOT NULL DEFAULT 0,
  active_product_limit INTEGER NOT NULL,
  stored_product_limit INTEGER NOT NULL DEFAULT 10000,
  staff_limit INTEGER NOT NULL DEFAULT 5,
  storage_mb INTEGER NOT NULL DEFAULT 2048,
  duration_options TEXT NOT NULL DEFAULT '[1,3,6,12]',
  features TEXT NOT NULL DEFAULT '{}',
  status TEXT NOT NULL DEFAULT 'active',
  sort INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT
);

CREATE TABLE templates (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT,
  status TEXT NOT NULL DEFAULT 'available',
  sort INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE shops (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  owner_name TEXT,
  email TEXT,
  phone TEXT,
  vat_number TEXT,
  address TEXT,
  hosting_provider TEXT,
  hosting_status TEXT NOT NULL DEFAULT 'ok',
  hosting_notes TEXT,
  plan_id INTEGER NOT NULL REFERENCES subscription_plans(id),
  template_code TEXT NOT NULL DEFAULT 't01',
  status TEXT NOT NULL DEFAULT 'PENDING',
  active_limit_override INTEGER,
  stored_limit_override INTEGER,
  subscription_start TEXT,
  subscription_expires TEXT,
  subscription_duration INTEGER,
  status_changed_at TEXT,
  notes TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE INDEX shops_status ON shops(status);
CREATE INDEX shops_expires ON shops(subscription_expires);

CREATE TABLE shop_domains (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id),
  domain TEXT NOT NULL UNIQUE,
  is_primary INTEGER NOT NULL DEFAULT 0,
  ssl_status TEXT NOT NULL DEFAULT 'pending',
  dns_status TEXT NOT NULL DEFAULT 'pending',
  created_at TEXT NOT NULL
);
CREATE INDEX shop_domains_shop ON shop_domains(shop_id);

CREATE TABLE users (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER REFERENCES shops(id),
  email TEXT NOT NULL,
  name TEXT,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL,
  permissions TEXT NOT NULL DEFAULT '[]',
  status TEXT NOT NULL DEFAULT 'active',
  failed_attempts INTEGER NOT NULL DEFAULT 0,
  locked_until TEXT,
  must_change_password INTEGER NOT NULL DEFAULT 0,
  last_login_at TEXT,
  created_by INTEGER,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE UNIQUE INDEX users_email_scope ON users(lower(email), ifnull(shop_id, 0)) WHERE deleted_at IS NULL;

/* Payment abstraction: provider -> payment -> subscription -> shop */
CREATE TABLE payment_providers (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  enabled INTEGER NOT NULL DEFAULT 0,
  config TEXT NOT NULL DEFAULT '{}'
);

CREATE TABLE subscriptions (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id),
  plan_id INTEGER NOT NULL REFERENCES subscription_plans(id),
  start_at TEXT NOT NULL,
  end_at TEXT NOT NULL,
  duration_months INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'active',
  source TEXT NOT NULL DEFAULT 'manual',
  external_ref TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX subscriptions_shop ON subscriptions(shop_id);

CREATE TABLE payments (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id),
  subscription_id INTEGER REFERENCES subscriptions(id),
  provider_code TEXT NOT NULL DEFAULT 'manual',
  type TEXT NOT NULL DEFAULT 'subscription',
  amount REAL NOT NULL DEFAULT 0,
  currency TEXT NOT NULL DEFAULT 'EUR',
  method TEXT,
  status TEXT NOT NULL DEFAULT 'paid',
  reference TEXT,
  paid_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX payments_shop ON payments(shop_id);

CREATE TABLE subscription_history (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER NOT NULL REFERENCES shops(id),
  action TEXT NOT NULL,
  plan_id INTEGER,
  duration_months INTEGER,
  start_at TEXT,
  end_at TEXT,
  actor_user_id INTEGER,
  note TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX sub_hist_shop ON subscription_history(shop_id);

CREATE TABLE notifications (
  id INTEGER PRIMARY KEY,
  shop_id INTEGER,
  type TEXT NOT NULL,
  level TEXT NOT NULL DEFAULT 'info',
  title TEXT NOT NULL,
  body TEXT,
  dedupe_key TEXT UNIQUE,
  audience TEXT NOT NULL DEFAULT 'superadmin',
  read_at TEXT,
  emailed_at TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX notifications_unread ON notifications(read_at);

CREATE TABLE audit_logs (
  id INTEGER PRIMARY KEY,
  actor_user_id INTEGER,
  actor_label TEXT,
  shop_id INTEGER,
  action TEXT NOT NULL,
  entity TEXT,
  entity_id TEXT,
  outcome TEXT NOT NULL DEFAULT 'ok',
  ip TEXT,
  meta TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX audit_shop ON audit_logs(shop_id, id);

CREATE TABLE impersonation_tokens (
  token_hash TEXT PRIMARY KEY,
  superadmin_id INTEGER NOT NULL,
  shop_id INTEGER NOT NULL,
  expires_at TEXT NOT NULL,
  used_at TEXT
);

CREATE TABLE rate_limits (
  key TEXT PRIMARY KEY,
  hits INTEGER NOT NULL,
  window_start INTEGER NOT NULL
);
SQL,
];
