"""Packs a folder into several zip files, each under a size limit (free hosting
refuses uploads over 10 MB). Every part opens on its own into the same folder.

    python3 tools/split-zip.py <folder> <out-dir> [limit in MB]
"""

import os
import sys
import zipfile

folder, out = sys.argv[1], sys.argv[2]
limit = int(float(sys.argv[3] if len(sys.argv) > 3 else 9) * 1024 * 1024)

files = []
for root, dirs, names in os.walk(folder):
    dirs.sort()
    for name in sorted(names):
        path = os.path.join(root, name)
        files.append((os.path.relpath(path, folder), path))

parts = []
current = None


def start():
    global current
    if current:
        current.close()
    name = os.path.join(out, 'htdocs-part-%d.zip' % (len(parts) + 1))
    current = zipfile.ZipFile(name, 'w', zipfile.ZIP_DEFLATED, compresslevel=9)
    parts.append(name)


start()
for rel, path in files:
    current.write(path, rel)
    current.fp.flush()
    if os.path.getsize(parts[-1]) > limit:
        # Over the limit: this file starts the next part instead.
        current.close()
        with zipfile.ZipFile(parts[-1]) as part:
            kept = [i for i in part.infolist() if i.filename != rel]
            data = {i.filename: part.read(i) for i in kept}
        with zipfile.ZipFile(parts[-1], 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as part:
            for info in kept:
                part.writestr(info, data[info.filename])
        current = None
        start()
        current.write(path, rel)
current.close()

for name in parts:
    print('%s  %.1f MB' % (os.path.basename(name), os.path.getsize(name) / 1024 / 1024))
