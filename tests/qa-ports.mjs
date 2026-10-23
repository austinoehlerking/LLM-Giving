import assert from 'node:assert/strict';
import { qaLoopbackPort } from '../tools/lib/qa-ports.mjs';
let checks = 0;
for (const [name, fallback] of [['QA_CALCULATION_PORT', 8089], ['QA_TOOL_HTTP_PORT', 8099]]) {
  assert.equal(qaLoopbackPort(name, fallback, {}), fallback); checks++;
  assert.equal(qaLoopbackPort(name, fallback, { [name]: '18469' }), 18469); checks++;
  for (const value of ['1024', '65535']) { assert.equal(qaLoopbackPort(name, fallback, { [name]: value }), Number(value)); checks++; }
  for (const value of ['', '0', '1023', '65536', '-1', '1.5', '12e3', ' 18469', '18469 ', '127.0.0.1:18469', 'https://example.invalid', '18469/path']) {
    assert.throws(() => qaLoopbackPort(name, fallback, { [name]: value }), RangeError); checks++;
  }
}
assert.notEqual(qaLoopbackPort('QA_CALCULATION_PORT', 8089, { QA_CALCULATION_PORT: '18469' }), qaLoopbackPort('QA_TOOL_HTTP_PORT', 8099, { QA_TOOL_HTTP_PORT: '18479' })); checks++;
assert.throws(() => qaLoopbackPort('QA_CALCULATION_PORT', 80, {}), RangeError); checks++;
console.log(checks + ' loopback fixture port checks passed.');
