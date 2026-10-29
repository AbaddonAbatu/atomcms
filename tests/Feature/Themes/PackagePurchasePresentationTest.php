<?php

use App\Models\Shop\WebsiteShopCategory;
use App\Models\User;

test('each shop card uses the shared purchase controller with safely serialized confirmation data', function (string $theme): void {
    installHotel();
    setSetting('theme', $theme);
    $category = WebsiteShopCategory::create(['name' => 'Credit packs', 'slug' => 'credit-packs', 'is_active' => true]);
    $giftable = makePackage(['website_shop_category_id' => $category->id, 'name' => 'Builder\'s "Bundle" & credits', 'is_giftable' => true]);
    $personal = makePackage(['website_shop_category_id' => $category->id, 'name' => 'Personal Bundle', 'is_giftable' => false]);

    $response = $this->actingAs(User::factory()->create())->get(route('shop.index'));

    $response->assertSee(['x-data="packagePurchase(', 'x-data="recipientSearch()"', 'x-on:input="search()"', 'x-on:keydown="handleKeydown($event)"'], false)
        ->assertSee(['if (!prepareGift(query)) return;', 'open = false;', '$nextTick(() => { confirmOpen = true; });', 'x-on:submit.prevent="preparePurchase()"'], false)
        ->assertSee(['name="receiver" :value="confirmReceiver"', ':action="confirmAction"', 'x-text="confirmMessage"'], false)
        ->assertSee(['\\\\u0027s', '\\\\u0022Bundle\\\\u0022', '\\\\u0026'], false)
        ->assertSee([route('shop.buy-package', $giftable), route('shop.buy-package', $personal)], false)
        ->assertDontSee(['debounceTimer:', '/api/users/search', 'handleKeydown(event)', "confirmAction = '", 'confirmReceiver = query', "tippy('.user-badge')"], false);

    expect(substr_count($response->getContent(), 'x-data="packagePurchase('))->toBe(2);
    expect(substr_count($response->getContent(), 'x-data="recipientSearch()"'))->toBe(1);
    expect(substr_count($response->getContent(), '$dispatch(\'shop:loaded\')'))->toBe(1);
})->with(['atom', 'dusk']);
