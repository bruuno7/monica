#!/usr/bin/env node
/**
 * Add translation strings to lang/en.json and lang/es.json without touching existing keys.
 *
 * Usage: node scripts/add-translations.mjs translations.json
 * where translations.json is a map of { "English source": "Traducción en español" }.
 * New keys are appended; `php artisan localize` re-sorts the files when it runs.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const [, , input] = process.argv;
if (!input) {
  console.error('Usage: node scripts/add-translations.mjs translations.json');
  process.exit(1);
}

const additions = JSON.parse(readFileSync(input, 'utf8'));
const root = resolve(new URL('..', import.meta.url).pathname);

const update = (file, valueFor) => {
  const path = resolve(root, 'lang', file);
  const content = JSON.parse(readFileSync(path, 'utf8'));
  let added = 0;

  for (const [key, translation] of Object.entries(additions)) {
    if (content[key] === undefined) {
      content[key] = valueFor(key, translation);
      added++;
    }
  }

  writeFileSync(path, JSON.stringify(content, null, 2) + '\n');
  console.log(`${file}: ${added} key(s) added`);
};

update('en.json', (key) => key);
update('es.json', (key, translation) => translation);
