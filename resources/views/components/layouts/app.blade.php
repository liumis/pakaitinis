<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Žalos registracija</title>
        
        <style>[x-cloak] { display: none !important; }</style>

        <script src="https://cdn.tailwindcss.com"></script>
        
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: {
                            danger: { 400: '#f87171', 600: '#dc2626' },
                            primary: { 500: '#f59e0b', 600: '#d97706' },
                        }
                    }
                }
            }
        </script>

        @filamentStyles
        @livewireStyles
    </head>
    <body class="antialiased bg-gray-50 text-gray-950 p-8">
        <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow-lg p-6">
            {{ $slot }}
        </div>

        @livewireScripts
        @filamentScripts
    </body>
</html>