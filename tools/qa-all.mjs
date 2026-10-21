/** Sequential local QA with fresh, immutable per-run evidence. Keep the dev DB running. */
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { root, env, assertLocalEnvironment } from './runtime.mjs';
import { qaReports } from './lib/qa-modes.mjs';

assertLocalEnvironment();
const output = path.join(root, 'storage', 'qa', 'runs', new Date().toISOString().replaceAll(':', '-'));
fs.mkdirSync(output, { recursive: true });
const summary = { startedUtc: new Date().toISOString(), status: 'RUNNING', suites: [] };
const save = () => fs.writeFileSync(path.join(output, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
let active;
for (const signal of ['SIGINT', 'SIGTERM']) process.once(signal, () => {
  active?.kill();
  summary.status = 'INTERRUPTED';
  save();
  process.exitCode = 1;
});

async function run(name, args, report) {
  const started = Date.now();
  const item = { name, command: ['node', ...args].join(' '), startedUtc: new Date(started).toISOString() };
  console.log('Running ' + name + '…');
  const descriptor = fs.openSync(path.join(output, name + '.log'), 'wx');
  try {
    const result = await new Promise(resolve => {
      active = spawn(process.execPath, args, { cwd: root, env, windowsHide: true, stdio: ['ignore', descriptor, descriptor] });
      active.once('error', error => resolve({ exitCode: 1, error: error.message }));
      active.once('close', code => resolve({ exitCode: code ?? 1 }));
    });
    Object.assign(item, result);
  } finally {
    fs.closeSync(descriptor);
    active = undefined;
  }
  item.finishedUtc = new Date().toISOString();
  if (item.exitCode === 0 && report) {
    const source = path.join(root, 'storage', 'qa', report);
    if (!fs.existsSync(source) || fs.statSync(source).mtimeMs < started - 1000) {
      item.exitCode = 1;
      item.error = 'Missing fresh report; historical output cannot count as a pass.';
    } else {
      const data = JSON.parse(fs.readFileSync(source, 'utf8'));
      item.assertions = data.checks.length;
      item.report = name + '-report.json';
      fs.copyFileSync(source, path.join(output, item.report), fs.constants.COPYFILE_EXCL);
    }
  }
  summary.suites.push(item);
  save();
  console.log(name + ': ' + (item.exitCode === 0 ? 'PASS' : 'FAIL') + (item.assertions ? ` (${item.assertions} assertions)` : ''));
  if (summary.status === 'INTERRUPTED') throw new Error('QA interrupted.');
}

try {
  save();
  await run('server', ['tools/check.mjs']);
  await run('blog-http', ['tests/blog-http.mjs', process.env.QA_APP_URL || 'http://127.0.0.1:8086']);
  for (const [mode, report] of Object.entries(qaReports)) await run(mode, ['tools/qa.mjs', mode], report);
  summary.status = summary.suites.every(suite => suite.exitCode === 0) ? 'PASS' : 'FAIL';
  summary.browserAssertions = summary.suites.reduce((sum, suite) => sum + (suite.assertions || 0), 0);
} catch (error) {
  summary.status = summary.status === 'INTERRUPTED' ? 'INTERRUPTED' : 'FAIL';
  summary.error = error.message;
} finally {
  summary.finishedUtc = new Date().toISOString();
  save();
  console.log('Evidence: ' + output);
  if (summary.status !== 'PASS') process.exitCode = 1;
}