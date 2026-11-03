<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'company_name' => 'Acme Corporation',
                'contact_person' => 'Jane Doe',
                'email' => 'billing@acme.test',
                'phone' => '(555) 010-1001',
                'address_line1' => '123 Market Street',
                'address_line2' => 'Suite 200',
                'city' => 'San Francisco',
                'state' => 'California',
                'postal_code' => '94103',
                'country' => 'United States',
                'invoice_prefix' => 'ACME',
            ],
            [
                'company_name' => 'Globex Industries',
                'contact_person' => 'Hank Scorpio',
                'email' => 'accounts@globex.test',
                'phone' => '(555) 010-1002',
                'address_line1' => '500 Industrial Way',
                'address_line2' => null,
                'city' => 'Cypress Creek',
                'state' => 'Oregon',
                'postal_code' => '97000',
                'country' => 'United States',
                'invoice_prefix' => 'GLOB',
            ],
            [
                'company_name' => 'Initech Solutions',
                'contact_person' => 'Peter Gibbons',
                'email' => 'billing@initech.test',
                'phone' => '(555) 010-1003',
                'address_line1' => '4120 Freidrich Lane',
                'address_line2' => null,
                'city' => 'Austin',
                'state' => 'Texas',
                'postal_code' => '78744',
                'country' => 'United States',
                'invoice_prefix' => 'INIT',
            ],
            [
                'company_name' => 'Umbrella Group',
                'contact_person' => 'Alice Abernathy',
                'email' => 'finance@umbrella.test',
                'phone' => '(555) 010-1004',
                'address_line1' => '1 Raccoon City Plaza',
                'address_line2' => null,
                'city' => 'Raccoon City',
                'state' => 'Michigan',
                'postal_code' => '48201',
                'country' => 'United States',
                'invoice_prefix' => 'UMBR',
            ],
            [
                'company_name' => 'Stark Industries',
                'contact_person' => 'Pepper Potts',
                'email' => 'accounts@stark.test',
                'phone' => '(555) 010-1005',
                'address_line1' => '10880 Malibu Point',
                'address_line2' => null,
                'city' => 'Malibu',
                'state' => 'California',
                'postal_code' => '90265',
                'country' => 'United States',
                'invoice_prefix' => 'STAR',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::query()->updateOrCreate(
                ['email' => $customer['email']],
                $customer,
            );
        }
    }
}
