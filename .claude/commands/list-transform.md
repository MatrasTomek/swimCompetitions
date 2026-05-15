---
description: Transform a swimming competition start list PDF into a JSON file for Olimpijczyk Brzesko
---

Analyse the start list PDF provided as argument: $ARGUMENTS

## Goal

Generate a competition JSON file containing **only the entries of swimmers from Olimpijczyk Brzesko** (also match variants: "Olimpijczyk", "OLI Brzesko", "UKS Olimpijczyk").

## Output format

Produce valid JSON matching the structure below. Save it as a file in `zawody/` — filename: slugified competition name (lowercase, spaces → underscores, no Polish diacritics), e.g. `zawody/mistrzostwa_powiatu_2026.json`.

```json
{
	"nazwa": "Full competition name",
	"miejsce": "City",
	"data": "DD/MM/YYYY",
	"klub": "Olimpijczyk Brzesko",
	"basen": "25m",
	"bloki": [
		{
			"blok": "I",
			"data": "DD/MM/YYYY",
			"godz_start": "HH:MM",
			"starty": [
				{
					"imie": "Surname Firstname",
					"konkurencja": "Category, distance stroke",
					"konkurencja_nr": 1,
					"seria": "X z Y",
					"godz": "HH:MM",
					"tor": 4,
					"czas": "M:SS.ss"
				}
			]
		}
	]
}
```

## Extraction rules

### Competition metadata

- `nazwa` — full competition name from the PDF header
- `miejsce` — city / venue from the header
- `data` — format `DD/MM/YYYY`; for multi-day meets `D-D/MM/YYYY` (e.g. `9-10/5/2026`)
- `basen` — `"25m"` or `"50m"` (read from the document; default to `"25m"`)
- `klub` — always `"Olimpijczyk Brzesko"`

### Blocks

- A block = one session (Morning, Afternoon, Block A/B/I/II, etc.)
- `blok` — session label from the document (Roman numeral or letter), e.g. `"I"`, `"II"`, `"A"`
- `godz_start` — warm-up / session start time from the document
- If the meet is single-session, create one block

### Entries (Olimpijczyk Brzesko only)

- `imie` — **Surname Firstname** (surname first, then first name) — exactly as in the PDF
- `konkurencja` — `"Category, distance stroke"`, e.g.:
    - `"Kobiet, 50m dowolny"` / `"Mężczyzn, 100m klasyczny"`
    - `"Dziewcząt, 25m motylkowy"` / `"Chłopców, 200m zmienny"`
    - Categories: Kobiet / Mężczyzn / Dziewcząt / Chłopców / Juniorek / Juniorów / Open
    - Strokes: dowolny / klasyczny / grzbietowy / motylkowy / zmienny
- `konkurencja_nr` — event number (integer)
- `seria` — format `"X z Y"` (e.g. `"3 z 6"`)
- `godz` — scheduled start time of the heat, format `"HH:MM"`
- `tor` — lane number (integer)
- `czas` — entry time:
    - `"SS.ss"` for times under one minute (e.g. `"32.06"`)
    - `"M:SS.ss"` for ≥ one minute (e.g. `"1:13.30"`, `"5:02.35"`)
    - If no entry time: omit the field or use `"NT"`

### Sorting

- Within each block, sort entries ascending by `godz` (heat start time)

## Example entry

```json
{
	"imie": "Imię Nazwisko",
	"konkurencja_nr": 3,
	"konkurencja": "Kobiet, 400m zmienny",
	"seria": "1 z 5",
	"godz": "8:41",
	"tor": 6,
	"czas": "5:23.71"
}
```

## Steps

1. Read the PDF: `Read $ARGUMENTS`
2. Identify competition metadata (name, venue, date, pool length)
3. Identify block/session structure
4. For each event and heat — extract entries belonging to Olimpijczyk Brzesko
5. Build the JSON and save to `zawody/<filename>.json`
6. Print a summary: number of blocks, total entries, unique athlete names

