<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Stránka neexistuje - Parte</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <div class="flex flex-col items-center justify-center min-h-screen">
                <h1 class="text-6xl font-bold text-gray-900 mb-4">404</h1>
                <p class="text-2xl text-gray-700 mb-8">Stránka neexistuje</p>
                <a href="/" class="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg transition-colors duration-200">
                    Zpět na hlavní stránku
                </a>
            </div>
        </div>
    </body>
</html>
