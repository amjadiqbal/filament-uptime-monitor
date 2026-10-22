"""Generate the Uptime Monitor art set (SVG) in the shared amjadiqbal template.

Usage: python3 -I make_art.py   (writes SVGs next to this file; render.sh makes the PNG/JPG)
No brown: warm colour (rose) appears only as small solid marks and text, never as a fill wash or translucent tint.
"""
import os

HERE = os.path.dirname(os.path.abspath(__file__))
FONT = "Helvetica, Arial, sans-serif"
PKG = "amjadiqbal/filament-uptime-monitor"
TITLE = "Uptime Monitor"
TAGLINE = "Watch your sites and certificates from Filament"
CHIPS = ["add · enable · check now", "uptime + SSL status", "Filament 4 · 5"]

THEMES = {
    "light": dict(bg=("#F8FAFC", "#ECFDF5"), title="#0F172A", accent="#047857", chip_fill="#FFFFFF",
                  chip_stroke="#E2E8F0", chip_text="#334155", card="#FFFFFF", card_stroke="#E2E8F0",
                  muted="#64748B", body="#0F172A", up="#059669", down="#E11D48", gray="#94A3B8", pkg="#94A3B8"),
    "dark": dict(bg=("#0B1220", "#0B2A22"), title="#F8FAFC", accent="#6EE7B7", chip_fill="#111C33",
                 chip_stroke="#273449", chip_text="#CBD5E1", card="#111C33", card_stroke="#273449",
                 muted="#94A3B8", body="#F8FAFC", up="#34D399", down="#FB7185", gray="#64748B", pkg="#64748B"),
}


def icon_body():
    """The 512x512 mark: a monitor ring with a heartbeat line, and a padlock badge for the certificate check."""
    return """
    <rect width="512" height="512" rx="112" fill="url(#iconbg)"/>
    <circle cx="240" cy="240" r="150" fill="none" stroke="#1E3A5F" stroke-width="22"/>
    <path d="M240 90 A150 150 0 1 1 103 300" fill="none" stroke="#34D399" stroke-width="22" stroke-linecap="round"/>
    <polyline points="92,246 170,246 202,180 246,318 284,206 310,246 388,246" fill="none" stroke="#F8FAFC" stroke-width="22" stroke-linecap="round" stroke-linejoin="round"/>
    <circle cx="392" cy="392" r="78" fill="#059669" stroke="#0F172A" stroke-width="14"/>
    <path d="M366 384 v-16 a26 26 0 0 1 52 0 v16" fill="none" stroke="#FFFFFF" stroke-width="11" stroke-linecap="round"/>
    <rect x="352" y="380" width="80" height="58" rx="12" fill="#FFFFFF"/>
    <circle cx="392" cy="405" r="8" fill="#059669"/>
    <rect x="388" y="408" width="8" height="16" rx="3" fill="#059669"/>
"""


def defs(t):
    return f"""  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{t['bg'][0]}"/>
      <stop offset="100%" stop-color="{t['bg'][1]}"/>
    </linearGradient>
    <linearGradient id="iconbg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0F172A"/>
      <stop offset="100%" stop-color="#0B3B2E"/>
    </linearGradient>
  </defs>"""


def chips(t, x, y, size=15, h=32, gap=10, anchor_center=False):
    widths = [round(len(c) * size * 0.56 + 34) for c in CHIPS]
    if anchor_center:
        x = x - (sum(widths) + gap * (len(widths) - 1)) / 2
    out = [f'  <g font-family="{FONT}" font-size="{size}" font-weight="600" fill="{t["chip_text"]}" text-anchor="middle">']
    for c, w in zip(CHIPS, widths):
        out.append(f'<g transform="translate({x:.0f},{y})"><rect width="{w}" height="{h}" rx="{h/2}" fill="{t["chip_fill"]}" stroke="{t["chip_stroke"]}"/>'
                   f'<text x="{w/2}" y="{h*0.66:.2f}">{c}</text></g>')
        x += w + gap
    out.append("  </g>")
    return "\n".join(out)


ROWS = [("shop.example.com", "up", "Up · 12 days", "SSL valid"), ("api.example.com", "down", "Down · 4 min", "SSL valid"),
        ("blog.example.com", "up", "Up · 3 hours", "SSL 9 days left")]


