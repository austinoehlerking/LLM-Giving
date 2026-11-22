import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { createInterface } from 'node:readline';

const MAX_EVENT_BYTES = 1_000_000;
const CHAT_ID = /^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/;

/** Run one saved, named Codex chat and extract only its completed structured response. */
export async function runPlannerChat({ project, env, title, model, effort, instructions, prompt, schema }, launch = spawn) {
  const args = ['app-server', '--stdio', '--disable', 'shell_tool', '--disable', 'unified_exec',
    '--disable', 'plugins', '--disable', 'apps', '--disable', 'hooks', '--disable', 'computer_use',
    '--disable', 'browser_use', '--disable', 'browser_use_external', '--disable', 'in_app_browser',
    '--disable', 'multi_agent', '--disable', 'multi_agent_v2', '--disable', 'skill_mcp_dependency_install',
    '-c', 'web_search="disabled"', '-c', 'forced_login_method="chatgpt"', '-c', 'mcp_servers={}',
    '-c', 'project_doc_max_bytes=0'];
  const child = launch('codex', args, {
    cwd: project, env, windowsHide: true, stdio: ['pipe', 'pipe', 'ignore'],
    signal: AbortSignal.timeout(720000),
  });
  const lines = createInterface({ input: child.stdout });
  const requests = new Map();
  let number = 0;
  let threadId;
  let completeTurn;
  let fail;
  let totalBytes = 0;
  const stopped = new Promise((_, reject) => { fail = reject; });
  const closed = once(child, 'close').catch(() => []);
  const completion = new Promise(resolve => { completeTurn = resolve; });
  const messages = new Map();

  function stop() {
    fail(new Error('The local Codex chat did not complete.'));
  }

  function send(value) {
    child.stdin.write(JSON.stringify(value) + '\n');
  }

  function request(method, params) {
    return new Promise((resolve, reject) => {
      const id = ++number;
      requests.set(id, { method, resolve, reject });
      send({ id, method, params });
    });
  }

  child.on('error', stop);
  child.on('close', stop);
  child.stdin.on('error', stop);
  lines.on('line', line => {
    try {
      totalBytes += Buffer.byteLength(line);
      if (totalBytes > MAX_EVENT_BYTES) { stop(); return; }
      const event = JSON.parse(line);
      if (event.method && event.id !== undefined) {
        // The planner never delegates an approval or tool call to this client.
        send({ id: event.id, error: { code: -32601, message: 'Unavailable to the research planner.' } });
        return;
      }
      if (!event.method && requests.has(event.id)) {
        const call = requests.get(event.id);
        requests.delete(event.id);
        if (event.error) call.reject(new Error('The local Codex chat rejected ' + call.method + '.'));
        else call.resolve(event.result);
        return;
      }
      if (!threadId || event.params?.threadId !== threadId) return;
      if (event.method === 'item/completed' && event.params.item?.type === 'agentMessage') {
        messages.set(event.params.turnId, event.params.item.text);
      }
      if (event.method === 'turn/completed') completeTurn(event.params.turn);
    } catch {
      stop();
    }
  });

  async function run() {
    await request('initialize', { clientInfo: { name: 'llm_giving_planner', title: 'LLM.Giving', version: '1.0.0' } });
    send({ method: 'initialized' });
    const { config } = await request('config/read', { cwd: project, includeLayers: false });
    if (!config) throw new Error('Codex did not return its local tool configuration.');
    const mcpServers = Object.fromEntries(Object.keys(config.mcp_servers ?? {}).map(name => [name, { enabled: false }]));
    const session = await request('thread/start', {
      cwd: project, model, modelProvider: 'openai', approvalPolicy: 'never', sandbox: 'read-only',
      ephemeral: false, config: { mcp_servers: mcpServers }, developerInstructions: instructions,
    });
    const { thread } = session;
    if (!CHAT_ID.test(thread?.id ?? '') || thread.ephemeral !== false) throw new Error('Codex did not open a saved planner chat.');
    if (session.approvalPolicy !== 'never' || session.sandbox?.type !== 'readOnly'
      || session.modelProvider !== 'openai' || session.model !== model) throw new Error('Codex changed the planner settings.');
    threadId = thread.id;
    const tools = await request('mcpServerStatus/list', { threadId, limit: 100 });
    const disabled = server => server.runtimeStatus === 'disabled' && !Object.keys(server.tools ?? {}).length
      && !server.resources?.length && !server.resourceTemplates?.length;
    if (!Array.isArray(tools?.data) || tools.nextCursor || !tools.data.every(disabled)) {
      throw new Error('External tools are active in the planner chat.');
    }
    await request('thread/name/set', { threadId, name: title });

    const { turn } = await request('turn/start', {
      threadId, effort, outputSchema: schema, input: [{ type: 'text', text: prompt, text_elements: [] }],
    });
    const finished = await completion;
    if (!turn?.id || finished?.id !== turn.id || finished.status !== 'completed') throw new Error('Codex did not complete the planner turn.');
    const text = messages.get(turn.id) ?? finished.items?.findLast(item => item.type === 'agentMessage')?.text;
    const result = JSON.parse(text ?? '');
    if (!Array.isArray(result?.tasks)) throw new Error('The planner returned no task array.');
    return { run_id: threadId, tasks: result.tasks };
  }

  try {
    return await Promise.race([run(), stopped]);
  } finally {
    lines.close();
    child.stdin.end();
    const timer = setTimeout(() => child.kill(), 1500);
    try { await closed; } finally { clearTimeout(timer); }
  }
}
