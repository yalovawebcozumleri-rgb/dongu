import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const roots = [
  path.join(root, 'src'),
  path.join(root, 'styles.ts'),
  path.join(root, 'detailStyles.ts'),
  path.join(root, 'locationBase.ts'),
  path.join(root, 'locationStyles.ts'),
  path.join(root, 'MarketplaceApp.tsx'),
];
const files = [];

function collect(target) {
  const stat = fs.statSync(target);
  if (stat.isFile()) {
    if (/\.(?:ts|tsx)$/.test(target) && !target.endsWith('.bak')) files.push(target);
    return;
  }
  for (const entry of fs.readdirSync(target, { withFileTypes: true })) {
    collect(path.join(target, entry.name));
  }
}

for (const target of roots) collect(target);

const problems = [];
for (const file of files) {
  const source = fs.readFileSync(file, 'utf8');
  const lines = source.split(/\r?\n/);
  lines.forEach((line, index) => {
    for (const match of line.matchAll(/fontSize:\s*(\d+(?:\.\d+)?)/g)) {
      if (Number(match[1]) < 10) problems.push(`${path.relative(root, file)}:${index + 1} fontSize ${match[1]}`);
    }
    if (/allowFontScaling\s*=\s*\{false\}/.test(line)) {
      problems.push(`${path.relative(root, file)}:${index + 1} sistem yazı ölçeklendirmesi kapatılmış`);
    }
  });
}

if (problems.length) {
  console.error('Tipografi denetimi başarısız:');
  problems.forEach(problem => console.error(`- ${problem}`));
  process.exit(1);
}

console.log(`Tipografi denetimi başarılı: ${files.length} dosyada 10 punto altı metin ve kapatılmış yazı ölçeklendirmesi yok.`);
