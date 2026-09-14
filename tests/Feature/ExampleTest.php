<?php

test('guests are redirected to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirectToRoute('login');
});

test('the login page uses the Hasira browser tab icon', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<link rel="icon" type="image/svg+xml" href="/icons/hasira-mark.svg?v=2">', false);
});
