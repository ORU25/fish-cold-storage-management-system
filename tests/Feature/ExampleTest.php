<?php

it('redirects the home page to the dashboard', function () {
    $response = $this->get('/');

    $response->assertRedirect('/dashboard');
});
