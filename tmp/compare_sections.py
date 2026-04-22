import re

def load(path):
    with open(path, encoding='utf-8', errors='ignore') as f:
        return f.read()

def clean(t):
    t = re.sub(r'<script[^>]*>.*?</script>', '', t, flags=re.S|re.I)
    t = re.sub(r'<style[^>]*>.*?</style>', '', t, flags=re.S|re.I)
    t = re.sub(r'<!--.*?-->', '', t, flags=re.S)
    return t

def tx(s):
    s = re.sub(r'<[^>]+>', ' ', s)
    s = re.sub(r'\s+', ' ', s)
    return s.strip()

live = clean(load('tmp/live-home.html'))
stg = clean(load('tmp/staging-home.html'))

# All headings on each, in DOCUMENT ORDER
def all_headings(html):
    return [(m.group(1), tx(m.group(2))) for m in re.finditer(r'<(h[1-6])[^>]*>(.*?)</\1>', html, re.S|re.I)]

print("="*70)
print("LIVE HEADINGS IN ORDER")
print("="*70)
for tag, txt in all_headings(live):
    if txt: print(f"  {tag.upper()}: {txt[:100]}")

print("\n" + "="*70)
print("STAGING HEADINGS IN ORDER")
print("="*70)
for tag, txt in all_headings(stg):
    if txt: print(f"  {tag.upper()}: {txt[:100]}")

# Check specific phrases on live
print("\n" + "="*70)
print("KEYWORD PRESENCE CHECK (phrase -> live / staging)")
print("="*70)
phrases = [
    "Beton Cir\u00e9 Lavasteen",
    "Beton Cir\u00e9 All-In-One",
    "Beton Cir\u00e9 Original",
    "Beton Cir\u00e9 Easyline",
    "Belangrijkste punten",
    "kleurstaal",
    "Kleurstaal",
    "Patrick",
    "Kant &amp; Klaar",
    "showroom",
    "Showroom",
    "Den Haag",
    "workshop",
    "Workshop",
    "0850270090",
    "085 027 0090",
    "tel:0850",
    "kennisbank",
    "Kennisbank",
    "DIY",
    "stucadoor",
    "Beton cire vloer All in one",
    "Professioneel laten aanbrengen",
    "Beton Cir\u00e9 keukenbladen",
    "spatschermen",
    "Is Beton cir\u00e9 kwetsbaar",
    "De wens",
    "Easyline",
    "garantie",
    "Garantie",
]
for p in phrases:
    l = p.lower() in live.lower()
    s = p.lower() in stg.lower()
    flag = "  " if (l == s) else "!!"
    print(f"  {flag} live:{'Y' if l else 'N'}  staging:{'Y' if s else 'N'}  -- {p}")
