<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * The module catalogue + prices (mirrors the financial model). Idempotent.
 * Run standalone: php artisan db:seed --class=Database\\Seeders\\ModuleCatalogueSeeder
 */
class ModuleCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // CUSTOMER — free core + paid modules
            ['customer_core',        'Catalog & Ordering (Core)', 'CUSTOMER', 0,  true,  0],
            ['buying_groups',        'Buying Groups',             'CUSTOMER', 12, false, 1],
            ['pricing_agreements',   'Pricing Agreements',        'CUSTOMER', 12, false, 2],
            ['transfers',            'Transfers',                 'CUSTOMER', 12, false, 3],
            ['ai_analytics',         'AI & Analytics',            'CUSTOMER', 8,  false, 4],
            ['order_chat',           'Order Chat',                'CUSTOMER', 5,  false, 5],
            ['market_feed',          'Market Feed',               'CUSTOMER', 5,  false, 6],

            // PHARMACY MASTER — paid chain base + chain-wide modules
            ['chain_management',     'Chain Management (Base)',   'MASTER', 30, false, 0],
            ['master_buying_groups', 'Buying Groups (chain)',     'MASTER', 20, false, 1],
            ['master_agreements',    'Pricing Agreements (chain)','MASTER', 20, false, 2],
            ['master_transfers',     'Transfers (chain)',         'MASTER', 20, false, 3],
            ['master_ai_analytics',  'AI & Analytics (chain)',    'MASTER', 20, false, 4],

            // LOCAL SUPPLIER — paid core (NOT free) + paid modules
            ['supplier_core',        'Supplier Core (Base)',      'SUPPLIER', 50, false, 0],
            ['supplier_agreements',  'Pricing Agreements',        'SUPPLIER', 20, false, 1],
            ['supplier_buying_groups','Buying Groups',            'SUPPLIER', 10, false, 2],
            ['transfer_qc',          'Transfer QC Console',       'SUPPLIER', 10, false, 3],
            ['foreign_partnerships', 'Foreign Partnerships',      'SUPPLIER', 10, false, 4],
            ['supplier_ai_analytics','AI & Analytics',            'SUPPLIER', 15, false, 5],

            // FOREIGN SUPPLIER — one flat paid plan (NOT free)
            ['foreign_plan',         'Foreign Supplier Plan',     'FOREIGN', 20, false, 0],
        ];

        foreach ($modules as [$key, $name, $scope, $price, $isCore, $sort]) {
            Module::updateOrCreate(['key' => $key], [
                'name' => $name, 'role_scope' => $scope,
                'monthly_price_kd' => $price, 'is_core' => $isCore, 'sort_order' => $sort,
            ]);
        }
    }
}
