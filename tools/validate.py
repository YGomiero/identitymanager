"""Check translations, XML manifests, PHP syntax and package hygiene."""
from pathlib import Path
import re, subprocess, sys, xml.etree.ElementTree as ET, zipfile, io
root=Path(__file__).resolve().parents[1]
php=sys.argv[1] if len(sys.argv)>1 else 'php'
files=[p for p in root.rglob('*') if p.is_file() and not set(p.relative_to(root).parts)&{'vendor','dist','.git'}]
for p in files:
 if p.suffix=='.php':subprocess.run([php,'-l',str(p)],check=True,stdout=subprocess.DEVNULL)
 if p.suffix=='.xml':ET.parse(p)
ini={}
for p in files:
 if p.suffix!='.ini':continue
 values={}
 for line in p.read_text(encoding='utf8').splitlines():
  if not line.strip() or line.startswith(';'):continue
  match=re.fullmatch(r'([A-Z0-9_]+)="(.*)"',line)
  assert match, f'Invalid INI: {p}: {line}'
  key,value=match.groups();assert key not in values,f'Duplicate key {key} in {p}';values[key]=value
 ini[p]=values
for p,values in ini.items():
 if 'fr-FR' not in p.parts:continue
 other=Path(str(p).replace('fr-FR','en-GB'))
 assert other in ini and values.keys()==ini[other].keys(),f'Language parity: {p}'
 for key,value in values.items():
  assert sorted(re.findall(r'%(?:\d+\$)?[sd]',value))==sorted(re.findall(r'%(?:\d+\$)?[sd]',ini[other][key])),f'Placeholder mismatch: {key}'
keys=set().union(*(v.keys() for v in ini.values()))
used=set()
for p in files:
 if p.suffix in {'.php','.xml','.js'}:used.update(re.findall(r'\b(?:COM_IDENTITYMANAGER|PLG_[A-Z_]*IDENTITYMANAGER|PKG_IDENTITYMANAGER)_[A-Z][A-Z0-9_]+\b',p.read_text(encoding='utf8')))
missing={k for k in used-keys if not k.endswith('_')}
assert not missing,f'Missing language keys: {sorted(missing)}'
package=root/'dist/pkg_identitymanager-1.0.0.zip'
if package.exists():
 with zipfile.ZipFile(package) as z:
  for name in z.namelist():
   assert not name.startswith('/') and '..' not in Path(name).parts
   if name.endswith('.zip'):
    with zipfile.ZipFile(io.BytesIO(z.read(name))) as child:
     for path in child.namelist():assert not re.search(r'(^|/)(?:\.env|configuration.php|auth.json|private|tests|\.git)(/|$)',path),path
print(f'Validated {len(files)} source files, {len(ini)} INI files and {len(keys)} language keys.')
