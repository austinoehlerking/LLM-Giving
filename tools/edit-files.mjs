import { executeEdits, readInput } from './lib/editing.mjs';
try {
  executeEdits(await readInput());
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}