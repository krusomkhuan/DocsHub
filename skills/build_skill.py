"""สร้างไฟล์สกิล Docs Hub สำหรับ ChatGPT

    python skills/build_skill.py

ผลลัพธ์:
  skills/docs-hub/references/schema.sql        โครงสร้างฐานข้อมูลล่าสุด (ไม่มีข้อมูล)
  skills/docs-hub/assets/docs-hub-source.zip   ซอร์สโค้ด (ไม่มีไฟล์ที่อัปโหลด ไฟล์สำรอง ผู้ใช้ หรือค่าลับ)
  skills/docs-hub-skill.zip                    โฟลเดอร์สกิล docs-hub/ สำหรับอัปโหลดเข้า ChatGPT
ต้องเปิด MySQL ของ XAMPP ไว้ (ใช้ tools/dump-schema.php อ่านโครงสร้างฐานข้อมูล)
"""
import os
import re
import subprocess
import zipfile

SKILLS = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(SKILLS)
SKILL = os.path.join(SKILLS, 'docs-hub')
PHP = r'C:\xampp\php\php.exe'

# ไม่เอาเข้า zip (ข้อมูลผู้ใช้/ไฟล์ที่อัปโหลด/ไฟล์สำรอง/ไฟล์เฉพาะเครื่อง)
SKIP_DIRS = {'.git', '.claude', 'skills', '__pycache__'}
SKIP_FILES = [r'^includes/installed\.lock$', r'^database/docshub\.sql$', r'\.log$', r'\.pyc$',
              r'^uploads/(?!\.htaccess$|docs/\.htaccess$).+', r'^backups/(?!\.htaccess$).+']
# ถ้าพบในไฟล์ใด ให้หยุดทันที
SECRET_PATTERNS = [rb'GOCSPX-[A-Za-z0-9_-]{10,}', rb'\b1//0[A-Za-z0-9_-]{20,}', rb'\bya29\.[A-Za-z0-9_-]{20,}',
                   rb'\$2y\$\d\d\$[./A-Za-z0-9]{50,}', rb'-----BEGIN [A-Z ]*PRIVATE KEY-----']


def php(*args):
    out = subprocess.run([PHP, os.path.join(ROOT, 'tools', 'dump-schema.php'), *args], capture_output=True)
    if out.returncode != 0 or b'CREATE TABLE' not in out.stdout:
        raise SystemExit('สร้างโครงสร้างฐานข้อมูลไม่สำเร็จ (เปิด MySQL แล้วหรือยัง?)\n' + out.stderr.decode('utf-8', 'replace'))
    return out.stdout


def check_secret(rel, data):
    for p in SECRET_PATTERNS:
        if re.search(p, data):
            raise SystemExit('พบข้อมูลที่อาจเป็นความลับใน ' + rel)
    if rel == 'includes/config.php' and re.search(rb"define\('DB_PASS',\s*'[^']+'\)", data):
        raise SystemExit('includes/config.php มีรหัสผ่านฐานข้อมูล — ล้างค่า DB_PASS ก่อนสร้างสกิล')


def source_files():
    out = []
    for base, dirs, files in os.walk(ROOT):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for f in files:
            rel = os.path.relpath(os.path.join(base, f), ROOT).replace('\\', '/')
            if not any(re.search(p, rel) for p in SKIP_FILES):
                out.append(rel)
    return sorted(out)


def build():
    os.makedirs(os.path.join(SKILL, 'assets'), exist_ok=True)
    with open(os.path.join(SKILL, 'references', 'schema.sql'), 'wb') as f:
        f.write(php())
    install_sql = php('--install')
    check_secret('database/docshub.sql (--install)', install_sql)

    src_zip = os.path.join(SKILL, 'assets', 'docs-hub-source.zip')
    files = source_files()
    with zipfile.ZipFile(src_zip, 'w', zipfile.ZIP_DEFLATED) as z:
        for rel in files:
            data = open(os.path.join(ROOT, rel), 'rb').read()
            check_secret(rel, data)
            z.writestr('docshub/' + rel, data)
        # ไฟล์ติดตั้งที่สะอาด แทน database/docshub.sql ของเครื่อง (ซึ่งอาจเป็นไฟล์ export ที่มีผู้ใช้จริง)
        z.writestr('docshub/database/docshub.sql', install_sql)
    print('source zip: %d files, %.0f KB' % (len(files) + 1, os.path.getsize(src_zip) / 1024))

    skill_zip = os.path.join(SKILLS, 'docs-hub-skill.zip')
    with zipfile.ZipFile(skill_zip, 'w', zipfile.ZIP_DEFLATED) as z:
        for base, _, names in os.walk(SKILL):
            for f in sorted(names):
                full = os.path.join(base, f)
                z.write(full, os.path.relpath(full, SKILLS).replace('\\', '/'))
    with zipfile.ZipFile(skill_zip) as z:
        skill_md = [n for n in z.namelist() if n.lower().endswith('skill.md')]
        if skill_md != ['docs-hub/SKILL.md']:
            raise SystemExit('ต้องมี SKILL.md เพียงไฟล์เดียวที่ docs-hub/SKILL.md')
        print('skill zip: %d files, %.0f KB' % (len(z.namelist()), os.path.getsize(skill_zip) / 1024))
        for n in z.namelist():
            print('  ' + n)


if __name__ == '__main__':
    build()
