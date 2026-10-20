import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { root, env, runPhp, assertLocalEnvironment } from '../tools/runtime.mjs';
import { prepareEdit, applyEdit } from '../tools/lib/editing.mjs';
const directory = fs.mkdtempSync(path.join(root, 'storage/tmp/editor-test-'));
const file = path.join(directory, 'sample.txt');
let checks = 0;
const check = (name, fn) => { fn(); checks++; console.log('ok tooling ' + checks + ' - ' + name); };
check('New files require absent state', () => applyEdit(prepareEdit(directory, { file: 'sample.txt', content: 'alpha\nalpha\n' }, { write: true })));
check('Unexpected replacement count leaves bytes untouched', () => {
  assert.throws(() => prepareEdit(directory, { file: 'sample.txt', search: 'alpha', replace: 'beta', expected: 1 }), /found 2/);
  assert.equal(fs.readFileSync(file, 'utf8'), 'alpha\nalpha\n');
});
check('Exact edit updates only matching text', () => {
  const plan = prepareEdit(directory, { file: 'sample.txt', search: 'alpha', replace: 'βeta', expected: 2 });
  assert.equal(fs.readFileSync(file, 'utf8'), 'alpha\nalpha\n');
  applyEdit(plan);
  assert.equal(fs.readFileSync(file, 'utf8'), 'βeta\nβeta\n');
});
check('Traversal and state mismatch fail closed', () => {
  assert.throws(() => prepareEdit(directory, { file: '../escape', content: '' }, { write: true }), /inside/);
  assert.throws(() => prepareEdit(directory, { file: 'sample.txt', content: '' }, { write: true }), /absent/);
});
check('Concurrent changes after dry run are preserved', () => {
  const plan = prepareEdit(directory, { file: 'sample.txt', content: 'replace' }, { write: true, replace: true });
  fs.writeFileSync(file, 'external change');
  assert.throws(() => applyEdit(plan), /changed after dry run/);
  assert.equal(fs.readFileSync(file, 'utf8'), 'external change');
});
check('CLI validates the whole batch before mutation', () => {
  const result = spawnSync(process.execPath, [path.join(root, 'tools/edit-files.mjs')], { cwd: directory, env, encoding: 'utf8', input: JSON.stringify([
    { file: 'sample.txt', search: 'external', replace: 'first' },
    { file: 'missing.txt', search: 'absent', replace: 'second' },
  ]) });
  assert.equal(result.status, 1);
  assert.equal(fs.readFileSync(file, 'utf8'), 'external change');
});
check('Linked directories cannot redirect an edit', () => {
  const outside = fs.mkdtempSync(path.join(root, 'storage/tmp/editor-outside-'));
  fs.symlinkSync(outside, path.join(directory, 'linked'), process.platform === 'win32' ? 'junction' : 'dir');
  assert.throws(() => prepareEdit(directory, { file: 'linked/escape.txt', content: 'blocked' }, { write: true }), /symbolic links/);
  assert.equal(fs.existsSync(path.join(outside, 'escape.txt')), false);
});
check('Production configuration is rejected with failure status', () => assert.throws(() => assertLocalEnvironment({ APP_ENV: 'production' }), /Tests require/));
check('Remote database configuration is rejected before connecting', () => assert.throws(() => assertLocalEnvironment({ APP_ENV: 'local', DB_HOST: 'database.example.invalid' }), /Tests require/));
check('Unsupported QA modes fail before starting a server', () => {
  const result = spawnSync(process.execPath, ['tools/qa.mjs', 'not-a-suite'], { cwd: root, env, encoding: 'utf8' });
  assert.equal(result.status, 1);
  assert.match(result.stderr, /Unknown QA mode/);
});
process.stdout.write(runPhp(['tests/input-boundaries.php']));
console.log(checks + ' source-tooling regression checks passed.');