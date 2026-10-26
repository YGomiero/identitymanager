"""Build the installable Joomla package and its checksummed update feed."""
from pathlib import Path
import hashlib, io, json, zipfile, xml.etree.ElementTree as ET

ROOT=Path(__file__).resolve().parents[1]
VERSION=ET.parse(ROOT/'package/pkg_identitymanager.xml').getroot().findtext('version')
REPO='https://github.com/YGomiero/identitymanager-joomla'
DIST=ROOT/'dist'
DIST.mkdir(exist_ok=True)

def archive(folder):
    content=io.BytesIO()
    with zipfile.ZipFile(content,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
        for p in sorted(folder.rglob('*')):
            if not p.is_file() or any(x in p.parts for x in ['.git','node_modules','tests','.github']):continue
            relative=p.relative_to(folder).as_posix()
            if p.name in ['composer.json','composer.lock'] or p.name.startswith('.') or p.suffix in ['.yml','.yaml','.dist']:continue
            if 'vendor' in p.parts and p.suffix != '.php' and p.name not in ['LICENSE','LICENSE.md','LICENSE.txt','COPYING','installed.json']:continue
            payload=p.read_bytes()
            if 'vendor' in p.parts and p.suffix == '.php':
                dependency_license='BSD 3-Clause License' if 'firebase' in p.parts else 'MIT License'
                notice=('\n/** Third-party dependency. @license '+dependency_license+'. Original notices and license file are retained. */\n').encode()
                payload=payload.replace(b'<?php',b'<?php'+notice,1)
            info=zipfile.ZipInfo(relative,(2026,10,26,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16
            z.writestr(info,payload)
    return content.getvalue()

if not (ROOT/'component/site/vendor/autoload.php').exists():raise SystemExit('Run composer install --no-dev in component/site first.')
package=io.BytesIO()
with zipfile.ZipFile(package,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
    for p in sorted((ROOT/'package').rglob('*')):
        if p.is_file():
            info=zipfile.ZipInfo(p.relative_to(ROOT/'package').as_posix(),(2026,10,26,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,p.read_bytes())
    for name,folder in [('com_identitymanager',ROOT/'component')]+[(f'plg_{group}_{name}',ROOT/'plugins'/group/name) for group,name in [('system','identitymanager'),('authentication','identitymanager'),('identitymanager','google'),('identitymanager','hybridauth')]]:
        info=zipfile.ZipInfo('packages/'+name+'.zip',(2026,10,26,0,0,0));info.compress_type=zipfile.ZIP_DEFLATED;z.writestr(info,archive(folder))
filename=f'pkg_identitymanager-{VERSION}.zip'
data=package.getvalue();(DIST/filename).write_bytes(data)
sha256=hashlib.sha256(data).hexdigest();sha384=hashlib.sha384(data).hexdigest();sha512=hashlib.sha512(data).hexdigest()
feed=f'''<?xml version="1.0" encoding="utf-8"?>
<updates><update>
<name>Identity Manager</name><description>External sign-in and Joomla identity provider</description>
<element>pkg_identitymanager</element><type>package</type><version>{VERSION}</version>
<infourl title="Identity Manager">{REPO}/releases/tag/{VERSION}</infourl>
<downloads><downloadurl type="full" format="zip">{REPO}/releases/download/{VERSION}/{filename}</downloadurl></downloads>
<tags><tag>stable</tag></tags><maintainer>Yann Gomiero</maintainer><maintainerurl>{REPO}</maintainerurl>
<targetplatform name="joomla" version="6\\.[0-9]+"/><php_minimum>8.3.0</php_minimum>
<sha256>{sha256}</sha256><sha384>{sha384}</sha384><sha512>{sha512}</sha512>
</update></updates>
'''
(DIST/'update.xml').write_text(feed,encoding='utf-8')
(DIST/'SHA256SUMS').write_text(sha256+'  '+filename+'\n'+hashlib.sha256(feed.encode()).hexdigest()+'  update.xml\n',encoding='ascii')
print(json.dumps({'package':filename,'bytes':len(data),'sha256':sha256,'version':VERSION}))
