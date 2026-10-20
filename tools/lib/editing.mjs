/** Repository-owned deterministic editing. No editor installation is required. */
import fs from 'node:fs';
import path from 'node:path';

export function prepareEdit(root, edit, { write = false, replace = false } = {}) {
  root = fs.realpathSync(root);
  if (!edit || typeof edit.file !== 'string' || !edit.file) throw new Error('A relative file path is required.');
  const target = path.resolve(root, edit.file);
  const relative = path.relative(root, target);
  if (!relative || relative === '..' || relative.startsWith('..' + path.sep) || path.isAbsolute(relative)) {
    throw new Error('Edit target must stay inside the repository.');
  }
  // Reject links at every existing path component, including the target itself.
  let cursor = root;
  for (const component of relative.split(path.sep)) {
    cursor = path.join(cursor, component);
    if (fs.existsSync(cursor) || (() => { try { fs.lstatSync(cursor); return true; } catch { return false; } })()) {
      if (fs.lstatSync(cursor).isSymbolicLink()) throw new Error('Editing through symbolic links is not allowed.');
    }
  }
  const exists = fs.existsSync(target);
  if (exists && !fs.statSync(target).isFile()) throw new Error('Edit target is not a regular file.');
  const before = exists ? fs.readFileSync(target, 'utf8') : null;
  let after;
  if (write) {
    if (exists !== replace) throw new Error(`Expected ${replace ? 'present' : 'absent'} file: ${edit.file}`);
    if (typeof edit.content !== 'string') throw new Error('File content must be a string.');
    after = edit.content;
  } else {
    if (!exists || typeof edit.search !== 'string' || !edit.search || typeof edit.replace !== 'string') {
      throw new Error('Exact edits require an existing file, a nonempty search and a replacement string.');
    }
    const expected = edit.expected ?? 1;
    if (!Number.isInteger(expected) || expected < 1) throw new Error('Expected count must be a positive integer.');
    const count = before.split(edit.search).length - 1;
    if (count !== expected) throw new Error(`Expected ${expected} occurrences in ${edit.file}; found ${count}.`);
    if (edit.search === edit.replace) throw new Error('Search and replacement are identical.');
    after = before.split(edit.search).join(edit.replace);
  }
  return { file: edit.file, target, before, after, mode: exists ? fs.statSync(target).mode : 0o644 };
}

export function applyEdit(plan) {
  const current = fs.existsSync(plan.target) ? fs.readFileSync(plan.target, 'utf8') : null;
  if (current !== plan.before) throw new Error(`File changed after dry run: ${plan.file}`);
  fs.mkdirSync(path.dirname(plan.target), { recursive: true });
  const temporary = `${plan.target}.safe-edit-${process.pid}.tmp`;
  try {
    fs.writeFileSync(temporary, plan.after, { flag: 'wx', mode: plan.mode });
    fs.renameSync(temporary, plan.target);
  } finally {
    if (fs.existsSync(temporary)) fs.unlinkSync(temporary);
  }
}

export async function readInput() {
  process.stdin.setEncoding('utf8');
  let input = '';
  for await (const chunk of process.stdin) input += chunk;
  return JSON.parse(input);
}

export function executeEdits(edits, options = {}) {
  if (!Array.isArray(edits) || edits.length === 0) throw new Error('Provide at least one edit.');
  const root = process.cwd();
  const plans = edits.map(edit => prepareEdit(root, edit, options));
  const targets = plans.map(plan => process.platform === 'win32' ? plan.target.toLowerCase() : plan.target);
  if (new Set(targets).size !== targets.length) throw new Error('Use one edit per file in each batch.');
  // Validate the entire batch before writing any file. Report metadata, never content.
  for (const plan of plans) console.log(JSON.stringify({ mode: 'dry-run', file: plan.file }));
  if (process.argv.includes('--dry-run')) return;
  for (const plan of plans) {
    applyEdit(plan);
    console.log(JSON.stringify({ mode: 'apply', file: plan.file }));
  }
}