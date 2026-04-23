# Test Account Chart PDFs

Drop real PDF chart files into the tier subdirectories to have them
automatically uploaded during seeding.

## File naming convention

Each PDF must be named exactly:

    {Song Title} - {Artist}.pdf

The title and artist must match what's in the corresponding seed data JSON.
For example:

    Wonderwall - Oasis.pdf
    Hotel California - The Eagles.pdf
    Sweet Caroline - Neil Diamond.pdf

## Directories

- `free/`  — Charts for Mia Torres (free@test.songtipper.com, 15 songs)
- `basic/` — Charts for Jake Mitchell (basic@test.songtipper.com, 40 songs)
- `pro/`   — Charts for Sarah Chen (pro@test.songtipper.com, 80 songs)

## Notes

- PDFs must be under 2 MB each (the upload limit for all tiers).
- Only songs listed in the corresponding JSON file will be matched.
- You can add charts for just a few songs — not every song needs one.
- The seeder skips charts that already exist, so it's safe to re-run.
- These files are gitignored — they stay local to your machine.
