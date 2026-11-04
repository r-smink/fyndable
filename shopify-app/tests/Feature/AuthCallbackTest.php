<?php

beforeEach(function () {
    configureShopifyTestApp();
});

function signedQuery(array $params): string
{
    ksort($params);
    $message = implode('&', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($params), $params));
    $params['hmac'] = hash_hmac('sha256', $message, TEST_API_SECRET);

    return http_build_query($params);
}

it('sends /install to the Shopify grant screen', function () {
    $this->get('/install?shop='.TEST_SHOP)
        ->assertRedirect('https://'.TEST_SHOP.'/admin/oauth/install?client_id='.TEST_API_KEY);
});

it('rejects /install without a valid shop domain', function () {
    $this->get('/install?shop=evil.example.com')->assertStatus(400);
});

it('forwards a correctly signed /auth/callback to the dashboard', function () {
    $query = signedQuery(['shop' => TEST_SHOP, 'host' => 'YWRtaW4', 'timestamp' => (string) time()]);

    $this->get('/auth/callback?'.$query)
        ->assertRedirect(route('dashboard', ['shop' => TEST_SHOP, 'host' => 'YWRtaW4']));
});

it('rejects /auth/callback with a forged HMAC', function () {
    $this->get('/auth/callback?shop='.TEST_SHOP.'&timestamp=1&hmac=deadbeef')->assertStatus(403);
});
