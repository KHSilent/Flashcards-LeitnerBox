<!DOCTYPE html>
<html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#2563eb">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="FlashCard">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">

        <title>FlashCard</title>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="apple-touch-icon" href="/icon-192.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <script>
            try {
                const locale = localStorage.getItem('flashcard.locale') === 'en' ? 'en' : 'fa';
                document.documentElement.lang = locale;
                document.documentElement.dir = locale === 'fa' ? 'rtl' : 'ltr';
            } catch (_) {}
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/service-worker.js');
                });
            }
        </script>
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
