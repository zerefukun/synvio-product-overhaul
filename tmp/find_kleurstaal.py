import re
stg = open('tmp/staging-home.html', encoding='utf-8', errors='ignore').read()
live = open('tmp/live-home.html', encoding='utf-8', errors='ignore').read()

# look for 'kleur' case-insensitive in staging
print("=== STAGING — 'kleur' matches (first 12) ===")
for i, m in enumerate(re.finditer(r'kleur', stg, re.I)):
    if i >= 12: break
    start = max(0, m.start()-60)
    end = min(len(stg), m.end()+80)
    snippet = re.sub(r'\s+', ' ', stg[start:end])
    print(f"  ...{snippet}...")

print("\n=== LIVE — 'kleurstaal' matches (first 6) ===")
for i, m in enumerate(re.finditer(r'kleurstaal', live, re.I)):
    if i >= 6: break
    start = max(0, m.start()-60)
    end = min(len(live), m.end()+80)
    snippet = re.sub(r'\s+', ' ', live[start:end])
    print(f"  ...{snippet}...")

# Check the specific staging section that says "Zeker zijn van je kleur?"
idx = stg.lower().find('zeker zijn van je kleur')
if idx >= 0:
    print("\n=== STAGING 'Zeker zijn van je kleur' block (600 chars) ===")
    print(re.sub(r'\s+', ' ', stg[idx:idx+800]))
