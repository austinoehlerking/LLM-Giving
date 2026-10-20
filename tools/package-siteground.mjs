/** Build a source-only SiteGround bundle. Does not connect to or alter hosting. */
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { sourceFiles, sourceRoot } from './source-check.mjs';

const selected = sourceFiles().filter(file =>
  /^(bin|src|views|templates|migrations|public)\//.test(file) ||
  (file.startsWith('content/') && !file.startsWith('content/blog-drafts/')) || file === 'LICENSE');
const stamp = new Date().toISOString().replaceAll(':', '-');
const output = path.join(sourceRoot, 'storage', 'deploy', stamp);
fs.mkdirSync(output, { recursive: true });
const records = [];
for (const file of selected) {
  const destination = file.startsWith('public/') ? 'public_html/' + file.slice(7) : 'app/' + file;
  let bytes = fs.readFileSync(path.join(sourceRoot, file));
  if (file === 'public/index.php') {
    const before = "require __DIR__.'/../src/bootstrap.php';";
    const text = bytes.toString('utf8');
    if (text.split(before).length !== 2) throw new Error('Unexpected entry point; refuse to build.');
    bytes = Buffer.from(text.replace(before, "require __DIR__.'/../app/src/bootstrap.php';"));
  }
  const target = path.join(output, destination);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, bytes, { flag: 'wx' });
  records.push({ path: destination, bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex') });
}
const manifest = { createdUtc: new Date().toISOString(), layout: 'app/ is private; public_html/ is the only document root.', files: records };
fs.writeFileSync(path.join(output, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n', { flag: 'wx' });
const archive = path.join(output, 'release.tar.gz');
const result = spawnSync('tar', ['-czf', archive, '-C', output, 'app', 'public_html', 'manifest.json'], { encoding: 'utf8', windowsHide: true });
if (result.status !== 0) throw new Error(result.error?.message || result.stderr || 'Archive creation failed.');
console.log(JSON.stringify({ archive, files: records.length, sha256: createHash('sha256').update(fs.readFileSync(archive)).digest('hex') }, null, 2));