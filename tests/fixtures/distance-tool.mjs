// Synthetic QA tool: metric distance conversion; not a scientific finding.
export function metres(kilometres) {
  if (typeof kilometres !== 'number' || !Number.isFinite(kilometres) || kilometres < 0) throw new Error('invalid distance');
  return kilometres * 1000;
}