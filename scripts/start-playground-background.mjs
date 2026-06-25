import { openSync } from 'node:fs';
import { spawn } from 'node:child_process';
import path from 'node:path';

const root = path.resolve('.');
const out = openSync(path.join(root, 'playground-server.out.log'), 'a');
const err = openSync(path.join(root, 'playground-server.err.log'), 'a');
const command = process.env.ComSpec || 'cmd.exe';
const npm = 'C:\\Progra~1\\nodejs\\npm.cmd';

const child = spawn(
  command,
  ['/d', '/s', '/c', `${npm} run wp:start -- --port=9400`],
  {
    cwd: root,
    detached: true,
    stdio: ['ignore', out, err],
    windowsHide: true,
  }
);

child.unref();
console.log(child.pid);
