<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

test('parte:download command exists', function () {
    $this->artisan('parte:download --help')
        ->assertExitCode(Response::HTTP_OK);
});

test('parte:download command shows available sources on invalid source', function () {
    $this->artisan('parte:download --source=invalid-source')
        ->expectsOutput('Neplatné zdroje: invalid-source')
        ->assertExitCode(Response::HTTP_UNPROCESSABLE_ENTITY);
});

test('parte:download command accepts valid sources', function () {
    // This test will actually attempt to scrape, so we're just testing it doesn't crash
    // In a real scenario, you would mock the HTTP responses
    $this->artisan('parte:download --source=sadovy-jan')
        ->assertExitCode(Response::HTTP_OK);
});

test('parte:download command can run with --all flag', function () {
    $this->artisan('parte:download --all')
        ->assertExitCode(Response::HTTP_OK);
});
