"""Baut die Kartentypen der fireTab-Lagekarte aus den GTA-V-Kacheln von
gtadb.org (Stufe 5, 1 Pixel pro Meter, Nullpunkt der Spielwelt in der
Mitte) im Rahmen der Atlas-Karte (8192 x 8192 Pixel, siehe
App\\Helpers\\MapCoordinates) und schneidet sie in Leaflet-Kacheln der
Stufen 0 bis 5. Herkunft und Lizenz: THIRD_PARTY_NOTICES.md.

Quelle holen (nur Stufe 5 der vier Kartentypen, rund 40 MB):

    git clone --filter=blob:none --no-checkout --depth 1 https://github.com/rolux/gtadb.org gtadb
    cd gtadb
    git sparse-checkout set --no-cone '/maps/tiles/5/satellite/5/' '/maps/tiles/5/hybrid/5/' '/maps/tiles/5/roadmap/5/' '/maps/tiles/5/terrain/5/'
    git checkout main

Aufruf je Kartentyp, danach npm run build (spiegelt nach public/):

    python tools/build_map_tiles.py gtadb/maps/tiles/5/satellite/5 assets/img/map/satellite
"""
import collections
import os
import sys

from PIL import Image

Image.MAX_IMAGE_PIXELS = None

# MapCoordinates: GTA (0, 0) liegt bei 45,8 % / 67,38 % der Atlas-Karte,
# 123,29 bzw. 123,96 GTA-Einheiten je Prozent. gtadb Stufe 5:
# px = x + 16384, py = 16384 - y.
SIZE = 8192
SX = 123.29 * 100 / SIZE
SY = 123.96 * 100 / SIZE
PX0 = 16384 - 45.8 * 123.29
PY0 = 16384 - 67.38 * 123.96
SRC_BOX = (PX0, PY0, PX0 + SIZE * SX, PY0 + SIZE * SY)


def load_tiles(src):
    tiles = {}
    for name in os.listdir(src):
        if name.endswith('.jpg'):
            _, y, x = name[:-4].split(',')
            tiles[(int(y), int(x))] = os.path.join(src, name)
    return tiles


def sea_colour(tiles):
    """Häufigste Farbe der Randkacheln: füllt, wo gtadb keine Kachel hat (offenes Meer)."""
    ys = [y for y, _ in tiles]
    xs = [x for _, x in tiles]
    count = collections.Counter()
    for (y, x), path in tiles.items():
        if y in (min(ys), max(ys)) or x in (min(xs), max(xs)):
            img = Image.open(path).convert('RGB').resize((32, 32))
            for c in img.get_flattened_data() if hasattr(img, 'get_flattened_data') else img.getdata():
                count[tuple(v // 4 * 4 for v in c)] += 1
    return count.most_common(1)[0][0]


def build(src, out, quality=82):
    tiles = load_tiles(src)
    fill = sea_colour(tiles)
    x0, y0 = int(SRC_BOX[0] // 256), int(SRC_BOX[1] // 256)
    x1, y1 = int(SRC_BOX[2] // 256), int(SRC_BOX[3] // 256)
    big = Image.new('RGB', ((x1 - x0 + 1) * 256, (y1 - y0 + 1) * 256), fill)
    for (y, x), path in tiles.items():
        if x0 <= x <= x1 and y0 <= y <= y1:
            big.paste(Image.open(path).convert('RGB'), ((x - x0) * 256, (y - y0) * 256))
    box = (SRC_BOX[0] - x0 * 256, SRC_BOX[1] - y0 * 256, SRC_BOX[2] - x0 * 256, SRC_BOX[3] - y0 * 256)
    level = big.resize((SIZE, SIZE), Image.LANCZOS, box=box)
    del big
    for z in range(5, -1, -1):
        n = 2 ** z
        if level.size[0] != n * 256:
            level = level.resize((n * 256, n * 256), Image.LANCZOS)
        for x in range(n):
            os.makedirs(os.path.join(out, str(z), str(x)), exist_ok=True)
            for y in range(n):
                tile = level.crop((x * 256, y * 256, x * 256 + 256, y * 256 + 256))
                tile.save(os.path.join(out, str(z), str(x), f'{y}.jpg'), quality=quality, optimize=True, progressive=True)
    return fill


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    print('Meeresfarbe:', build(sys.argv[1], sys.argv[2]))
