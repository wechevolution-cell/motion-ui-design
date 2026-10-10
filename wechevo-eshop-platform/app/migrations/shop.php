<?php
/* Per-shop schema. One file per tenant: data/shops/{id}.sqlite.
   Templates never write to these tables: they only read them. */
return [
1 => <<<SQL
CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT);

CREATE TABLE categories (
  id INTEGER PRIMARY KEY,
  parent_id INTEGER REFERENCES categories(id),
  name TEXT NOT NULL,
  slug TEXT NOT NULL,
  description TEXT,
  image TEXT,
  seo_title TEXT,
  seo_description TEXT,
  canonical_url TEXT,
  sort INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE UNIQUE INDEX categories_slug ON categories(ifnull(parent_id,0), slug) WHERE deleted_at IS NULL;

CREATE TABLE products (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL,
  sku TEXT,
  barcode TEXT,
  mpn TEXT,
  brand TEXT,
  short_description TEXT,
  description TEXT,
  price REAL NOT NULL DEFAULT 0,
  sale_price REAL,
  cost_price REAL,
  stock INTEGER NOT NULL DEFAULT 0,
  manage_stock INTEGER NOT NULL DEFAULT 1,
  weight REAL,
  length REAL,
  width REAL,
  height REAL,
  category_id INTEGER REFERENCES categories(id),
  status TEXT NOT NULL DEFAULT 'inactive',
  activated_at TEXT,
  featured INTEGER NOT NULL DEFAULT 0,
  seo_title TEXT,
  seo_description TEXT,
  canonical_url TEXT,
  robots TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT,
  deleted_at TEXT
);
CREATE UNIQUE INDEX products_slug ON products(slug) WHERE deleted_at IS NULL;
CREATE UNIQUE INDEX products_sku ON products(sku) WHERE deleted_at IS NULL AND sku IS NOT NULL AND sku <> '';
CREATE INDEX products_status ON products(status, deleted_at);
CREATE INDEX products_category ON products(category_id);
CREATE INDEX products_activated ON products(activated_at);
CREATE INDEX products_name ON products(name);

CREATE TABLE product_categories (
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  category_id INTEGER NOT NULL REFERENCES categories(id),
  PRIMARY KEY (product_id, category_id)
);
CREATE INDEX product_categories_cat ON product_categories(category_id);

CREATE TABLE media (
  id INTEGER PRIMARY KEY,
  path TEXT NOT NULL,
  mime TEXT,
  width INTEGER,
  height INTEGER,
  size INTEGER,
  alt TEXT,
  variants TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL
);

CREATE TABLE product_images (
  id INTEGER PRIMARY KEY,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  media_id INTEGER REFERENCES media(id),
  url TEXT,
  alt TEXT,
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX product_images_product ON product_images(product_id, sort);

CREATE TABLE product_variants (
  id INTEGER PRIMARY KEY,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  sku TEXT,
  options TEXT NOT NULL DEFAULT '{}',
  price REAL,
  stock INTEGER NOT NULL DEFAULT 0,
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX product_variants_product ON product_variants(product_id);

CREATE TABLE product_attributes (
  id INTEGER PRIMARY KEY,
  product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  value TEXT NOT NULL,
  sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX product_attributes_product ON product_attributes(product_id);

CREATE TABLE pages (
  id INTEGER PRIMARY KEY,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  body TEXT,
  seo_title TEXT,
  seo_description TEXT,
  status TEXT NOT NULL DEFAULT 'published',
  in_footer INTEGER NOT NULL DEFAULT 1,
  sort INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT
);

CREATE TABLE menus (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL);
CREATE TABLE menu_items (
  id INTEGER PRIMARY KEY,
  menu_id INTEGER NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
  parent_id INTEGER,
  label TEXT NOT NULL,
  type TEXT NOT NULL,
  ref_id INTEGER,
  url TEXT,
  sort INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE customers (
  id INTEGER PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  name TEXT,
  phone TEXT,
  password_hash TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE TABLE addresses (
  id INTEGER PRIMARY KEY,
  customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
  name TEXT, phone TEXT, street TEXT, city TEXT, postcode TEXT, region TEXT,
  is_default INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE orders (
  id INTEGER PRIMARY KEY,
  number TEXT NOT NULL UNIQUE,
  access_token TEXT NOT NULL,
  customer_id INTEGER REFERENCES customers(id),
  email TEXT NOT NULL,
  name TEXT NOT NULL,
  phone TEXT,
  street TEXT, city TEXT, postcode TEXT,
  region TEXT NOT NULL DEFAULT 'mainland',
  invoice_type TEXT NOT NULL DEFAULT 'receipt',
  company_name TEXT, vat_number TEXT, tax_office TEXT,
  customer_note TEXT,
  subtotal REAL NOT NULL,
  shipping REAL NOT NULL DEFAULT 0,
  fee REAL NOT NULL DEFAULT 0,
  total REAL NOT NULL,
  currency TEXT NOT NULL DEFAULT 'EUR',
  payment_method TEXT NOT NULL,
  payment_status TEXT NOT NULL DEFAULT 'pending',
  payment_ref TEXT,
  status TEXT NOT NULL DEFAULT 'new',
  shipping_status TEXT NOT NULL DEFAULT 'not_shipped',
  tracking_number TEXT,
  ip TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT
);
CREATE INDEX orders_created ON orders(created_at);
CREATE INDEX orders_status ON orders(status);

CREATE TABLE order_items (
  id INTEGER PRIMARY KEY,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  product_id INTEGER,
  variant_id INTEGER,
  name TEXT NOT NULL,
  sku TEXT,
  options TEXT,
  price REAL NOT NULL,
  qty INTEGER NOT NULL,
  total REAL NOT NULL
);
CREATE INDEX order_items_order ON order_items(order_id);

CREATE TABLE order_notes (
  id INTEGER PRIMARY KEY,
  order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  note TEXT NOT NULL,
  author TEXT,
  is_customer_visible INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE subscribers (
  id INTEGER PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  source TEXT,
  consent INTEGER NOT NULL DEFAULT 0,
  consent_text TEXT,
  ip TEXT,
  token TEXT NOT NULL,
  created_at TEXT NOT NULL,
  unsubscribed_at TEXT
);

CREATE TABLE contact_messages (
  id INTEGER PRIMARY KEY,
  name TEXT, email TEXT, phone TEXT, message TEXT,
  ip TEXT, read_at TEXT, created_at TEXT NOT NULL
);

CREATE TABLE feeds (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  type TEXT NOT NULL DEFAULT 'generic',
  filters TEXT NOT NULL DEFAULT '{}',
  fields TEXT NOT NULL DEFAULT '[]',
  frequency_minutes INTEGER NOT NULL DEFAULT 60,
  status TEXT NOT NULL DEFAULT 'active',
  last_generated_at TEXT,
  created_at TEXT NOT NULL
);
CREATE TABLE feed_logs (
  id INTEGER PRIMARY KEY,
  feed_id INTEGER NOT NULL REFERENCES feeds(id) ON DELETE CASCADE,
  items INTEGER NOT NULL DEFAULT 0,
  duration_ms INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL,
  message TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE imports (
  id INTEGER PRIMARY KEY,
  format TEXT NOT NULL,
  filename TEXT NOT NULL,
  stored_path TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'uploaded',
  total INTEGER NOT NULL DEFAULT 0,
  processed INTEGER NOT NULL DEFAULT 0,
  created INTEGER NOT NULL DEFAULT 0,
  updated INTEGER NOT NULL DEFAULT 0,
  skipped INTEGER NOT NULL DEFAULT 0,
  errors INTEGER NOT NULL DEFAULT 0,
  activated INTEGER NOT NULL DEFAULT 0,
  mapping TEXT NOT NULL DEFAULT '{}',
  options TEXT NOT NULL DEFAULT '{}',
  cursor TEXT,
  user_label TEXT,
  created_at TEXT NOT NULL,
  finished_at TEXT
);
CREATE TABLE import_rows (
  id INTEGER PRIMARY KEY,
  import_id INTEGER NOT NULL REFERENCES imports(id) ON DELETE CASCADE,
  row_no INTEGER NOT NULL,
  sku TEXT,
  status TEXT NOT NULL,
  message TEXT
);
CREATE INDEX import_rows_import ON import_rows(import_id);
CREATE TABLE import_logs (
  id INTEGER PRIMARY KEY,
  import_id INTEGER NOT NULL REFERENCES imports(id) ON DELETE CASCADE,
  message TEXT NOT NULL,
  created_at TEXT NOT NULL
);
SQL,
];
