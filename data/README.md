Place your `cities.json` file in this folder:

- `data/cities.json`

The system uses REST Countries API to convert country names to CCA2 codes (ISO 3166-1 alpha-2, uppercase).
Your cities.json should use CCA2 codes as country identifiers (e.g., "TN" for Tunisia, "FR" for France).

Coordinates are optional but recommended - when provided, they will be automatically saved to the destination.

The local lookup service supports these structures:

1. List of city records with CCA2 codes (with optional coordinates):
[
  {"city": "Paris", "country": "FR", "latitude": 48.8566, "longitude": 2.3522},
  {"city": "Lyon", "country": "FR", "latitude": 45.7640, "longitude": 4.8357},
  {"city": "Tunis", "country": "TN", "latitude": 36.8065, "longitude": 10.1815},
  {"city": "Sfax", "country": "TN", "latitude": 34.7405, "longitude": 10.7605}
]

2. Country (CCA2) -> city list map:
{
  "FR": ["Paris", "Lyon", "Marseille"],
  "TN": ["Tunis", "Sfax", "Sousse"]
}

3. Nested country objects with CCA2 codes and optional coordinates:
[
  {
    "country": "FR",
    "cities": [
      {"city": "Paris", "latitude": 48.8566, "longitude": 2.3522},
      {"city": "Lyon", "latitude": 45.7640, "longitude": 4.8357}
    ]
  },
  {
    "country": "TN",
    "cities": [
      {"city": "Tunis", "latitude": 36.8065, "longitude": 10.1815},
      {"city": "Sfax", "latitude": 34.7405, "longitude": 10.7605}
    ]
  }
]

How it works:
1. User enters country name (e.g., "Tunisia")
2. System converts it to CCA2 code via REST Countries API (e.g., "TN")
3. User enters city name (e.g., "Tunis")
4. System validates city against CCA2-indexed data in cities.json
5. If coordinates exist in cities.json, they are automatically saved to the destination
6. All validation is performed against the CCA2 code
