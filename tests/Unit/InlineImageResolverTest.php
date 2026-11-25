<?php

use App\Channels\Email\InlineImageResolver;

it('returns no images when html is empty', function () {
    expect((new InlineImageResolver)->resolve(null))->toBe([])
        ->and((new InlineImageResolver)->resolve(''))->toBe([]);
});

it('embeds sivacrim logos referenced as cid', function () {
    $images = (new InlineImageResolver)->resolve(
        '<img src="cid:logo_cicpc.png"><img src="cid:logo_experticias_telecomunicaciones.png">'
    );

    expect($images)->toHaveCount(2)
        ->and($images[0]['name'])->toBe('logo_cicpc.png')
        ->and($images[0]['mime'])->toBe('image/png')
        ->and(is_file($images[0]['path']))->toBeTrue()
        ->and($images[1]['name'])->toBe('logo_experticias_telecomunicaciones.png');
});

it('skips logos that are not referenced or missing on disk', function () {
    config([
        'email.inline_images' => [
            'logo_cicpc.png' => resource_path('images/sivacrim/logo_cicpc.png'),
            'missing.png' => '/tmp/does-not-exist.png',
        ],
    ]);

    $images = (new InlineImageResolver)->resolve('<img src="cid:missing.png">');

    expect($images)->toBe([]);
});
