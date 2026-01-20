<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');

        // 1. ISP Info - Company information
        $this->command->info('Seeding ISP Info...');
        $this->call(IspInfoSeeder::class);

        // 2. Users - Admin, Collectors, Technicians
        $this->command->info('Seeding Users...');
        $this->call(UserSeeder::class);

        // 3. Routers - Mikrotik routers
        $this->command->info('Seeding Routers...');
        $this->call(RouterSeeder::class);

        // 4. Packages - Internet packages
        $this->command->info('Seeding Packages...');
        $this->call(PackageSeeder::class);

        // 5. Areas - Service areas
        $this->command->info('Seeding Areas...');
        $this->call(AreaSeeder::class);

        // 6. Customers - 200 customers
        $this->command->info('Seeding Customers (200 records)...');
        $this->call(CustomerSeeder::class);

        // 7. Invoices - Monthly invoices for all customers
        $this->command->info('Seeding Invoices...');
        $this->call(InvoiceSeeder::class);

        // 8. Payments - Payment records
        $this->command->info('Seeding Payments...');
        $this->call(PaymentSeeder::class);

        // 9. Expenses - Collector expenses
        $this->command->info('Seeding Expenses...');
        $this->call(ExpenseSeeder::class);

        // 10. Collection Logs - Collection activity logs
        $this->command->info('Seeding Collection Logs...');
        $this->call(CollectionLogSeeder::class);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('Database seeding completed!');
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('Test Accounts:');
        $this->command->info('----------------------------------------');
        $this->command->info('Admin:     admin@javaindonusa.net / password');
        $this->command->info('Finance:   finance@javaindonusa.net / password');
        $this->command->info('Collector: budi@javaindonusa.net / password');
        $this->command->info('Collector: agus@javaindonusa.net / password');
        $this->command->info('----------------------------------------');
        $this->command->info('');
        $this->command->info('Data Summary:');
        $this->command->info('- 1 ISP Info record');
        $this->command->info('- 9 Users (2 admin, 5 collectors, 2 technicians)');
        $this->command->info('- 5 Routers');
        $this->command->info('- 7 Packages');
        $this->command->info('- 17 Areas (4 main + 13 sub-areas)');
        $this->command->info('- 200 Customers');
        $this->command->info('- Invoices for all customers');
        $this->command->info('- Payments and Collection Logs');
        $this->command->info('- Expenses for last 60 days');
    }
}
