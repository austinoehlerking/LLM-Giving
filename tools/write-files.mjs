import { executeEdits, readInput } from './lib/editing.mjs';
try {
  const files = await readInput();
  if (!files || Array.isArray(files) || typeof files !== 'object') throw new Error('Expected a JSON map of file paths to text.');
  executeEdits(Object.entries(files).map(([file, content]) => ({ file, content })), {
    write: true,
    replace: process.argv.includes('--replace'),
  });
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}