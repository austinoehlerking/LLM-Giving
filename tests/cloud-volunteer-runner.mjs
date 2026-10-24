/** Execute the real check-runner dispatch under supported app configs without running its DB suites. */
import assert from 'node:assert/strict';
import childProcess from 'node:child_process';
import { syncBuiltinESMExports } from 'node:module';
import { root, env } from '../tools/runtime.mjs';

if (process.argv.includes('--probe')) {
  const realSpawn = childProcess.spawnSync;
  const realWrite = process.stdout.write;
  const calls = [];
  let contractOutput = '';
  childProcess.spawnSync = (command, args = [], options = {}) => {
    const script = args.includes('-l') ? null : args.find(arg => typeof arg === 'string' && arg.endsWith('.php'))?.replaceAll('\\', '/');
    calls.push(script || args[0]);
    // These actual PHP executions have no DB access. Accidentally dispatching the state suite
    // executes its unchanged guard, which rejects both configurations before a connection.
    if (['tests/bootstrap.php', 'tests/cloud-volunteer-contract.php', 'tests/cloud-volunteer-state.php'].includes(script)) {
      const result = realSpawn(command, args, options);
      if (script === 'tests/cloud-volunteer-contract.php') contractOutput = result.stdout;
      return result;
    }
    // Only dispatch is under test: no other server suite, lint, browser or this probe recurses.
    return { status: 0, stdout: '', stderr: '' };
  };
  syncBuiltinESMExports();
  process.stdout.write = () => true; // Do not print the runner's simulated suite/lint messages.
  try {
    await import('../tools/check.mjs');
    assert.ok(calls.includes('tests/bootstrap.php'), 'Real app configuration guard ran.');
    assert.ok(calls.includes('tests/cloud-volunteer-contract.php'), 'Normal runner executes the pure Cloud contracts.');
    assert.ok(!calls.includes('tests/cloud-volunteer-state.php'), 'Isolated state suite cannot inherit app DB settings.');
    assert.match(contractOutput, /Cloud synthetic guard\/verifier contract checks passed/);
  } finally {
    process.stdout.write = realWrite;
    childProcess.spawnSync = realSpawn;
    syncBuiltinESMExports();
  }
  console.log(JSON.stringify({ configuration: process.env.CLOUD_RUNNER_CASE, runnerDispatch: 'PASS', actualExecutions: ['app configuration guard', 'database-free Cloud contracts'], otherSuites: 'Intercepted; not executed and not claimed PASS.' }));
} else {
  let checks = 0;
  for (const [name, host, url] of [['standard-local', '127.0.0.1', 'http://127.0.0.1:8086'], ['compose', 'db', 'http://localhost:8086']]) {
    const configuration = { ...env, APP_ENV: 'local', DB_HOST: host, DB_PORT: '1', DB_NAME: 'llm_giving', DB_USER: 'unused-fixture-test', DB_PASSWORD: 'unused-fixture-test', APP_URL: url, CLOUD_RUNNER_CASE: name };
    const probe = childProcess.spawnSync(process.execPath, ['tests/cloud-volunteer-runner.mjs', '--probe'], { cwd: root, env: configuration, encoding: 'utf8', windowsHide: true });
    assert.equal(probe.status, 0, name + ': ' + probe.stdout + probe.stderr);
    const result = JSON.parse(probe.stdout);
    assert.equal(result.runnerDispatch, 'PASS');
    checks++;
    const guard = String.raw`require 'tools/cloud-volunteer-contract.php';cv_guard(['scope'=>str_repeat('a',32),'destination'=>'local','candidate'=>str_repeat('b',40),'tree'=>str_repeat('c',40)],['environment'=>getenv('APP_ENV'),'host'=>getenv('DB_HOST'),'database'=>getenv('DB_NAME'),'url'=>getenv('APP_URL')]);`;
    const refused = childProcess.spawnSync(configuration.PHP_BINARY || 'php', ['-r', guard], { cwd: root, env: configuration, encoding: 'utf8', windowsHide: true });
    assert.notEqual(refused.status, 0, 'The isolated fixture guard must still reject ' + name);
    assert.match(refused.stderr, /dedicated llm_giving_cloud_qa_|loopback database/);
    checks++;
    console.log(name + ': real runner dispatch permits pure contracts; isolated fixture guard still rejects this app DB.');
  }
  console.log(checks + ' Cloud runner/configuration boundary checks passed. Other application suites were intercepted, not run.');
}
