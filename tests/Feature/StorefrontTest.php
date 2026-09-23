<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_is_the_storefront(): void
    {
        $this->superAdmin();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Home')
                ->has('pharmacy.name')
            );
    }

    public function test_guest_can_browse_products_from_the_pharmacy_catalog(): void
    {
        $this->superAdmin();
        $owner = $this->pharmacyUser();
        app()->instance('current_tenant_id', (string) $owner->tenant_id);

        $category = Category::query()->create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Antalgiques',
        ]);

        Product::query()->create([
            'tenant_id' => $owner->tenant_id,
            'category_id' => $category->id,
            'sku' => 'PARA-500',
            'commercial_name' => 'Paracétamol 500mg',
            'generic_name' => 'Paracétamol',
            'purchase_price' => '2500',
            'sale_price' => '5000',
            'min_stock' => '10',
            'critical_stock' => '3',
            'allocation_strategy' => 'fefo',
        ]);

        $this->get('/produits')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Products')
                ->where('products.data.0.name', 'Paracétamol 500mg')
                ->missing('products.data.0.purchase_price')
            );

        $this->get('/produits?q=paracetamol')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/Products')
                ->where('products.data.0.name', 'Paracétamol 500mg')
                ->has('products.data', 1)
            );
    }

    public function test_contact_form_accepts_a_message(): void
    {
        $this->superAdmin();

        $this->from('/contact')->post('/contact', [
            'name' => 'Marie',
            'email' => 'marie@example.com',
            'phone' => '0810000000',
            'message' => 'Avez-vous du paracétamol ?',
        ])->assertRedirect('/contact')->assertSessionHas('success');
    }

    public function test_about_and_contact_pages_render(): void
    {
        $this->superAdmin();

        $this->get('/a-propos')->assertOk()->assertInertia(fn ($page) => $page->component('Storefront/About'));
        $this->get('/contact')->assertOk()->assertInertia(fn ($page) => $page->component('Storefront/Contact'));
    }

    public function test_staff_login_remains_on_the_login_page(): void
    {
        $this->superAdmin();

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }
}
