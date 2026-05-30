import re
from pathlib import Path

root = Path(r'c:\wamp64\www\opendoor-backend-api')
api = root / 'routes' / 'api.php'
html_files = [root / 'public' / 'customer-lifecycle.html', root / 'public' / 'admin-lifecycle.html']
text = api.read_text(encoding='utf-8')
route_entries = []
for m in re.finditer(r"Route::(get|post|patch|delete|apiResource)\s*\(\s*(['\"])([^\'\"]+)\2", text):
    route_entries.append((m.group(1), m.group(3)))

print('ROUTE COUNT', len(route_entries))
for e in route_entries:
    print(e)

print('\n--- HTML PATHS ---')
for f in html_files:
    html = f.read_text(encoding='utf-8')
    paths = re.findall(r'<div class="endpoint-path">([^<]+)</div>', html)
    print(f.name, len(paths))
    for p in paths:
        print(p)
