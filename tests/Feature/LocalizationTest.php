<?php

declare(strict_types=1);

test('it serves the ui in english by default', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Start state')
        ->assertDontSee('Startzustand');
});

test('it serves the ui in german when the browser prefers it', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8')
        ->get('/')
        ->assertSuccessful()
        ->assertSee('Startzustand')
        ->assertSee('Züge');
});

test('it falls back to english for unsupported languages', function () {
    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get('/')
        ->assertSuccessful()
        ->assertSee('Start state');
});

test('it respects the browser preference order', function () {
    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9,de;q=0.5')
        ->get('/')
        ->assertSuccessful()
        ->assertSee('Start state')
        ->assertDontSee('Startzustand');
});

test('the landing page carries open graph metadata', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('property="og:title"', false)
        ->assertSee('property="og:image"', false)
        ->assertSee('og-image.png', false)
        ->assertSee('name="twitter:card"', false);
});

test('every english source string has a german translation', function () {
    $translations = json_decode(file_get_contents(lang_path('de.json')), true, flags: JSON_THROW_ON_ERROR);

    $source = file_get_contents(resource_path('views/pages/⚡lock-picker.blade.php'));
    preg_match_all("/(?:__|trans_choice)\\('((?:[^'\\\\]|\\\\.)+)'/", $source, $matches);

    $missing = array_filter(
        array_map(static fn (string $key): string => stripslashes($key), array_unique($matches[1])),
        static fn (string $key): bool => ! array_key_exists($key, $translations),
    );

    expect(array_values($missing))->toBe([]);
});
