<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FastXHorizon | Store Coming Soon</title>
    <style>
        *{box-sizing:border-box}html,body{min-height:100%;margin:0}body{background:#f7faff;color:#172033;font-family:Arial,sans-serif}.coming-soon{min-height:100vh;display:grid;place-items:center;padding:32px 20px}.content{width:min(100%,720px);text-align:center}.logo{max-width:min(280px,75vw);max-height:100px;object-fit:contain;margin-bottom:42px}h1{margin:0 0 18px;color:#1677e8;font-size:clamp(34px,6vw,64px);line-height:1.1}p{margin:0 auto;max-width:560px;color:#64748b;font-size:clamp(17px,2.5vw,22px);line-height:1.6}
    </style>
</head>
<body><main class="coming-soon"><div class="content">
    <img class="logo" src="{{ getStorageImages(path: $web_config['web_logo'], type: 'logo') }}" alt="{{ $web_config['company_name'] }}">
    <h1>Store Coming Soon</h1><p>We are preparing something special for you. Please check back soon.</p>
</div></main></body>
</html>
