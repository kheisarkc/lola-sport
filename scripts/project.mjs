// Perintah proyek: npm run setup | dev | test | check | test:e2e | db:* | api -- <artisan>
// Ditulis dengan Node supaya sama di Windows, macOS, Linux, dan GitHub Actions.

import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const runtime = join(root, '.runtime');
mkdirSync(runtime, { recursive: true });

// PHP bisa diatur lewat LOLA_PHP atau .runtime/tools.json; selain itu memakai `php` dari PATH.
const tools = existsSync(join(runtime, 'tools.json')) ? JSON.parse(readFileSync(join(runtime, 'tools.json'), 'utf8')) : {};
const php = process.env.LOLA_PHP || tools.php || 'php';
const composer = process.env.LOLA_COMPOSER || tools.composer;
const docker = tools.docker || 'docker';
const env = { ...process.env, PLAYWRIGHT_BROWSERS_PATH: process.env.PLAYWRIGHT_BROWSERS_PATH || join(runtime, 'browsers') };
if (existsSync(join(runtime, 'php.ini'))) env.PHPRC = join(runtime, 'php.ini');

const API_PORT = 8001;
const E2E_PORT = Number(process.env.LOLA_E2E_PORT || 8002);

function run(command, args, extra = {}) {
  const result = spawnSync(command, args, { cwd: root, env, stdio: 'inherit', windowsHide: true, ...extra });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(`${command} ${args[0] ?? ''} gagal (kode ${result.status}).`);
}
const artisan = (...args) => run(php, ['artisan', ...args]);
const compose = (...args) => run(docker, ['compose', ...args]);

function readEnv(file) {
  if (!existsSync(file)) return {};
  const pairs = readFileSync(file, 'utf8').split(/\r?\n/).filter(line => /^[A-Z_][A-Z0-9_]*=/.test(line));
  return Object.fromEntries(pairs.map(line => {
    const at = line.indexOf('=');
    return [line.slice(0, at), line.slice(at + 1).replace(/^"|"$/g, '')];
  }));
}

/** Salin template lalu ganti nilai tertentu. */
function writeEnv(target, template, values) {
  let text = readFileSync(template, 'utf8');
  for (const [key, value] of Object.entries(values)) {
    const pattern = new RegExp(`^${key}=.*$`, 'm');
    text = pattern.test(text) ? text.replace(pattern, `${key}=${value}`) : `${text.trimEnd()}\n${key}=${value}\n`;
  }
  writeFileSync(target, text);
}

/** Membuat .env (aplikasi) dan .env.testing (tes) bila belum ada. Tidak menimpa yang sudah ada. */
function configure() {
  const main = join(root, '.env');
  if (!existsSync(main)) {
    writeEnv(main, join(root, '.env.example'), { DB_PASSWORD: randomBytes(18).toString('hex') });
    artisan('key:generate', '--no-interaction');
  }
  const testing = join(root, '.env.testing');
  if (!existsSync(testing)) {
    writeEnv(testing, main, { APP_ENV: 'testing', DB_DATABASE: 'lola_demo_test', CACHE_STORE: 'array', SESSION_DRIVER: 'array', MAIL_MAILER: 'array' });
  }
}

function startDatabase() {
  // Di GitHub Actions database sudah disediakan sebagai service.
  if (process.env.LOLA_DATABASE_EXTERNAL !== '1') compose('up', '-d', '--wait');
}

function requireSetup() {
  for (const file of ['.env', 'vendor/autoload.php']) {
    if (!existsSync(join(root, file))) throw new Error(`File ${file} belum ada. Jalankan npm run setup dulu (lihat docs/SETUP.md).`);
  }
}

function serve(port, extraEnv = {}) {
  const server = spawn(php, ['artisan', 'serve', '--host=127.0.0.1', `--port=${port}`, '--no-reload'], {
    cwd: root, env: { ...env, ...extraEnv }, stdio: 'inherit', windowsHide: true,
  });
  const stop = () => {
    if (server.exitCode !== null) return;
    if (process.platform === 'win32') spawnSync('taskkill', ['/PID', String(server.pid), '/T', '/F'], { stdio: 'ignore' });
    else server.kill();
  };
  process.on('SIGINT', () => { stop(); process.exit(0); });
  process.on('SIGTERM', () => { stop(); process.exit(0); });
  server.on('exit', code => { process.exitCode = code ?? 1; });
}

