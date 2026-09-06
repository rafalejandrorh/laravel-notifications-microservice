<?php

use App\Channels\Email\SymfonyEmailFactory;

it('maps recipients and embeds inline images', function () {
    $logo = resource_path('images/sivacrim/logo_cicpc.png');

    $email = (new SymfonyEmailFactory)->make(makeRenderedEmail([
        'cc' => [['email' => 'cc@example.com', 'name' => 'CC']],
        'bcc' => [['email' => 'bcc@example.com', 'name' => null]],
        'replyTo' => ['email' => 'reply@example.com', 'name' => 'Reply'],
        'html' => '<p>Hola <img src="cid:logo_cicpc.png"></p>',
        'text' => null,
        'inlineImages' => [
            ['path' => $logo, 'name' => 'logo_cicpc.png', 'mime' => 'image/png'],
        ],
    ]));

    expect($email->getTo()[0]->getAddress())->toBe('user@example.com')
        ->and($email->getCc()[0]->getAddress())->toBe('cc@example.com')
        ->and($email->getBcc()[0]->getAddress())->toBe('bcc@example.com')
        ->and($email->getReplyTo()[0]->getAddress())->toBe('reply@example.com')
        ->and($email->getHtmlBody())->toContain('cid:logo_cicpc.png');

    $names = array_map(fn ($part) => $part->getName(), $email->getAttachments());

    expect($names)->toContain('logo_cicpc.png');
});
