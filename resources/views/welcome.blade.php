<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Parte - Úmrtní oznámení Třinecko a Jablunkovsko</title>
        <meta name="description" content="Aktuální parte a úmrtní oznámení z regionu Třinecko a Jablunkovsko. Archiv parte z pohřebních služeb.">
        <meta name="keywords" content="parte, úmrtní oznámení, Třinecko, Jablunkovsko, Třinec, Jablunkov, Bystřice, pohřební služba">
        <meta name="robots" content="index, follow">
        <meta name="author" content="BYSTRICE.ORG">

        <!-- Open Graph -->
        <meta property="og:title" content="Parte - Úmrtní oznámení Třinecko a Jablunkovsko">
        <meta property="og:description" content="Aktuální parte a úmrtní oznámení z regionu Třinecko a Jablunkovsko.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white flex items-center justify-center min-h-screen">
        <div class="text-center">
            <div class="bg-red-600 px-16 py-8 rounded-lg shadow-lg mb-6">
            <h1 class="text-white text-6xl font-bold tracking-wide">WWW.BYSTRICE.ORG</h1>
            <p class="text-white text-xl mt-4 text-center max-w-3xl">
                Aktuální parte a úmrtní oznámení z regionu Třinecko a Jablunkovsko.
                Archiv parte z pohřebních služeb.
            </p>
            <p class="text-white text-lg mt-6 text-center font-semibold">
                Již brzy na tomto místě.
            </p>
        </div>
    </body>
</html>