const commands = {
  setup() {
    if (!existsSync(join(root, 'vendor/autoload.php'))) {
      composer ? run(php, [composer, 'install', '--no-interaction']) : run('composer', ['install', '--no-interaction'], { shell: true });
    }
    configure();
    startDatabase();
    artisan('migrate', '--no-interaction');
    artisan('db:seed', '--no-interaction');
    console.log(`\nSetup selesai. Jalankan "npm run dev" lalu buka http://127.0.0.1:${API_PORT}`);
  },

  dev() {
    requireSetup();
    startDatabase();
    // dev tidak menjalankan migrasi. Bila ada migrasi baru, jalankan npm run db:migrate secara sadar.
    try {
      artisan('migrate:status', '--pending=1', '--no-interaction');
    } catch {
      throw new Error('Database belum siap atau ada migrasi baru. Periksa pesan di atas, lalu jalankan npm run db:migrate.');
    }
    console.log(`Lola Sport: http://127.0.0.1:${API_PORT}`);
    serve(API_PORT);
  },

  test(args) {
    configure();
    startDatabase();
    artisan('test', ...args);
  },

  check() {
    configure();
    startDatabase();
    run(php, ['vendor/bin/pint', '--test']);
    run(php, ['vendor/bin/phpstan', 'analyse', '--memory-limit=1G', '--no-progress']);
    artisan('test');
  },

  'test:e2e'(args) {
    run(process.execPath, [join(root, 'node_modules/@playwright/test/cli.js'), 'test', ...args]);
  },

  /** Dipanggil Playwright: database tes dikosongkan lalu server khusus tes dijalankan di port 8002. */
  'e2e:serve'() {
    configure();
    startDatabase();
    artisan('migrate:fresh', '--seed', '--env=testing', '--no-interaction');
    const e2eEnv = join(root, '.env.e2e');
    const key = readEnv(e2eEnv).APP_KEY || `base64:${randomBytes(32).toString('base64')}`;
    writeEnv(e2eEnv, join(root, '.env.testing'), {
      APP_ENV: 'e2e', APP_KEY: key, APP_URL: `http://127.0.0.1:${E2E_PORT}`,
      SESSION_DRIVER: 'file', SESSION_COOKIE: 'lola_demo_e2e_session', CACHE_STORE: 'file',
    });
    const storage = join(runtime, 'e2e-storage');
    for (const folder of ['app/private', 'framework/sessions', 'framework/views', 'framework/cache/data', 'logs']) {
      mkdirSync(join(storage, folder), { recursive: true });
    }
    serve(E2E_PORT, { APP_ENV: 'e2e', LARAVEL_STORAGE_PATH: storage });
  },

  'db:up': () => compose('up', '-d', '--wait'),
  'db:stop': () => compose('stop'),
  'db:migrate': () => { requireSetup(); startDatabase(); artisan('migrate', '--no-interaction'); },
  'db:seed': () => { requireSetup(); startDatabase(); artisan('db:seed', '--no-interaction'); },
  'db:status': () => { requireSetup(); startDatabase(); artisan('migrate:status'); },

  /** Menghapus semua data demo lokal lalu mengisi ulang. Harus diminta eksplisit dengan --yes. */
  'db:reset'(args) {
    if (!args.includes('--yes')) throw new Error('Ini menghapus semua data di database lola_demo lokal. Ulangi dengan: npm run db:reset -- --yes');
    requireSetup();
    startDatabase();
    artisan('migrate:fresh', '--seed', '--no-interaction');
  },

  api: args => artisan(...args),
};

const [name = 'help', ...args] = process.argv.slice(2);
try {
  if (!commands[name]) throw new Error(`Perintah tidak dikenal: ${name}. Pilihan: ${Object.keys(commands).join(', ')}`);
  await commands[name](args);
} catch (error) {
  console.error(`\n${error.message}`);
  process.exitCode = 1;
}
