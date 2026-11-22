import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { createInterface } from 'node:readline';
import { PassThrough } from 'node:stream';
import { test } from 'node:test';
import { runPlannerChat } from '../tools/lib/local-planner-chat.mjs';

const threadId = '01234567-1234-4321-8765-987654321abc';
const turnId = 'test-turn';
const plan = { tasks: [{ key: 'compare-published-guidance' }] };
const input = {
  project: process.cwd(), env: {}, title: 'LLM.Giving plan · Example study',
  model: 'gpt-6.1-sol', effort: 'max',
  instructions: 'Use approved data only.',
  prompt: 'UNTRUSTED APPROVED CONTEXT\nAn isolated plan.',
  schema: { type: 'object' },
};

function fakeServer({ externalTools = false, readOnly = true, foreignTurn = false } = {}) {
  const calls = [];
  const actions = [];
  function launch(binary, args, options) {
    assert.equal(binary, 'codex');
    assert.deepEqual(args.slice(0, 2), ['app-server', '--stdio']);
    for (const feature of ['shell_tool', 'plugins', 'apps', 'browser_use', 'computer_use', 'hooks']) {
      assert.ok(args.some((arg, i) => arg === '--disable' && args[i + 1] === feature));
    }
    assert.ok(args.includes('mcp_servers={}'));
    assert.equal(options.cwd, input.project);
    assert.equal(options.windowsHide, true);
    const child = new EventEmitter();
    child.stdin = new PassThrough();
    child.stdout = new PassThrough();
    child.kill = () => child.emit('close', 1);
    child.stdin.on('end', () => child.emit('close', 0));
    const send = value => child.stdout.write(JSON.stringify(value) + '\n');
    createInterface({ input: child.stdin }).on('line', line => {
      const event = JSON.parse(line);
      if (!event.method) { actions.push(event); return; }
      calls.push(event);
      const reply = result => send({ id: event.id, result });
      switch (event.method) {
        case 'initialize': reply({}); break;
        case 'initialized': break;
        case 'config/read': reply({ config: { mcp_servers: { local_reference: { enabled: true } } } }); break;
        case 'thread/start':
          reply({ thread: { id: threadId, ephemeral: false }, model: input.model, modelProvider: 'openai',
            approvalPolicy: 'never', sandbox: { type: readOnly ? 'readOnly' : 'workspaceWrite' } });
          break;
        case 'mcpServerStatus/list':
          reply({ data: [{ runtimeStatus: externalTools ? 'ready' : 'disabled', tools: {}, resources: [], resourceTemplates: [] }], nextCursor: null });
          break;
        case 'thread/name/set': reply({}); break;
        case 'turn/start':
          send({ id: 'request-approval', method: 'item/commandExecution/requestApproval', params: { threadId, turnId } });
          send({ method: 'item/completed', params: { threadId, turnId, item: { type: 'agentMessage', text: JSON.stringify(plan) } } });
          send({ method: 'turn/completed', params: { threadId, turn: { id: foreignTurn ? 'other-turn' : turnId, status: 'completed' } } });
          reply({ turn: { id: turnId } });
          break;
        default: throw new Error('Unexpected RPC method.');
      }
    });
    return child;
  }
  return { calls, actions, launch };
}

test('names a saved read-only chat and submits only its matching completed plan', async () => {
  const fake = fakeServer();
  const actual = await runPlannerChat(input, fake.launch);
  assert.deepEqual(actual, { run_id: threadId, tasks: plan.tasks });
  assert.deepEqual(fake.calls.map(event => event.method), [
    'initialize', 'initialized', 'config/read', 'thread/start', 'mcpServerStatus/list', 'thread/name/set', 'turn/start',
  ]);
  assert.equal(fake.calls[3].params.ephemeral, false);
  assert.equal(fake.calls[3].params.sandbox, 'read-only');
  assert.equal(fake.calls[3].params.approvalPolicy, 'never');
  assert.deepEqual(fake.calls[3].params.config.mcp_servers, { local_reference: { enabled: false } });
  assert.equal(fake.calls[5].params.name, input.title);
  assert.equal(fake.calls[6].params.effort, 'max');
  assert.deepEqual(fake.calls[6].params.outputSchema, input.schema);
  assert.equal(fake.actions[0].error.code, -32601);
});

test('stops before inference when the app server exposes external tools', async () => {
  const fake = fakeServer({ externalTools: true });
  await assert.rejects(runPlannerChat(input, fake.launch), /External tools are active/i);
  assert.ok(!fake.calls.some(event => event.method === 'turn/start'));
});

test('stops before inference when the app server changes the sandbox', async () => {
  const fake = fakeServer({ readOnly: false });
  await assert.rejects(runPlannerChat(input, fake.launch), /changed the planner settings/i);
  assert.ok(!fake.calls.some(event => event.method === 'turn/start'));
});

test('never attaches another turn’s result to the leased plan', async () => {
  const fake = fakeServer({ foreignTurn: true });
  await assert.rejects(runPlannerChat(input, fake.launch), /did not complete the planner turn/i);
});