def monitor_card(t, x, y, s=1.0, rows=ROWS):
    """A miniature monitors table: status dot, URL, uptime status, certificate."""
    h = 52 + 44 * len(rows)
    out = [f'  <g transform="translate({x},{y}) scale({s})" font-family="{FONT}">',
           f'    <rect width="300" height="{h}" rx="16" fill="{t["card"]}" stroke="{t["card_stroke"]}" stroke-width="1.5"/>',
           f'    <text x="18" y="30" font-size="12" font-weight="700" fill="{t["muted"]}" letter-spacing="0.6">MONITORS</text>']
    for i, (url, st, status, cert) in enumerate(rows):
        ry = 46 + i * 44
        c = t["up"] if st == "up" else t["down"]
        out.append(f'    <line x1="0" y1="{ry}" x2="300" y2="{ry}" stroke="{t["card_stroke"]}"/>')
        out.append(f'    <circle cx="26" cy="{ry+22}" r="6" fill="{c}"/>')
        out.append(f'    <text x="42" y="{ry+19}" font-size="13" font-weight="700" fill="{t["body"]}">{url}</text>')
        out.append(f'    <text x="42" y="{ry+35}" font-size="11" font-weight="600" fill="{c}">{status}</text>')
        cc = t["muted"] if "valid" in cert else t["down"]
        out.append(f'    <text x="282" y="{ry+27}" font-size="11" fill="{cc}" text-anchor="end">{cert}</text>')
    out.append("  </g>")
    return "\n".join(out)


def svg(w, h, body, t):
    return f'<svg width="{w}" height="{h}" viewBox="0 0 {w} {h}" xmlns="http://www.w3.org/2000/svg">\n{defs(t)}\n  <rect width="{w}" height="{h}" fill="url(#bg)"/>\n{body}\n</svg>\n'


def banner(name):
    t = THEMES[name]
    body = f"""  <g transform="translate(60,75) scale(0.29297)">{icon_body()}</g>
  <text x="235" y="148" font-family="{FONT}" font-size="64" font-weight="700" fill="{t['title']}">{TITLE}</text>
  <text x="238" y="190" font-family="{FONT}" font-size="23" font-weight="500" fill="{t['accent']}" letter-spacing="0.3">{TAGLINE}</text>
{chips(t, 238, 218)}
{monitor_card(t, 905, 34, s=0.88)}
  <text x="1170" y="284" text-anchor="end" font-family="{FONT}" font-size="15" fill="{t['pkg']}">{PKG}</text>"""
    return svg(1200, 300, body, t)


def social():
    t = THEMES["dark"]
    body = f"""  <g transform="translate(490,50) scale(0.5859)">{icon_body()}</g>
  <text x="640" y="430" text-anchor="middle" font-family="{FONT}" font-size="76" font-weight="700" fill="{t['title']}">{TITLE}</text>
  <text x="640" y="482" text-anchor="middle" font-family="{FONT}" font-size="28" font-weight="500" fill="{t['accent']}">Manage spatie/laravel-uptime-monitor from your Filament panel</text>
{chips(t, 640, 530, size=18, h=40, gap=14, anchor_center=True)}
  <text x="1240" y="610" text-anchor="end" font-family="{FONT}" font-size="18" fill="{t['pkg']}">{PKG}</text>"""
    return svg(1280, 640, body, t)


def directory():
    """16:9 LIGHT listing image for filamentphp.com, authored at 1280x720, rendered at 2560x1440.
    Zoomed into the feature: no panel sidebar or top bar."""
    t = THEMES["light"]
    body = f"""  <g transform="translate(90,80) scale(0.25)">{icon_body()}</g>
  <text x="90" y="290" font-family="{FONT}" font-size="64" font-weight="700" fill="{t['title']}">{TITLE}</text>
  <text x="92" y="338" font-family="{FONT}" font-size="26" font-weight="500" fill="{t['accent']}">Uptime and SSL checks, managed in Filament</text>
  <g font-family="{FONT}" font-size="21" fill="{t['chip_text']}">
    <text x="92" y="410">• Add, edit, enable and disable monitors</text>
    <text x="92" y="448">• Uptime and certificate status at a glance</text>
    <text x="92" y="486">• Check now, one by one or in bulk</text>
    <text x="92" y="524">• Dashboard widget for what is down</text>
  </g>
{monitor_card(t, 690, 150, s=1.8)}
  <text x="1190" y="690" text-anchor="end" font-family="{FONT}" font-size="18" fill="{t['pkg']}">{PKG}</text>"""
    return svg(1280, 720, body, t).replace('width="1280" height="720"', 'width="2560" height="1440"', 1)


def icon():
    return f'<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">\n{defs(THEMES["dark"])}\n<g>{icon_body()}</g>\n</svg>\n'


if __name__ == "__main__":
    files = {"icon.svg": icon(), "banner-light.svg": banner("light"), "banner-dark.svg": banner("dark"),
             "social-preview.svg": social(), "filament-directory.svg": directory()}
    for f, s in files.items():
        with open(os.path.join(HERE, f), "w") as fh:
            fh.write(s)
        print("wrote", f)
