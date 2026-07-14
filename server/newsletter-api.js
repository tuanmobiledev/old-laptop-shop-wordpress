import http from 'node:http';
import { createWriteStream } from 'node:fs';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import { execFile } from 'node:child_process';
import path from 'node:path';
import { pipeline } from 'node:stream/promises';
import { promisify } from 'node:util';
import { Pool } from 'pg';

const port = Number(process.env.PORT || 3000);
const adminToken = process.env.ADMIN_TOKEN || process.env.VITE_ADMIN_TOKEN || 'change-me-in-production';
const uploadDir = process.env.UPLOAD_DIR || '/data/uploads';
const publicUploadPath = process.env.PUBLIC_UPLOAD_PATH || '/uploads';
const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;
const MEDIA_TYPES = {
  'image/jpeg': '.webp',
  'image/png': '.webp',
  'image/webp': '.webp',
  'image/gif': '.webp',
  'video/mp4': '.mp4',
  'video/webm': '.webm',
  'video/quicktime': '.mov',
};
const IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
const execFileAsync = promisify(execFile);
const pool = new Pool({
  host: process.env.POSTGRES_HOST || 'laprevive-db',
  port: Number(process.env.POSTGRES_PORT || 5432),
  database: process.env.POSTGRES_DB || 'laprevive',
  user: process.env.POSTGRES_USER || 'laprevive',
  password: process.env.POSTGRES_PASSWORD || 'change_this_strong_password',
});

const getClientIp = (req) => {
  const forwarded = req.headers['x-forwarded-for'];
  return (forwarded ? forwarded.split(',')[0].trim() : req.socket.remoteAddress) || 'unknown';
};

const rateLimit = new Map();
const RATE_WINDOW_MS = 60_000;
const RATE_MAX = 3;

const isRateLimited = (ip) => {
  const now = Date.now();
  const entry = rateLimit.get(ip);
  if (!entry || now > entry.resetAt) {
    rateLimit.set(ip, { count: 1, resetAt: now + RATE_WINDOW_MS });
    return false;
  }
  entry.count += 1;
  return entry.count > RATE_MAX;
};

const sendJson = (res, status, body) => {
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store',
  });
  res.end(JSON.stringify(body));
};

const readBody = (req, limit = 8192) => new Promise((resolve, reject) => {
  let body = '';
  req.on('data', (chunk) => {
    body += chunk;
    if (body.length > limit) {
      reject(new Error('Payload too large'));
      req.destroy();
    }
  });
  req.on('end', () => resolve(body));
  req.on('error', reject);
});

const cleanToken = (value = '') => String(value).replace(/^Bearer\s+/i, '').trim();
const isAdminRequest = (req) => cleanToken(req.headers.authorization) === adminToken || cleanToken(req.headers['x-admin-token']) === adminToken;
const safeFileName = (name) => String(name || 'media').toLowerCase().normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd')
  .replace(/[^a-z0-9._-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80) || 'media';

const readUploadBuffer = (req) => new Promise((resolve, reject) => {
  const chunks = [];
  let received = 0;
  req.on('data', (chunk) => {
    received += chunk.length;
    if (received > MAX_UPLOAD_BYTES) {
      reject(new Error('Payload too large'));
      req.destroy();
      return;
    }
    chunks.push(chunk);
  });
  req.on('end', () => resolve(Buffer.concat(chunks)));
  req.on('error', reject);
});

const convertToWebp = async (input, output) => {
  await execFileAsync('cwebp', ['-quiet', '-q', '82', input, '-o', output], { timeout: 30_000 });
};

const saveUpload = async (req) => {
  const type = String(req.headers['content-type'] || '').split(';')[0].toLowerCase();
  const ext = MEDIA_TYPES[type];
  if (!ext) return { status: 415, body: { ok: false, error: 'unsupported_media_type' } };

  const length = Number(req.headers['content-length'] || 0);
  if (!length || length > MAX_UPLOAD_BYTES) return { status: 413, body: { ok: false, error: 'file_too_large' } };

  await mkdir(uploadDir, { recursive: true });
  const original = safeFileName(decodeURIComponent(String(req.headers['x-file-name'] || 'media')));
  const base = original.replace(/\.[a-z0-9]+$/i, '');
  const filename = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}-${base}${ext}`;
  const destination = path.join(uploadDir, filename);

  if (IMAGE_TYPES.has(type)) {
    const buffer = await readUploadBuffer(req);
    if (type === 'image/webp') {
      await writeFile(destination, buffer, { flag: 'wx' });
      return { status: 200, body: { ok: true, url: `${publicUploadPath}/${filename}` } };
    }

    const tempInput = path.join(uploadDir, `${filename}.source`);
    try {
      await writeFile(tempInput, buffer, { flag: 'wx' });
      await convertToWebp(tempInput, destination);
    } finally {
      await rm(tempInput, { force: true }).catch(() => {});
    }
    return { status: 200, body: { ok: true, url: `${publicUploadPath}/${filename}` } };
  }

  await pipeline(req, createWriteStream(destination, { flags: 'wx' }));
  return { status: 200, body: { ok: true, url: `${publicUploadPath}/${filename}` } };
};

const isEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

const ensureTable = async () => {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS newsletter_subscribers (
      id BIGSERIAL PRIMARY KEY,
      email TEXT NOT NULL UNIQUE,
      source TEXT NOT NULL DEFAULT 'footer',
      created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )
  `);
};

for (let attempt = 1; attempt <= 20; attempt += 1) {
  try {
    await ensureTable();
    break;
  } catch (error) {
    if (attempt === 20) throw error;
    console.warn(`Waiting for database (${attempt}/20)...`);
    await new Promise((resolve) => setTimeout(resolve, 1500));
  }
}

const server = http.createServer(async (req, res) => {
  if (req.method === 'GET' && req.url === '/health') {
    sendJson(res, 200, { ok: true });
    return;
  }

  if (req.method === 'POST' && req.url === '/api/upload') {
    if (!isAdminRequest(req)) {
      sendJson(res, 401, { ok: false, error: 'unauthorized' });
      return;
    }
    try {
      const result = await saveUpload(req);
      sendJson(res, result.status, result.body);
    } catch (error) {
      console.error('upload_failed', error);
      sendJson(res, 500, { ok: false, error: 'upload_failed' });
    }
    return;
  }

  if (req.method !== 'POST' || req.url !== '/api/newsletter') {
    sendJson(res, 404, { ok: false, error: 'not_found' });
    return;
  }

  const ip = getClientIp(req);
  if (isRateLimited(ip)) {
    sendJson(res, 429, { ok: false, error: 'too_many_requests' });
    return;
  }

  try {
    const payload = JSON.parse(await readBody(req) || '{}');
    const email = String(payload.email || '').trim().toLowerCase();
    if (!isEmail(email)) {
      sendJson(res, 400, { ok: false, error: 'invalid_email' });
      return;
    }

    await pool.query(
      `INSERT INTO newsletter_subscribers (email, source)
       VALUES ($1, $2)
       ON CONFLICT (email) DO UPDATE SET source = EXCLUDED.source`,
      [email, 'footer']
    );
    sendJson(res, 200, { ok: true });
  } catch (error) {
    console.error('newsletter_submit_failed', error);
    sendJson(res, 500, { ok: false, error: 'server_error' });
  }
});

server.listen(port, '0.0.0.0', () => {
  console.log(`Newsletter API listening on ${port}`);
});
