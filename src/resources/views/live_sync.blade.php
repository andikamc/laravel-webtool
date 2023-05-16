<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Webtool - Live Sync</title>
    <style>
        body {
            margin: 0;
        }

        iframe {
            display: block;
            background: #000;
            border: none;
            height: 100vh;
            width: 100vw;
        }
    </style>
</head>
<body>
    <iframe src="{{ route('webtool.live-sync.action') }}" style="position:fixed; top:0; left:0; bottom:0; right:0; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden; z-index:999999;">
        Your browser doesn't support iframes
    </iframe>
</body>
</html>