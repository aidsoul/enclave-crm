<?php

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Site;
use App\Support\PhoneNumber;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::first() ?? Site::create([
            'name' => 'Основной магазин',
            'domain' => 'shop.example.com',
            'api_token' => (string) Str::uuid(),
        ]);

        $customer1 = Customer::first() ?? Customer::create([
            'site_id' => $site->id,
            'name' => 'Дмитрий Ковалёв',
            'phone' => PhoneNumber::normalize('+375291234567'),
            'email' => 'dmitry@example.com',
        ]);

        $customer2 = Customer::where('id', '!=', $customer1->id)->first();

        $leads = [
            [
                'site_id' => $site->id,
                'customer_id' => $customer1->id,
                'type' => LeadType::CALLBACK,
                'status' => LeadStatus::NEW,
                'name' => $customer1->name,
                'phone' => $customer1->phone,
                'email' => $customer1->email,
                'message' => 'Пожалуйста, перезвоните после 18:00 по поводу доставки в субботу.',
                'page_url' => 'https://shop.example.com/delivery',
                'page_title' => 'Условия доставки — Интернет-магазин',
                'extra_data' => [
                    'utm_source' => 'yandex',
                    'utm_medium' => 'cpc',
                    'utm_campaign' => 'delivery_info',
                ],
            ],
            [
                'site_id' => $site->id,
                'customer_id' => $customer2?->id,
                'type' => LeadType::QUESTION,
                'status' => LeadStatus::NEW,
                'name' => 'Елена Смирнова',
                'phone' => PhoneNumber::normalize('+375297654321'),
                'email' => 'elena.smirnova@example.com',
                'message' => 'Подскажите, есть ли данная модель в белом цвете и действует ли официальная гарантия?',
                'product_name' => 'Кофемашина DeLonghi Magnifica S',
                'product_sku' => 'DL-ECAM-22110B',
                'page_url' => 'https://shop.example.com/product/delonghi-magnifica-s',
                'page_title' => 'Кофемашина DeLonghi Magnifica S — купить',
                'extra_data' => [
                    'utm_source' => 'google',
                    'utm_medium' => 'organic',
                ],
            ],
            [
                'site_id' => $site->id,
                'customer_id' => null,
                'type' => LeadType::ONE_CLICK,
                'status' => LeadStatus::IN_PROGRESS,
                'name' => 'Алексей Морозов',
                'phone' => PhoneNumber::normalize('+375293334455'),
                'email' => null,
                'message' => 'Быстрый заказ с карточки товара в 1 клик',
                'product_name' => 'Смартфон Pixel 9 Pro 256GB',
                'product_sku' => 'PIXEL-9P-256',
                'page_url' => 'https://shop.example.com/product/pixel-9-pro',
                'page_title' => 'Смартфон Pixel 9 Pro — купить',
                'extra_data' => [
                    'utm_source' => 'telegram',
                    'utm_medium' => 'cpc',
                ],
            ],
            [
                'site_id' => $site->id,
                'customer_id' => $customer1->id,
                'type' => LeadType::CONSULTATION,
                'status' => LeadStatus::CONVERTED,
                'name' => $customer1->name,
                'phone' => $customer1->phone,
                'email' => $customer1->email,
                'message' => 'Нужна консультация по выбору робота-пылесоса для квартиры с ковровыми покрытиями.',
                'page_url' => 'https://shop.example.com/catalog/vacuum-cleaners',
                'page_title' => 'Роботы-пылесосы в каталоге',
                'extra_data' => [
                    'utm_source' => 'direct',
                ],
            ],
            [
                'site_id' => $site->id,
                'customer_id' => null,
                'type' => LeadType::CALLBACK,
                'status' => LeadStatus::REJECTED,
                'name' => 'Телемаркетинг',
                'phone' => PhoneNumber::normalize('+375290000000'),
                'email' => null,
                'message' => 'Спам: предложение услуг по контекстной рекламе.',
                'page_url' => 'https://shop.example.com/contacts',
                'page_title' => 'Контакты',
            ],
        ];

        foreach ($leads as $data) {
            Lead::create($data);
        }
    }
}
