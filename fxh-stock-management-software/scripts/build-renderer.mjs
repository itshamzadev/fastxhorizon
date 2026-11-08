import { build } from 'esbuild';
import { mkdir, rm, writeFile } from 'node:fs/promises';

await rm('dist', { recursive: true, force: true });
await mkdir('dist/assets', { recursive: true });

await build({
  entryPoints: ['src/main.tsx'],
  bundle: true,
  format: 'iife',
  platform: 'browser',
  target: ['chrome120'],
  outfile: 'dist/assets/index.js',
  assetNames: 'assets/[name]-[hash]',
  loader: { '.tsx': 'tsx', '.ts': 'ts', '.css': 'css' },
  sourcemap: false,
  legalComments: 'none',
});

await writeFile('dist/index.html', `<!doctype html>
<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>FASTXHORIZON Stock Manager</title><link rel="stylesheet" href="./assets/index.css"></head><body><div id="root"></div><script src="./assets/index.js"></script></body></html>`);
