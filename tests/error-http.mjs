/** A failed database must still produce a complete, private, usable error response. */
import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import assert from 'node:assert/strict';
import { root, env, spawnPhp, playwright, browserExecutable } from '../tools/runtime.mjs';
async function freePort() {
  const reservation = net.createServer();
  await new Promise(resolve => reservation.listen(0, '127.0.0.1', resolve));
  const port = reservation.address().port;
  await new Promise(resolve => reservation.close(resolve));
  return port;
}
const port = await freePort();
const unavailableDatabasePort = await freePort();
const base = 'http://127.0.0.1:' + port;
const server = spawnPhp(['-S', '127.0.0.1:' + port, '-t', 'public', 'public/router.php'], {
  APP_URL: base, DB_PORT: String(unavailableDatabasePort),
});
const output = path.join(root, 'storage/qa/error-response');
fs.mkdirSync(output, { recursive: true });
const checks = [];
const check = (label, ok) => { assert.ok(ok, label); checks.push(label); };
let browser;
try {
  let response;
  for (let attempt = 0; attempt < 50 && !response; attempt++) {
    if (server.launchError) throw server.launchError;
    try { response = await fetch(base, { signal: AbortSignal.timeout(3000) }); }
    catch { await new Promise(resolve => setTimeout(resolve, 100)); }
  }
  check('Database outage returns HTTP 500', response?.status === 500);
  const html = await response.text();
  check('Complete standalone error document', html.startsWith('<!doctype html>') && html.includes('</html>'));
  check('Error explains retry action', html.includes('Please try again shortly.') && html.includes('Try the homepage again'));
  check('No database exception, credentials or source path exposed', !/PDOException|SQLSTATE|Fatal error|DB_PASSWORD|bootstrap\.php|Stack trace/i.test(html));
  check('Security headers survive database failure', response.headers.get('x-content-type-options') === 'nosniff' && response.headers.get('cache-control')?.includes('no-store'));
  check('Static assets remain available during outage', (await fetch(base + '/assets/app.css')).status === 200);
  if (process.argv.includes('--browser')) {
    browser = await (await playwright()).chromium.launch({ headless: true, executablePath: browserExecutable(), env });
    const page = await browser.newPage();
    for (const size of [{ width: 1366, height: 768 }, { width: 390, height: 844 }, { width: 375, height: 667 }]) {
      await page.setViewportSize(size);
      await page.goto(base);
      await page.screenshot({ path: path.join(output, `${size.width}x${size.height}.png`), fullPage: false });
      const visible = await page.getByRole('link', { name: 'Try the homepage again' }).evaluate(element => {
        const r = element.getBoundingClientRect();
        const top = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
        return r.x >= 0 && r.y >= 0 && r.right <= innerWidth && r.bottom <= innerHeight && r.height >= 44 && (top === element || element.contains(top));
      });
      check('Retry action visible and uncovered at ' + size.width + 'x' + size.height, visible);
      const retried = page.waitForResponse(result => result.url() === base + '/');
      await page.getByRole('link', { name: 'Try the homepage again' }).click();
      check('Retry reaches the real route at ' + size.width, (await retried).status() === 500);
    }
    await page.setViewportSize({width:683,height:384});
    await page.goto(base);
    await page.evaluate(() => { document.body.style.fontSize='200%'; });
    await page.screenshot({path:path.join(output,'enlarged-text.png'),fullPage:false});
    check('Enlarged error text reflows without horizontal clipping',await page.evaluate(() => document.documentElement.scrollWidth<=innerWidth+1));
  }
  fs.writeFileSync(path.join(output, process.argv.includes('--browser') ? 'browser-report.json' : 'http-report.json'), JSON.stringify({ checks, at: new Date().toISOString() }, null, 2));
  console.log(checks.length + ' database-outage response checks passed.');
} finally {
  await browser?.close();
  server.kill();
}