import re, html
live = open('tmp/live-home.html', encoding='utf-8', errors='ignore').read()
live = re.sub(r'<script[^>]*>.*?</script>', '', live, flags=re.S|re.I)
live = re.sub(r'<style[^>]*>.*?</style>', '', live, flags=re.S|re.I)

def section_around(pattern, ctx_before=500, ctx_after=2500):
    m = re.search(pattern, live, re.I)
    if not m: return None
    s = max(0, m.start()-ctx_before)
    e = min(len(live), m.end()+ctx_after)
    return live[s:e]

def clean(s):
    s = re.sub(r'<[^>]+>', ' ', s)
    s = html.unescape(s)
    s = re.sub(r'\s+', ' ', s)
    return s.strip()

# Lavasteen
print("="*70, "\nLAVASTEEN block (live):\n", "="*70)
s = section_around(r'>Beton Cir\S? Lavasteen<', 300, 2500)
print(clean(s) if s else 'NOT FOUND')

# Belangrijkste punten
print("\n" + "="*70, "\nBELANGRIJKSTE PUNTEN (live):\n", "="*70)
s = section_around(r'>Belangrijkste punten<', 300, 3000)
print(clean(s) if s else 'NOT FOUND')

# Is Beton cire kwetsbaar?
print("\n" + "="*70, "\nIs Beton cire kwetsbaar? (live):\n", "="*70)
s = section_around(r'Is Beton cir\S? kwetsbaar', 200, 2000)
print(clean(s) if s else 'NOT FOUND')

# Wat zijn de nadelen
print("\n" + "="*70, "\nWat zijn de nadelen? (live):\n", "="*70)
s = section_around(r'Wat zijn de nadelen van Beton', 100, 1500)
print(clean(s) if s else 'NOT FOUND')

# keukenbladen en spatschermen
print("\n" + "="*70, "\nkeukenbladen en spatschermen (live):\n", "="*70)
s = section_around(r'keukenbladen en spatschermen', 100, 800)
print(clean(s) if s else 'NOT FOUND')

# DIY / aanbrengen
print("\n" + "="*70, "\naanbrengen DIY (live):\n", "="*70)
s = section_around(r'aanbrengen DIY', 100, 2500)
print(clean(s) if s else 'NOT FOUND')
