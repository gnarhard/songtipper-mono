<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #000;
            padding: 18pt;
        }

        .header {
            text-align: center;
            margin-bottom: 14pt;
        }

        .header .title {
            font-size: 22pt;
            font-weight: bold;
            margin-bottom: 2pt;
        }

        .header .artist {
            font-size: 13pt;
        }

        .section {
            margin-bottom: 10pt;
        }

        .section-label {
            font-weight: normal;
            font-size: 11pt;
            margin-bottom: 1pt;
        }

        .lyric-line {
            font-size: 11pt;
            line-height: 1.35;
        }

        @if($useColumns)
        .content {
            column-count: 2;
            column-gap: 24pt;
        }

        .section {
            break-inside: avoid;
        }
        @endif
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $title }}</div>
        <div class="artist">{{ $artist }}</div>
    </div>

    <div class="content">
        @foreach($sections as $section)
            <div class="section">
                <div class="section-label">[{{ $section['label'] }}]</div>
                @foreach($section['lines'] ?? [] as $line)
                    @if(is_string($line))
                        <div class="lyric-line">{{ $line }}</div>
                    @elseif(is_array($line))
                        <div class="lyric-line">{{ $line['lyrics'] ?? $line['text'] ?? '' }}</div>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>
</body>
</html>
