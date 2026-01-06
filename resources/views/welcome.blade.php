<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
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
    <body class="bg-gray-100 min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <!-- Header -->
            <header class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">
                    Poslední parte / Smuteční oznámení
                </h1>
                <p class="text-lg text-gray-600">
                    Aktuální parte a úmrtní oznámení z regionu Třinecko a Jablunkovsko
                </p>
            </header>

            <!-- Death Notices Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($deathNotices as $notice)
                    <article class="bg-white border-2 border-gray-300 rounded-lg shadow-lg hover:shadow-xl transition-shadow duration-300">
                        <div class="p-6">
                            <!-- Name and Death Date -->
                            <h2 class="text-xl font-bold text-gray-900 mb-3">
                                {{ $notice->full_name }}
                                @if($notice->death_date)
                                    <span class="text-gray-700">(†{{ $notice->death_date->format('j. n. Y') }})</span>
                                @endif
                            </h2>

                            <!-- Announcement Text -->
                            @if($notice->announcement_text)
                                <div class="text-gray-700 mb-4 whitespace-pre-line text-sm leading-relaxed">
                                    {{ $notice->announcement_text }}
                                </div>
                            @endif

                            <!-- PDF Button -->
                            @if($notice->hasMedia('pdf'))
                                <div class="flex justify-end mt-4 pt-4 border-t border-gray-200">
                                    <a 
                                        href="{{ $notice->getFirstMediaUrl('pdf') }}" 
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg transition-colors duration-200 shadow-md hover:shadow-lg"
                                        title="Otevřít PDF parte"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-gray-500 text-lg">
                            Momentálně nejsou k dispozici žádná parte.
                        </p>
                    </div>
                @endforelse
            </div>

            <!-- Pagination -->
            @if($deathNotices->hasPages())
                <div class="mt-8 flex justify-center">
                    {{ $deathNotices->links() }}
                </div>
            @endif

            <!-- Footer -->
            <footer class="text-center mt-12 pt-8 border-t border-gray-300">
                <p class="text-gray-600">
                    <a href="https://www.bystrice.org" class="text-amber-600 hover:text-amber-700 font-semibold transition-colors">
                        WWW.BYSTRICE.ORG
                    </a>
                </p>
            </footer>
        </div>
    </body>
</html>
