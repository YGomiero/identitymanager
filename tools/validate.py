"""Check translations, XML manifests, PHP syntax and package hygiene."""
from pathlib import Path
import hashlib, re, subprocess, sys, xml.etree.ElementTree as ET, zipfile, io
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
manifest=ET.parse(root/'package/pkg_identitymanager.xml').getroot()
version=manifest.findtext('version')
manifests=[p for p in files if p.suffix=='.xml' and ET.parse(p).getroot().tag=='extension']
assert len(manifests)==6, 'The package must contain six extension manifests'
assert all(ET.parse(p).getroot().findtext('version')==version for p in manifests), 'Manifest versions differ'
package=root/f'dist/pkg_identitymanager-{version}.zip'
if package.exists():
 feed=ET.parse(root/'dist/update.xml').getroot().find('update')
 assert feed is not None and feed.findtext('version')==version, 'Update feed version differs'
 assert feed.findtext('element')=='pkg_identitymanager' and feed.findtext('type')=='package', 'Wrong update target'
 assert feed.findtext('client')=='site', 'Packages use client_id=0; without client=site Joomla cannot match the installed package'
 assert feed.findtext('downloads/downloadurl')==f'https://github.com/YGomiero/identitymanager-joomla/releases/download/{version}/{package.name}', 'Wrong download URL'
 for algorithm in ['sha256','sha384','sha512']:
  assert feed.findtext(algorithm)==hashlib.new(algorithm,package.read_bytes()).hexdigest(), f'Wrong {algorithm} checksum'
 sums=(root/'dist/SHA256SUMS').read_text(encoding='ascii').splitlines()
 assert len(sums)==2, 'Checksum list must contain the package and feed'
 for line,filename in zip(sums,[package.name,'update.xml']):
  assert line==hashlib.sha256((root/'dist'/filename).read_bytes()).hexdigest()+'  '+filename, f'Wrong checksum list entry: {filename}'
 with zipfile.ZipFile(package) as z:
  for name in z.namelist():
   assert not name.startswith('/') and '..' not in Path(name).parts
   if name.endswith('.zip'):
    with zipfile.ZipFile(io.BytesIO(z.read(name))) as child:
     for path in child.namelist():assert not re.search(r'(^|/)(?:\.env|configuration.php|auth.json|private|tests|\.git)(/|$)',path),path
print(f'Validated {len(files)} source files, {len(ini)} INI files and {len(keys)} language keys.')
