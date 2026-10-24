/** Dependency-free source hygiene checks; runtime data is never read or published. */
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';

export const sourceRoot = path.resolve(import.meta.dirname, '..');
const directories = ['.github', 'bin', 'content', 'docs', 'migrations', 'public', 'src', 'views', 'templates', 'tests', 'tools', 'volunteer'];
const rootFiles = ['.dockerignore', '.editorconfig', '.env.example', '.gitattributes', '.gitignore', 'AGENTS.md', 'CONTRIBUTING.md', 'Dockerfile', 'LICENSE', 'README.md', 'SECURITY.md', 'compose.yaml', 'package.json'];

export function sourceFiles() {
  const result = [...rootFiles];
  function visit(directory) {
    for (const entry of fs.readdirSync(path.join(sourceRoot, directory), { withFileTypes: true })) {
      const file = directory + '/' + entry.name;
      assert.ok(!entry.isSymbolicLink(), 'Source must not contain symbolic links: ' + file);
      if (entry.isDirectory()) visit(file);
      else if (entry.isFile()) result.push(file);
    }
  }
  // The optional source package arrives with the same feature batch; its own checker requires it.
  directories.forEach(directory => { if (directory !== 'volunteer' || fs.existsSync(path.join(sourceRoot, directory))) visit(directory); });
  return result.sort();
}

export function checkSource() {
  const files = sourceFiles();
  const checks = [];
  const check = (name, value) => { assert.ok(value, name); checks.push(name); };
  const read = file => fs.readFileSync(path.join(sourceRoot, file), 'utf8');
  const apache = read('public/.htaccess').match(/^RewriteRule \^(.*)\$ - \[L\]$/m);
  check('Apache has an explicit static asset allowlist', apache);
  const allowed = new RegExp('^' + apache[1] + '$');
  const router = [...read('public/router.php').match(/in_array\(\$path,\[([^\]]+)\]/)[1].matchAll(/'(\/[^']+)'/g)].map(match => match[1].slice(1));
  const assets = files.filter(file => file.startsWith('public/assets/') || file === 'public/favicon.svg').map(file => file.slice(7));
  check('Apache serves every checked-in asset', assets.every(file => allowed.test(file)));
  check('Local router serves exactly the checked-in assets', [...router].sort().join('\n') === [...assets].sort().join('\n'));
  check('Apache rejects source and private paths', ['.env', 'storage/private/handoff.key', 'src/bootstrap.php', 'assets/hidden.php', 'assets/hidden.js'].every(file => !allowed.test(file)));
  const ignore = read('.gitignore').split(/\r?\n/);
  check('Git excludes runtime data and environment variants', ['.env', '.env.*', '!.env.example', '/storage/', '/node_modules/'].every(rule => ignore.includes(rule)));
  check('Container copies explicit runtime directories only', !/^COPY\s+\.\s+\./m.test(read('Dockerfile')));
  let javascript = 0;
  let json = 0;
  const manifest = [];
  for (const file of files) {
    const bytes = fs.readFileSync(path.join(sourceRoot, file));
    const text = bytes.toString('utf8');
    check('No temporary/private artifact in source: ' + file,
      !/(?:\.safe-edit-|\.codex-safe-edit-|\.(?:pem|key|p12|log|bak)$|(?:^|\/)\.env(?:\.|$))/.test(file) || file === '.env.example');
    assert.ok(!/^(?:<{7}|={7}|>{7})(?: |$)/m.test(text), 'Unresolved merge marker: ' + file);
    const privateKey = new RegExp('-----BEGIN ' + '(?:RSA |EC |OPENSSH )?PRIVATE KEY-----');
    assert.ok(!privateKey.test(text), 'Private key marker in source: ' + file);
    assert.ok(!/\b(?:ghp_|github_pat_)[A-Za-z0-9_]{30,}\b/.test(text), 'Credential-like token in source: ' + file);
    if (/\.(?:mjs|js)$/.test(file)) {
      const result = spawnSync(process.execPath, ['--check', file], { cwd: sourceRoot, encoding: 'utf8', windowsHide: true });
      assert.equal(result.status, 0, file + ': ' + (result.error?.message || result.stderr));
      javascript++;
    }
    if (file.endsWith('.json')) { JSON.parse(text); json++; }
    if (/^(?:src|tools|tests|public)\//.test(file)) {
      assert.ok(!/[CD]:[\\/]Users[\\/][a-z0-9_.-]+[\\/]/i.test(text), 'Machine-specific user directory: ' + file);
    }
    manifest.push({ file, bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex') });
  }
  return { status: 'PASS', at: new Date().toISOString(), files: files.length, javascript, json, checks, manifest,
    limits: 'Basic source hygiene, syntax and routing checks. Not a complete secret scanner or a hosting test. Runtime storage and .env are excluded.' };
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const report = checkSource();
  const output = path.join(sourceRoot, 'storage/qa');
  fs.mkdirSync(output, { recursive: true });
  fs.writeFileSync(path.join(output, 'source-check.json'), JSON.stringify(report, null, 2) + '\n');
  console.log(`Source checks passed: ${report.files} files, ${report.javascript} JavaScript syntax checks, ${report.json} JSON files; asset parity and release exclusions verified.`);
}