#!/usr/bin/env python3
"""
Pobiera ResultList_N.pdf z livetiming.pl i wyciąga wynik zawodnika.
Użycie: python3 parse_pdf.py --pdf-url URL --athlete "Wąs Amelia"
"""
import argparse
import json
import re
import sys
import urllib.request
import urllib.error
import io

try:
    import pdfplumber
except ImportError:
    print(json.dumps({"found": False, "error": "pdfplumber not installed"}))
    sys.exit(1)


def parse_athlete_line(text: str, athlete_name: str) -> dict | None:
    """
    Szuka linii z wynikiem zawodnika w wyodrębnionym tekście PDF.
    Format linii: "Wąs Amelia 12 Olimpijczyk Brzesko 5:23.29 542"
    """
    lines = text.split("\n")
    athlete_lower = athlete_name.strip().lower()

    for i, line in enumerate(lines):
        if athlete_lower in line.lower():
            # Czas: m:ss.dd lub ss.dd
            time_match = re.search(r'(\d{1,2}:\d{2}\.\d{2}|\d{2}\.\d{2})', line)
            if not time_match:
                continue
            czas = time_match.group(1)

            # Rok urodzenia: 2-cyfrowa liczba po nazwisku (np. "12" = 2012)
            rok_match = re.search(
                re.escape(athlete_name.strip()) + r'\s+(\d{2})\s+', line, re.IGNORECASE
            )
            rok_ur = None
            if rok_match:
                suffix = int(rok_match.group(1))
                rok_ur = 2000 + suffix if suffix >= 0 else None

            # Punkty: liczba po czasie
            after_time = line[time_match.end():]
            pts_match = re.search(r'\b(\d{3,4})\b', after_time)
            punkty = int(pts_match.group(1)) if pts_match else None

            return {
                "found": True,
                "imie": athlete_name.strip(),
                "czas": czas,
                "rok_urodzenia": rok_ur,
                "punkty": punkty,
            }

    return None


def fetch_pdf_text(pdf_url: str) -> str:
    req = urllib.request.Request(
        pdf_url,
        headers={"User-Agent": "Mozilla/5.0 SwimResults/1.0"},
    )
    with urllib.request.urlopen(req, timeout=15) as resp:
        data = resp.read()

    text_parts = []
    with pdfplumber.open(io.BytesIO(data)) as pdf:
        for page in pdf.pages:
            t = page.extract_text()
            if t:
                text_parts.append(t)
    return "\n".join(text_parts)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--pdf-url", required=True)
    parser.add_argument("--athlete", required=True)
    args = parser.parse_args()

    try:
        text = fetch_pdf_text(args.pdf_url)
    except urllib.error.URLError as e:
        print(json.dumps({"found": False, "error": f"HTTP error: {e}"}))
        sys.exit(0)
    except Exception as e:
        print(json.dumps({"found": False, "error": str(e)}))
        sys.exit(0)

    result = parse_athlete_line(text, args.athlete)
    if result:
        print(json.dumps(result, ensure_ascii=False))
    else:
        print(json.dumps({"found": False, "error": "Athlete not found in PDF"}))


if __name__ == "__main__":
    main()
