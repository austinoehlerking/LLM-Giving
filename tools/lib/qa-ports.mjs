/** Validated loopback fixture ports. No runtime application dependency. */
export function qaLoopbackPort(name, fallback, values = process.env) {
  const value = values[name] ?? fallback;
  const text = String(value);
  if (!/^[0-9]{1,5}$/.test(text)) throw new RangeError(name + ' must be a decimal loopback port from 1024 to 65535.');
  const port = Number(text);
  if (!Number.isInteger(port) || port < 1024 || port > 65535) throw new RangeError(name + ' must be a decimal loopback port from 1024 to 65535.');
  return port;
}
