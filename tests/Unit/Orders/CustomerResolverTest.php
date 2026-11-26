<?php

namespace Tests\Unit\Orders;

use App\Models\Customer;
use App\Models\Site;
use App\Services\Orders\CustomerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerResolverTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerResolver $resolver;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new CustomerResolver;
        $this->site = Site::create([
            'name' => 'Test Site',
            'domain' => 'test.example.com',
            'token' => 'test_token_123',
            'is_active' => true,
        ]);
    }

    public function test_creates_customer_when_not_exists(): void
    {
        $customer = $this->resolver->resolve($this->site->id, [
            'client_name' => 'Иван Иванов',
            'client_phone' => '+7 (999) 111-22-33',
            'client_email' => 'ivan@example.com',
            'client_address' => 'ул. Ленина 10',
        ]);

        $this->assertNotNull($customer);
        $this->assertEquals('Иван Иванов', $customer->name);
        $this->assertEquals('79991112233', $customer->phone);
        $this->assertEquals('ivan@example.com', $customer->email);
        $this->assertEquals('ул. Ленина 10', $customer->address);
        $this->assertEquals($this->site->id, $customer->site_id);
    }

    public function test_finds_and_updates_existing_customer_by_phone(): void
    {
        $existing = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Старое Имя',
            'phone' => '79991112233',
            'email' => 'old@example.com',
        ]);

        $resolved = $this->resolver->resolve($this->site->id, [
            'client_name' => 'Новое Имя',
            'client_phone' => '8 (999) 111-22-33',
            'client_email' => 'new@example.com',
            'client_address' => 'Новый адрес',
        ]);

        $this->assertEquals($existing->id, $resolved->id);
        $this->assertEquals('Новое Имя', $resolved->name);
        $this->assertEquals('new@example.com', $resolved->email);
        $this->assertEquals('Новый адрес', $resolved->address);
    }

    public function test_replaces_placeholder_name_with_actual_name(): void
    {
        $existing = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Клиент',
            'phone' => '79991112233',
        ]);

        $resolved = $this->resolver->resolve($this->site->id, [
            'client_name' => 'Алексей',
            'client_phone' => '79991112233',
        ]);

        $this->assertEquals('Алексей', $resolved->name);
    }

    public function test_does_not_overwrite_real_name_with_placeholder(): void
    {
        $existing = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Реальный Клиент',
            'phone' => '79991112233',
        ]);

        $resolved = $this->resolver->resolve($this->site->id, [
            'client_name' => 'Клиент сайта',
            'client_phone' => '79991112233',
        ]);

        $this->assertEquals('Реальный Клиент', $resolved->name);
    }

    public function test_resolves_by_explicit_customer_id(): void
    {
        $existing = Customer::create([
            'site_id' => $this->site->id,
            'name' => 'Петр',
            'phone' => '79995554433',
        ]);

        $resolved = $this->resolver->resolve($this->site->id, [
            'customer_id' => $existing->id,
            'client_name' => 'Игнорируемое имя',
        ]);

        $this->assertEquals($existing->id, $resolved->id);
    }

    public function test_returns_null_when_no_client_data_provided(): void
    {
        $resolved = $this->resolver->resolve($this->site->id, []);

        $this->assertNull($resolved);
    }
}
