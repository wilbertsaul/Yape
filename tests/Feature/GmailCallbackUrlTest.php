<?php

namespace Tests\Feature;

use Tests\TestCase;

class GmailCallbackUrlTest extends TestCase
{
    public function test_el_redirect_uri_de_gmail_usa_app_url(): void
    {
        $this->assertSame(
            rtrim(config('app.url'), '/') . '/gmail/callback',
            route('gmail.callback')
        );
    }
}
