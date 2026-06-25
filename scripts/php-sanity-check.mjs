import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve('wpagent');
const errors = [];

async function walk(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      await walk(full);
    } else if (entry.name.endsWith('.php')) {
      await checkPhp(full);
    }
  }
}

async function checkPhp(file) {
  const source = await readFile(file, 'utf8');
  const opens = (source.match(/<\?php/g) || []).length;
  if (opens < 1) {
    errors.push(`${file}: missing PHP open tag`);
  }
  if (!source.includes("defined( 'ABSPATH' )") && !file.endsWith('wpagent.php')) {
    errors.push(`${file}: missing ABSPATH guard`);
  }
}

await walk(root);

if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}

console.log('PHP sanity checks passed.');
