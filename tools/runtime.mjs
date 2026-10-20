/** Local process and browser utilities. Production does not depend on Node. */
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import net from 'node:net';
import { pathToFileURL } from 'node:url';

export const root = path.resolve(import.meta.dirname, '..');
export const env = Object.fromEntries(Object.entries(process.env).filter(([key]) => key.toLowerCase() !== 'path'));
env.PATH = process.env.Path || process.env.PATH || '';
env.TMP = env.TEMP = env.TMPDIR = path.join(root, 'storage', 'tmp');
process.env.TMP = process.env.TEMP = process.env.TMPDIR = env.TMP;
fs.mkdirSync(env.TMP, { recursive: true });
fs.mkdirSync(path.join(root, 'storage', 'logs'), { recursive: true });

export function phpArgs() {
  return process.platform === 'win32' ? ['-d', 'extension=fileinfo', '-d', 'extension=gd'] : [];
}

export function runPhp(args, input, extra = {}) {
  const result = spawnSync(process.env.PHP_BINARY || 'php', [...phpArgs(), ...args], {
    cwd: root, env: { ...env, ...extra }, input, encoding: 'utf8', windowsHide: true,
  });
  if (result.error) throw result.error;
  if (result.status !== 0) throw new Error(result.stdout + result.stderr);
  return result.stdout;
}

export function assertLocalEnvironment(extra = {}) {
  // Uses PHP's actual configuration loader, including inherited environment overrides.
  runPhp(['tests/bootstrap.php'], undefined, extra);
}

export function spawnPhp(args, extra = {}) {
  assertLocalEnvironment(extra);
  const log = fs.openSync(path.join(root, 'storage', 'logs', 'php-server.log'), 'a');
  let child;
  try {
    child = spawn(process.env.PHP_BINARY || 'php', [
      ...phpArgs(), '-d', 'upload_max_filesize=10M', '-d', 'post_max_size=32M',
      '-d', 'upload_tmp_dir=' + env.TMP, '-d', 'log_errors=1',
      '-d', 'error_log=' + path.join(root, 'storage', 'logs', 'php-errors.log'),
      '-d', 'display_errors=0', ...args,
    ], { cwd: root, env: { ...env, ...extra }, stdio: ['ignore', log, log], windowsHide: true });
  } finally {
    fs.closeSync(log);
  }
  child.on('error', error => { child.launchError = error; });
  return child;
}

export async function ready(url, child) {
  for (let attempt = 0; attempt < 100; attempt++) {
    if (child?.launchError) throw child.launchError;
    if (child?.exitCode != null) throw new Error('Server exited; inspect storage/logs.');
    try {
      const response = await fetch(url, { signal: AbortSignal.timeout(1500) });
      if (response.status < 500) return;
    } catch {}
    await new Promise(resolve => setTimeout(resolve, 150));
  }
  throw new Error('Server readiness timed out: ' + url);
}

export async function portOpen(port) {
  return new Promise(resolve => {
    const socket = net.createConnection({ host: '127.0.0.1', port });
    socket.once('connect', () => { socket.destroy(); resolve(true); });
    socket.once('error', () => resolve(false));
    socket.setTimeout(500, () => { socket.destroy(); resolve(false); });
  });
}

export function browserExecutable() {
  if (process.env.QA_BROWSER) return process.env.QA_BROWSER;
  if (process.platform !== 'win32') return undefined; // Playwright's installed Chromium.
  const candidates = [process.env['ProgramFiles(x86)'], process.env.ProgramFiles, process.env.LOCALAPPDATA]
    .filter(Boolean).map(directory => path.join(directory, 'Microsoft', 'Edge', 'Application', 'msedge.exe'));
  return candidates.find(file => fs.existsSync(file));
}

export async function playwright() {
  const bundled = path.join(os.homedir(), '.cache', 'codex-runtimes', 'codex-primary-runtime', 'dependencies', 'node', 'node_modules', 'playwright', 'index.mjs');
  const candidates = [process.env.PLAYWRIGHT_MODULE, 'playwright', bundled].filter(Boolean);
  for (const candidate of candidates) {
    try { return await import(path.isAbsolute(candidate) ? pathToFileURL(candidate).href : candidate); } catch {}
  }
  throw new Error('Playwright unavailable. Set PLAYWRIGHT_MODULE to an existing installation or install the declared dev dependency with authorization.');
}