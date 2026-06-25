import { cp, mkdir, rm } from 'node:fs/promises';
import { spawn } from 'node:child_process';
import path from 'node:path';
import process from 'node:process';
import packageJson from '../package.json' with { type: 'json' };

const root = path.resolve('.');
const dist = path.join(root, 'dist');
const packageRoot = path.join(dist, 'package');
const stagedPlugin = path.join(packageRoot, 'wpagent');
const version = packageJson.version;
const output = path.join(dist, `wpagent-${version}.zip`);

await mkdir(dist, { recursive: true });
await rm(output, { force: true });
await rm(packageRoot, { recursive: true, force: true });
await mkdir(packageRoot, { recursive: true });
await cp(path.join(root, 'wpagent'), stagedPlugin, { recursive: true });
await cp(path.join(root, 'readme.txt'), path.join(stagedPlugin, 'readme.txt'));
await cp(path.join(root, 'LICENSE'), path.join(stagedPlugin, 'LICENSE'));

await run('zip', [
  '-r',
  output,
  'wpagent',
  '-x',
  '*.DS_Store',
  '*/.DS_Store',
]);

await rm(packageRoot, { recursive: true, force: true });
console.log(`Created ${path.relative(root, output)}`);

function run(command, args) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, {
      cwd: packageRoot,
      stdio: 'inherit',
    });

    child.on('error', reject);
    child.on('close', (code) => {
      if (code === 0) {
        resolve();
        return;
      }

      reject(new Error(`${command} exited with code ${code}`));
    });
  });
}
