import re
from html.parser import HTMLParser

def strip_tags(s):
    s = re.sub(r'<script[^>]*>.*?</script>', '', s, flags=re.S|re.I)
    s = re.sub(r'<style[^>]*>.*?</style>', '', s, flags=re.S|re.I)
    s = re.sub(r'<!--.*?-->', '', s, flags=re.S)
    return s

def load(path):
    with open(path, encoding='utf-8', errors='ignore') as f:
        return f.read()

def extract(html, tag):
    return re.findall(rf'<{tag}[^>]*>(.*?)</{tag}>', html, re.S|re.I)

def clean_text(t):
    t = re.sub(r'<[^>]+>', ' ', t)
    t = re.sub(r'\s+', ' ', t)
    return t.strip()

def section_summary(html, label):
    html = strip_tags(html)
    print(f"\n{'='*70}\n{label}\n{'='*70}")

    # Title & meta
    m = re.search(r'<title[^>]*>(.*?)</title>', html, re.I|re.S)
    print(f"TITLE: {clean_text(m.group(1)) if m else '(missing)'}")
    m = re.search(r'<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)', html, re.I)
    print(f"META DESC: {clean_text(m.group(1)) if m else '(missing)'}")

    # Headings
    for h in ('h1','h2','h3'):
        items = [clean_text(x) for x in extract(html, h)]
        items = [x for x in items if x]
        print(f"\n{h.upper()} ({len(items)}):")
        for i, x in enumerate(items, 1):
            print(f"  {i}. {x[:140]}")

    # Images with alt
    imgs = re.findall(r'<img[^>]+>', html, re.I)
    img_alts = []
    missing_alt = 0
    for i in imgs:
        src_m = re.search(r'src=["\']([^"\']+)', i)
        alt_m = re.search(r'alt=["\']([^"\']*)', i)
        if src_m:
            src = src_m.group(1)
            if 'data:image' in src or src.endswith('.svg'):
                continue
            alt = alt_m.group(1) if alt_m else None
            img_alts.append((src.rsplit('/',1)[-1][:60], alt))
            if not alt:
                missing_alt += 1
    print(f"\nIMAGES: {len(img_alts)} ({missing_alt} missing alt)")

    # Links with visible text
    a_tags = re.findall(r'<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>', html, re.S|re.I)
    cta_like = []
    for href, txt in a_tags:
        t = clean_text(txt)
        if not t or len(t) < 3 or len(t) > 60:
            continue
        if any(w in t.lower() for w in ('bestel','shop','bekijk','lees','meer','offerte','vraag','aanvraag','contact','kopen','naar','start','begin')):
            cta_like.append((t, href))
    seen = set()
    unique_ctas = []
    for t,h in cta_like:
        key = (t.lower(), h)
        if key not in seen:
            seen.add(key)
            unique_ctas.append((t,h))
    print(f"\nCTA-LIKE LINKS ({len(unique_ctas)}):")
    for t,h in unique_ctas[:40]:
        print(f"  - {t[:50]} -> {h[:80]}")

    return img_alts

live = load('tmp/live-home.html')
stg = load('tmp/staging-home.html')
live_imgs = section_summary(live, 'LIVE (beton-cire-webshop.nl)')
stg_imgs = section_summary(stg, 'STAGING (staging.beton-cire-webshop.nl)')

# Image diff
print(f"\n{'='*70}\nIMAGE DIFF (filenames present on live but NOT on staging):\n{'='*70}")
live_set = {x[0] for x in live_imgs}
stg_set = {x[0] for x in stg_imgs}
missing = sorted(live_set - stg_set)
for m in missing:
    print(f"  MISSING ON STAGING: {m}")
added = sorted(stg_set - live_set)
print(f"\nStaging-only images ({len(added)}):")
for m in added[:20]:
    print(f"  + {m}")
