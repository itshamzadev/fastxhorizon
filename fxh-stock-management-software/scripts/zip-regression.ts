import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import archiverModule = require('archiver');

const archiver = (_format: string, options?: Record<string, unknown>) => new archiverModule.ZipArchive(options);
async function run() {
const root = fs.mkdtempSync(path.join(os.tmpdir(), 'fxh-zip-'));
const source = path.join(root, 'media', 'products'); fs.mkdirSync(source, { recursive: true });
fs.writeFileSync(path.join(source, 'photo.webp'), Buffer.from('test-image'));
const zip = path.join(root, 'website-export.zip'); const partial = `${zip}.${randomUUID()}.partial`;
const output = fs.createWriteStream(partial, { flags: 'wx' }); const archive = archiver('zip', { zlib: { level: 6 } }); archive.pipe(output); archive.append('{"formatVersion":2}', { name: 'manifest.json' }); archive.file(path.join(source, 'photo.webp'), { name: 'images/photo.webp' });
await new Promise<void>((resolve, reject) => { let done = false; const fail = (error: unknown) => { if (!done) { done = true; reject(error); } }; output.once('close', () => { if (!done) { done = true; resolve(); } }); output.once('error', fail); archive.once('error', fail); void archive.finalize().catch(fail); });
fs.renameSync(partial, zip);
const extracted = path.join(root, 'extracted'); execFileSync('powershell.exe', ['-NoProfile', '-NonInteractive', '-Command', `Expand-Archive -LiteralPath '${zip.replace(/'/g, "''")}' -DestinationPath '${extracted.replace(/'/g, "''")}' -Force`]);
assert.equal(fs.readFileSync(path.join(extracted, 'manifest.json'), 'utf8'), '{"formatVersion":2}'); assert.equal(fs.readFileSync(path.join(extracted, 'images', 'photo.webp'), 'utf8'), 'test-image'); assert.equal(fs.existsSync(partial), false);
fs.rmSync(root, { recursive: true, force: true }); console.log('ZIP regression test passed: finalized archive extracted with Windows Expand-Archive.');
}
void run();
