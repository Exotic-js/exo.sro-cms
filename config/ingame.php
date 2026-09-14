<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Battle Pass
    |--------------------------------------------------------------------------
    */
    'battlepass' => [
        // Premium Pass configuration (editable from Admin Panel later)
        'premium_enabled' => false,
        'premium_price' => 500,
        'points_per_purchase' => 1,
        'silk_per_point' => 1,

        // Battle Pass tiers (level = position in array, id must be unique)
        'tiers' => [
            ['id' => 1,  'points' => 10,    'free_item' => ['id' => 4,     'qty' => 10], 'vip_item' => ['id' => 11,    'qty' => 10]],
            ['id' => 2,  'points' => 25,    'free_item' => ['id' => 5,     'qty' => 10], 'vip_item' => ['id' => 12,    'qty' => 10]],
            ['id' => 3,  'points' => 50,    'free_item' => ['id' => 6,     'qty' => 10], 'vip_item' => ['id' => 13,    'qty' => 10]],
            ['id' => 4,  'points' => 100,   'free_item' => ['id' => 7,     'qty' => 10], 'vip_item' => ['id' => 14,    'qty' => 10]],
            ['id' => 5,  'points' => 150,   'free_item' => ['id' => 8,     'qty' => 10], 'vip_item' => ['id' => 15,    'qty' => 10]],
            ['id' => 6,  'points' => 200,   'free_item' => ['id' => 9,     'qty' => 10], 'vip_item' => ['id' => 16,    'qty' => 10]],
            ['id' => 7,  'points' => 300,   'free_item' => ['id' => 71,    'qty' => 1],  'vip_item' => ['id' => 74,    'qty' => 1]],
            ['id' => 8,  'points' => 500,   'free_item' => ['id' => 12928, 'qty' => 1],  'vip_item' => ['id' => 12939, 'qty' => 1]],
            ['id' => 9,  'points' => 1000,  'free_item' => ['id' => 34252, 'qty' => 1],  'vip_item' => ['id' => 34259, 'qty' => 1]],
            ['id' => 10, 'points' => 1500,  'free_item' => ['id' => 25187, 'qty' => 1],  'vip_item' => ['id' => 25194, 'qty' => 1]],
        ],
    ],

];
