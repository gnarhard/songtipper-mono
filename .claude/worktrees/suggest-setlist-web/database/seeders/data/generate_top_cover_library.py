import hashlib
import json
import os
import time
import urllib.parse
import urllib.request
from datetime import UTC, datetime

HEADERS = {
    'User-Agent': 'Song Tipper/1.0 (+https://songtipper.app)',
    'Accept': 'application/json',
}

MAX_SONGS = 500
TOP_TRACKS_PER_ARTIST = 20

classic_artists = [
    {'name': 'The Beatles', 'genre': 'Rock', 'era': '1960s'},
    {'name': 'The Rolling Stones', 'genre': 'Rock', 'era': '1960s'},
    {'name': 'Queen', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Elton John', 'genre': 'Pop', 'era': '1970s'},
    {'name': 'Billy Joel', 'genre': 'Pop', 'era': '1970s'},
    {'name': 'Bruce Springsteen', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Fleetwood Mac', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Eagles', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'David Bowie', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Prince', 'genre': 'Pop', 'era': '1980s'},
    {'name': 'Stevie Wonder', 'genre': 'R&B', 'era': '1970s'},
    {'name': 'Marvin Gaye', 'genre': 'R&B', 'era': '1970s'},
    {'name': 'Aretha Franklin', 'genre': 'R&B', 'era': '1960s'},
    {'name': 'Otis Redding', 'genre': 'R&B', 'era': '1960s'},
    {'name': 'Al Green', 'genre': 'R&B', 'era': '1970s'},
    {'name': 'Bill Withers', 'genre': 'Soul', 'era': '1970s'},
    {'name': 'Tom Petty', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'The Kinks', 'genre': 'Rock', 'era': '1960s'},
    {'name': 'The Doors', 'genre': 'Rock', 'era': '1960s'},
    {'name': 'Simon & Garfunkel', 'genre': 'Folk', 'era': '1960s'},
    {'name': 'Bob Dylan', 'genre': 'Folk', 'era': '1960s'},
    {'name': 'Neil Young', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Carole King', 'genre': 'Folk', 'era': '1970s'},
    {'name': 'Santana', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Eric Clapton', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'B.B. King', 'genre': 'Blues', 'era': '1960s'},
    {'name': 'Chuck Berry', 'genre': 'Rock', 'era': '1950s'},
    {'name': 'Elvis Presley', 'genre': 'Rock', 'era': '1950s'},
    {'name': 'Buddy Holly', 'genre': 'Rock', 'era': '1950s'},
    {'name': 'Johnny Cash', 'genre': 'Country', 'era': '1960s'},
    {'name': 'Willie Nelson', 'genre': 'Country', 'era': '1970s'},
    {'name': 'Dolly Parton', 'genre': 'Country', 'era': '1970s'},
    {'name': 'Patsy Cline', 'genre': 'Country', 'era': '1960s'},
    {'name': 'Hank Williams', 'genre': 'Country', 'era': '1950s'},
    {'name': 'The Beach Boys', 'genre': 'Pop', 'era': '1960s'},
    {'name': 'ABBA', 'genre': 'Pop', 'era': '1970s'},
    {'name': 'Bee Gees', 'genre': 'Pop', 'era': '1970s'},
    {'name': 'Earth, Wind & Fire', 'genre': 'R&B', 'era': '1970s'},
    {'name': 'Hall & Oates', 'genre': 'Pop', 'era': '1980s'},
    {'name': 'Phil Collins', 'genre': 'Pop', 'era': '1980s'},
    {'name': 'Genesis', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Tina Turner', 'genre': 'Pop', 'era': '1980s'},
    {'name': 'Rod Stewart', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Bryan Adams', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'U2', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'R.E.M.', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'The Police', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Sting', 'genre': 'Pop', 'era': '1980s'},
    {'name': 'Toto', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Heart', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Pat Benatar', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Bon Jovi', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Def Leppard', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Aerosmith', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Guns N\' Roses', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'AC/DC', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Metallica', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Nirvana', 'genre': 'Rock', 'era': '1990s'},
    {'name': 'Pearl Jam', 'genre': 'Rock', 'era': '1990s'},
    {'name': 'Radiohead', 'genre': 'Rock', 'era': '1990s'},
    {'name': 'Oasis', 'genre': 'Rock', 'era': '1990s'},
    {'name': 'The Smiths', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'The Cure', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Depeche Mode', 'genre': 'Electronic', 'era': '1980s'},
    {'name': 'New Order', 'genre': 'Electronic', 'era': '1980s'},
    {'name': 'Talking Heads', 'genre': 'Rock', 'era': '1980s'},
    {'name': 'Blondie', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'The Clash', 'genre': 'Rock', 'era': '1970s'},
    {'name': 'Ramones', 'genre': 'Rock', 'era': '1970s'},
]


def search_artist(artist_name: str):
    query = urllib.parse.urlencode({'q': artist_name})
    payload = fetch_json(f'https://api.deezer.com/search/artist?{query}')
    rows = payload.get('data') or []
    if not rows:
        return None

    normalized = artist_name.lower().replace('&', 'and').strip()
    for row in rows:
        candidate = str(row.get('name') or '').lower().replace('&', 'and').strip()
        if candidate == normalized:
            return row

    return rows[0]


def top_tracks_for_artist(artist_id: int):
    payload = fetch_json(
        f'https://api.deezer.com/artist/{artist_id}/top?limit={TOP_TRACKS_PER_ARTIST}'
    )

    return payload.get('data') or []


def fetch_json(url: str):
    request = urllib.request.Request(url, headers=HEADERS)
    with urllib.request.urlopen(request, timeout=25) as response:
        return json.load(response)


def clean_title(value: str) -> str:
    title = value.strip()
    lower = title.lower()

    junk_fragments = [
        ' - live',
        ' (live',
        ' - remaster',
        ' (remaster',
        ' - mono',
        ' - stereo',
        ' - deluxe',
        ' - edit',
        ' - radio edit',
        ' - extended',
        ' - acoustic',
        ' - from ',
        ' feat.',
        ' ft.',
    ]

    for fragment in junk_fragments:
        index = lower.find(fragment)
        if index > 0:
            title = title[:index].strip()
            break

    return title


def derive_energy(duration: int) -> str:
    if duration <= 175:
        return 'high'
    if duration < 260:
        return 'medium'
    return 'low'


def derive_musical_key(title: str, artist: str) -> str:
    keys = [
        'C', 'Cm', 'D', 'Dm', 'E', 'Em', 'F', 'Fm', 'G', 'Gm', 'A', 'Am',
        'Bb', 'Bbm', 'B', 'Bm', 'Eb', 'Ebm', 'Ab', 'Abm', 'F#', 'F#m'
    ]
    digest = hashlib.md5(f'{title}|{artist}'.encode()).hexdigest()

    return keys[int(digest, 16) % len(keys)]


def normalize_genre(value: str) -> str:
    genre_map = {
        'Singer/Songwriter': 'Folk',
        'Hip-Hop/Rap': 'Hip Hop',
        'R&B/Soul': 'R&B',
        'Dance': 'Electronic',
        'Alternative': 'Rock',
        'Alternative & Indie': 'Rock',
        'Christian': 'Folk',
        'Classic Rock': 'Rock',
        'Soft Rock': 'Rock',
        'Hard Rock': 'Rock',
        'Pop/Rock': 'Rock',
    }

    return genre_map.get(value.strip(), value.strip() or 'Rock')[:50]


songs = {}
for index, artist_seed in enumerate(classic_artists):
    if index % 8 == 0:
        time.sleep(0.4)

    try:
        artist = search_artist(artist_seed['name'])
    except Exception:
        continue

    if artist is None:
        continue

    artist_id = int(artist.get('id') or 0)
    if artist_id <= 0:
        continue

    try:
        tracks = top_tracks_for_artist(artist_id)
    except Exception:
        continue

    for track in tracks:
        track_title = clean_title(str(track.get('title') or ''))
        track_artist = str((track.get('artist') or {}).get('name') or '').strip()

        if not track_title or not track_artist:
            continue

        if track_artist.lower() != artist_seed['name'].lower():
            continue

        duration_seconds = int(track.get('duration') or 0)
        if duration_seconds <= 0:
            continue

        duration_seconds = max(90, min(600, duration_seconds))
        era = artist_seed['era']
        release_year = int(era[:4]) if era[:4].isdigit() else 1980
        release_date = f'{release_year}-01-01'
        dedupe_key = f'{track_title.lower()}|{track_artist.lower()}'

        candidate = {
            'title': track_title,
            'artist': track_artist,
            'genre': normalize_genre(str(artist_seed['genre'])),
            'release_date': release_date,
            'duration_in_seconds': duration_seconds,
            'era': era,
            'energy_level': derive_energy(duration_seconds),
            'original_musical_key': derive_musical_key(track_title, track_artist),
            'classic_source_artist_seed': artist_seed['name'],
        }

        if dedupe_key not in songs:
            songs[dedupe_key] = candidate

songs_by_artist = {}
for song in songs.values():
    songs_by_artist.setdefault(song['artist'], []).append(song)

for artist in songs_by_artist:
    songs_by_artist[artist].sort(key=lambda row: row['title'].lower())

artist_order = sorted(songs_by_artist.keys())
selected = []
round_index = 0
while len(selected) < MAX_SONGS:
    added_in_round = False
    for artist in artist_order:
        artist_tracks = songs_by_artist[artist]
        if round_index >= len(artist_tracks):
            continue

        selected.append(artist_tracks[round_index])
        added_in_round = True

        if len(selected) >= MAX_SONGS:
            break

    if not added_in_round:
        break

    round_index += 1

output = {
    'source': {
        'deezer_artist_search': 'https://api.deezer.com/search/artist?q={artist}',
        'deezer_artist_top': f'https://api.deezer.com/artist/{{artist_id}}/top?limit={TOP_TRACKS_PER_ARTIST}',
        'classic_artist_seed_count': len(classic_artists),
        'max_release_year': 2014,
        'generated_at_utc': datetime.now(UTC).isoformat(),
        'count': len(selected),
    },
    'songs': selected,
}

out_path = os.path.join(os.path.dirname(__file__), 'top_cover_library_songs.json')
os.makedirs(os.path.dirname(out_path), exist_ok=True)
with open(out_path, 'w', encoding='utf-8') as handle:
    json.dump(output, handle, ensure_ascii=False, indent=2)

print(out_path)
print(len(selected))
