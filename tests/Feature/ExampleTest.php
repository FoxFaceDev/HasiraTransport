<?php

test('guests are redirected to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirectToRoute('login');
});
