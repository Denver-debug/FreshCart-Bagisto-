<?php

/**
 * FreshCart starter catalog: a Philippine supermarket assortment.
 *
 * categories: key => [name, parent key|null, description]
 * products:   [sku, name, category key, price (PHP), cost (PHP), weight (kg), shelf life days|null, opening stock, featured]
 *
 * Shelf life (days) is used to give the opening stock a batch with an expiry date
 * so FreshCart's expiry monitoring has real data. Prices are indicative retail prices.
 */
return [
    'categories' => [
        'fresh-produce' => ['Fresh Produce', null, 'Fresh vegetables and fruits delivered daily from Mindoro and Batangas farms.'],
        'vegetables' => ['Vegetables', 'fresh-produce', 'Leafy greens, root crops and everyday cooking vegetables.'],
        'fruits' => ['Fruits', 'fresh-produce', 'Local and imported fruits in season.'],

        'meat-seafood' => ['Meat & Seafood', null, 'Fresh and chilled pork, chicken, beef, fish and seafood.'],
        'pork' => ['Pork', 'meat-seafood', 'Fresh pork cuts.'],
        'chicken' => ['Chicken', 'meat-seafood', 'Whole chicken and chicken cuts.'],
        'beef' => ['Beef', 'meat-seafood', 'Beef cuts for stews and grilling.'],
        'fish-seafood' => ['Fish & Seafood', 'meat-seafood', 'Fresh catch from Mindoro waters.'],

        'dairy-eggs' => ['Dairy, Eggs & Chilled', null, 'Milk, cheese, butter, yogurt and eggs.'],
        'milk' => ['Milk', 'dairy-eggs', 'Fresh, UHT, evaporated and condensed milk.'],
        'cheese-butter' => ['Cheese & Butter', 'dairy-eggs', 'Cheese, butter and margarine.'],
        'eggs' => ['Eggs', 'dairy-eggs', 'Fresh chicken and duck eggs.'],

        'bakery' => ['Bakery', null, 'Bread and pastries baked fresh every morning.'],

        'rice-grains' => ['Rice, Grains & Noodles', null, 'Rice, noodles, pasta and cereals.'],
        'rice' => ['Rice', 'rice-grains', 'Local and premium rice varieties.'],
        'noodles-pasta' => ['Noodles & Pasta', 'rice-grains', 'Instant noodles, pancit and pasta.'],

        'pantry' => ['Pantry & Canned Goods', null, 'Canned goods, condiments, oils and baking needs.'],
        'canned-goods' => ['Canned Goods', 'pantry', 'Canned meat, fish and vegetables.'],
        'condiments' => ['Condiments & Sauces', 'pantry', 'Soy sauce, vinegar, fish sauce, ketchup and more.'],
        'cooking-essentials' => ['Cooking Oil, Sugar & Baking', 'pantry', 'Cooking oil, sugar, salt, flour and baking supplies.'],

        'snacks' => ['Snacks & Sweets', null, 'Chips, biscuits, chocolates and local delicacies.'],

        'beverages' => ['Beverages', null, 'Coffee, tea, juices, soft drinks and water.'],
        'coffee-tea' => ['Coffee & Tea', 'beverages', 'Instant, ground coffee and tea.'],
        'drinks' => ['Juices & Soft Drinks', 'beverages', 'Juices, soft drinks and powdered drinks.'],
        'water' => ['Water', 'beverages', 'Purified, mineral and distilled water.'],

        'frozen' => ['Frozen Foods', null, 'Hotdogs, tocino, longganisa, nuggets and ice cream.'],

        'household' => ['Household & Cleaning', null, 'Laundry, dishwashing and home cleaning supplies.'],
        'laundry' => ['Laundry', 'household', 'Detergents and fabric conditioners.'],
        'cleaning' => ['Cleaning Supplies', 'household', 'Dishwashing liquids, cleaners and tissue.'],

        'personal-care' => ['Personal Care', null, 'Bath, hair and oral care.'],

        'baby' => ['Baby & Kids', null, 'Diapers, baby wipes and milk formula.'],
    ],

    'products' => [
        // Vegetables
        ['FC-VEG-001', 'Pechay Tagalog (per bundle)', 'vegetables', 25.00, 16.00, 0.25, 4, 80, false],
        ['FC-VEG-002', 'Kangkong (per bundle)', 'vegetables', 20.00, 12.00, 0.25, 3, 80, false],
        ['FC-VEG-003', 'Red Onion 1kg', 'vegetables', 140.00, 110.00, 1.00, 30, 60, true],
        ['FC-VEG-004', 'Native Garlic 250g', 'vegetables', 45.00, 34.00, 0.25, 60, 70, false],
        ['FC-VEG-005', 'Tomato 1kg', 'vegetables', 90.00, 65.00, 1.00, 7, 50, false],
        ['FC-VEG-006', 'Potato 1kg', 'vegetables', 95.00, 72.00, 1.00, 30, 60, false],
        ['FC-VEG-007', 'Carrots 500g', 'vegetables', 55.00, 40.00, 0.50, 14, 60, false],
        ['FC-VEG-008', 'Eggplant 500g', 'vegetables', 45.00, 32.00, 0.50, 6, 40, false],
        ['FC-VEG-009', 'Ampalaya (Bitter Gourd) 500g', 'vegetables', 60.00, 44.00, 0.50, 6, 40, false],
        ['FC-VEG-010', 'Cabbage (per head)', 'vegetables', 70.00, 50.00, 1.00, 10, 40, false],

        // Fruits
        ['FC-FRU-001', 'Lakatan Banana 1kg', 'fruits', 95.00, 70.00, 1.00, 5, 50, true],
        ['FC-FRU-002', 'Calamansi 250g', 'fruits', 35.00, 24.00, 0.25, 10, 60, false],
        ['FC-FRU-003', 'Carabao Mango 1kg', 'fruits', 160.00, 120.00, 1.00, 5, 40, true],
        ['FC-FRU-004', 'Fuji Apple (per piece)', 'fruits', 35.00, 25.00, 0.20, 21, 100, false],
        ['FC-FRU-005', 'Papaya (per piece)', 'fruits', 65.00, 45.00, 1.20, 5, 30, false],
        ['FC-FRU-006', 'Pineapple (per piece)', 'fruits', 85.00, 60.00, 1.50, 7, 30, false],

        // Meat & seafood
        ['FC-PRK-001', 'Pork Liempo 1kg', 'pork', 380.00, 310.00, 1.00, 3, 30, true],
        ['FC-PRK-002', 'Pork Kasim 1kg', 'pork', 340.00, 275.00, 1.00, 3, 30, false],
        ['FC-PRK-003', 'Ground Pork 500g', 'pork', 165.00, 130.00, 0.50, 2, 30, false],
        ['FC-CHK-001', 'Whole Dressed Chicken 1.2kg', 'chicken', 260.00, 205.00, 1.20, 3, 30, true],
        ['FC-CHK-002', 'Chicken Thigh Fillet 1kg', 'chicken', 280.00, 220.00, 1.00, 3, 30, false],
        ['FC-BEF-001', 'Beef Brisket 1kg', 'beef', 480.00, 390.00, 1.00, 4, 20, false],
        ['FC-FSH-001', 'Bangus (Milkfish) Large', 'fish-seafood', 220.00, 170.00, 0.80, 2, 25, true],
        ['FC-FSH-002', 'Galunggong 1kg', 'fish-seafood', 240.00, 185.00, 1.00, 2, 25, false],
        ['FC-FSH-003', 'Tilapia 1kg', 'fish-seafood', 170.00, 130.00, 1.00, 2, 25, false],
        ['FC-FSH-004', 'Shrimp (Suahe) 500g', 'fish-seafood', 320.00, 250.00, 0.50, 2, 15, false],

        // Dairy & eggs
        ['FC-MLK-001', 'FreshCart Fresh Milk 1L', 'milk', 105.00, 82.00, 1.05, 10, 60, true],
        ['FC-MLK-002', 'UHT Full Cream Milk 1L', 'milk', 98.00, 78.00, 1.05, 180, 80, false],
        ['FC-MLK-003', 'Evaporated Milk 370ml', 'milk', 38.00, 30.00, 0.40, 365, 120, false],
        ['FC-MLK-004', 'Sweetened Condensed Milk 300ml', 'milk', 55.00, 43.00, 0.39, 365, 100, false],
        ['FC-CHS-001', 'Processed Cheese 165g', 'cheese-butter', 72.00, 56.00, 0.17, 180, 80, false],
        ['FC-CHS-002', 'Salted Butter 225g', 'cheese-butter', 185.00, 145.00, 0.23, 120, 40, false],
        ['FC-CHS-003', 'Margarine 250g', 'cheese-butter', 52.00, 40.00, 0.25, 180, 60, false],
        ['FC-EGG-001', 'Fresh Eggs Large (Tray of 30)', 'eggs', 280.00, 225.00, 1.90, 21, 40, true],
        ['FC-EGG-002', 'Fresh Eggs Medium (Dozen)', 'eggs', 105.00, 84.00, 0.70, 21, 50, false],
        ['FC-EGG-003', 'Salted Duck Eggs (6 pcs)', 'eggs', 90.00, 70.00, 0.45, 30, 30, false],

        // Bakery
        ['FC-BKR-001', 'Pandesal (10 pcs)', 'bakery', 45.00, 30.00, 0.30, 2, 60, true],
        ['FC-BKR-002', 'Classic White Sliced Bread 600g', 'bakery', 78.00, 60.00, 0.60, 5, 50, false],
        ['FC-BKR-003', 'Whole Wheat Loaf Bread 600g', 'bakery', 92.00, 70.00, 0.60, 5, 40, false],
        ['FC-BKR-004', 'Ensaymada (4 pcs)', 'bakery', 120.00, 85.00, 0.32, 3, 25, false],
        ['FC-BKR-005', 'Ube Cheese Pandesal (6 pcs)', 'bakery', 95.00, 68.00, 0.36, 3, 25, false],

        // Rice & noodles
        ['FC-RCE-001', 'Well-Milled Rice 5kg', 'rice', 275.00, 235.00, 5.00, 365, 100, true],
        ['FC-RCE-002', 'Premium Jasmine Rice 5kg', 'rice', 360.00, 300.00, 5.00, 365, 80, true],
        ['FC-RCE-003', 'Dinorado Rice 2kg', 'rice', 175.00, 145.00, 2.00, 365, 60, false],
        ['FC-RCE-004', 'Malagkit (Glutinous Rice) 1kg', 'rice', 95.00, 76.00, 1.00, 365, 50, false],
        ['FC-NDL-001', 'Instant Pancit Canton Original 60g', 'noodles-pasta', 16.00, 12.50, 0.06, 240, 300, true],
        ['FC-NDL-002', 'Instant Chicken Noodle Soup 55g', 'noodles-pasta', 13.50, 10.50, 0.06, 240, 300, false],
        ['FC-NDL-003', 'Spaghetti Pasta 1kg', 'noodles-pasta', 98.00, 76.00, 1.00, 540, 80, false],
        ['FC-NDL-004', 'Bihon (Rice Noodles) 500g', 'noodles-pasta', 55.00, 42.00, 0.50, 365, 80, false],

        // Pantry
        ['FC-CAN-001', 'Corned Beef 260g', 'canned-goods', 85.00, 66.00, 0.26, 730, 150, true],
        ['FC-CAN-002', 'Sardines in Tomato Sauce 155g', 'canned-goods', 26.00, 20.00, 0.16, 730, 200, true],
        ['FC-CAN-003', 'Luncheon Meat 340g', 'canned-goods', 155.00, 122.00, 0.34, 730, 100, false],
        ['FC-CAN-004', 'Tuna Flakes in Oil 155g', 'canned-goods', 42.00, 33.00, 0.16, 730, 150, false],
        ['FC-CAN-005', 'Meat Loaf 150g', 'canned-goods', 28.00, 21.50, 0.15, 730, 150, false],
        ['FC-CND-001', 'Soy Sauce 1L', 'condiments', 62.00, 48.00, 1.05, 540, 80, false],
        ['FC-CND-002', 'Cane Vinegar 1L', 'condiments', 48.00, 36.00, 1.05, 540, 80, false],
        ['FC-CND-003', 'Fish Sauce (Patis) 750ml', 'condiments', 58.00, 44.00, 0.80, 540, 60, false],
        ['FC-CND-004', 'Banana Ketchup 320g', 'condiments', 38.00, 29.00, 0.32, 365, 80, false],
        ['FC-CND-005', 'Oyster Sauce 405g', 'condiments', 82.00, 63.00, 0.41, 540, 50, false],
        ['FC-COK-001', 'Coconut Cooking Oil 1L', 'cooking-essentials', 115.00, 92.00, 0.92, 365, 80, true],
        ['FC-COK-002', 'Refined White Sugar 1kg', 'cooking-essentials', 85.00, 70.00, 1.00, 730, 100, false],
        ['FC-COK-003', 'Brown Sugar 1kg', 'cooking-essentials', 78.00, 64.00, 1.00, 730, 100, false],
        ['FC-COK-004', 'Iodized Salt 500g', 'cooking-essentials', 22.00, 16.00, 0.50, 1095, 100, false],
        ['FC-COK-005', 'All-Purpose Flour 1kg', 'cooking-essentials', 62.00, 48.00, 1.00, 365, 60, false],

        // Snacks
        ['FC-SNK-001', 'Potato Chips Original 105g', 'snacks', 72.00, 55.00, 0.11, 180, 100, true],
        ['FC-SNK-002', 'Crackers Family Pack 250g', 'snacks', 68.00, 52.00, 0.25, 270, 80, false],
        ['FC-SNK-003', 'Milk Chocolate Bar 100g', 'snacks', 95.00, 72.00, 0.10, 365, 80, false],
        ['FC-SNK-004', 'Dried Mangoes 100g', 'snacks', 110.00, 82.00, 0.10, 365, 60, true],
        ['FC-SNK-005', 'Chicharon (Pork Rinds) 90g', 'snacks', 65.00, 48.00, 0.09, 120, 60, false],
        ['FC-SNK-006', 'Peanut Brittle (Panutsa) 200g', 'snacks', 55.00, 40.00, 0.20, 120, 40, false],

        // Beverages
        ['FC-COF-001', '3-in-1 Coffee Mix (30 sachets)', 'coffee-tea', 210.00, 168.00, 0.60, 540, 80, true],
        ['FC-COF-002', 'Instant Coffee 100g Jar', 'coffee-tea', 185.00, 148.00, 0.20, 730, 50, false],
        ['FC-COF-003', 'Batangas Barako Ground Coffee 250g', 'coffee-tea', 160.00, 120.00, 0.25, 365, 40, true],
        ['FC-COF-004', 'Ginger Tea (Salabat) 10 sachets', 'coffee-tea', 85.00, 64.00, 0.15, 540, 40, false],
        ['FC-DRK-001', 'Cola Soft Drink 1.5L', 'drinks', 78.00, 62.00, 1.55, 270, 120, false],
        ['FC-DRK-002', 'Orange Juice Drink 1L', 'drinks', 70.00, 54.00, 1.05, 270, 80, false],
        ['FC-DRK-003', 'Powdered Juice Drink Mango 25g (10 pcs)', 'drinks', 95.00, 74.00, 0.25, 540, 60, false],
        ['FC-DRK-004', 'Buko Juice 500ml', 'drinks', 45.00, 32.00, 0.52, 7, 40, false],
        ['FC-WTR-001', 'Purified Drinking Water 500ml (24 pack)', 'water', 220.00, 170.00, 12.50, 730, 60, true],
        ['FC-WTR-002', 'Purified Drinking Water 6L', 'water', 75.00, 56.00, 6.10, 730, 60, false],

        // Frozen
        ['FC-FRZ-001', 'Classic Hotdog 1kg', 'frozen', 245.00, 190.00, 1.00, 90, 50, true],
        ['FC-FRZ-002', 'Pork Tocino 450g', 'frozen', 165.00, 125.00, 0.45, 90, 40, false],
        ['FC-FRZ-003', 'Lucban Longganisa 500g', 'frozen', 180.00, 138.00, 0.50, 90, 40, false],
        ['FC-FRZ-004', 'Chicken Nuggets 400g', 'frozen', 175.00, 135.00, 0.40, 180, 40, false],
        ['FC-FRZ-005', 'Ube Ice Cream 1.5L', 'frozen', 260.00, 195.00, 1.50, 180, 25, false],

        // Household
        ['FC-LND-001', 'Powder Detergent 2kg', 'laundry', 265.00, 210.00, 2.00, null, 60, false],
        ['FC-LND-002', 'Liquid Detergent 1L Refill', 'laundry', 175.00, 138.00, 1.05, null, 50, false],
        ['FC-LND-003', 'Fabric Conditioner 1L', 'laundry', 145.00, 112.00, 1.05, null, 50, false],
        ['FC-CLN-001', 'Dishwashing Liquid Calamansi 500ml', 'cleaning', 85.00, 65.00, 0.52, null, 80, true],
        ['FC-CLN-002', 'Bathroom Tissue 12 Rolls', 'cleaning', 210.00, 165.00, 1.20, null, 60, false],
        ['FC-CLN-003', 'Bleach 1L', 'cleaning', 58.00, 44.00, 1.05, null, 50, false],
        ['FC-CLN-004', 'Multi-Surface Cleaner 500ml', 'cleaning', 125.00, 95.00, 0.52, null, 40, false],

        // Personal care
        ['FC-PRC-001', 'Bath Soap 135g (3 pcs)', 'personal-care', 115.00, 88.00, 0.41, null, 80, false],
        ['FC-PRC-002', 'Shampoo 340ml', 'personal-care', 185.00, 142.00, 0.36, null, 50, false],
        ['FC-PRC-003', 'Toothpaste 150g', 'personal-care', 125.00, 96.00, 0.16, null, 80, false],
        ['FC-PRC-004', 'Toothbrush Soft (2 pcs)', 'personal-care', 85.00, 62.00, 0.05, null, 60, false],

        // Baby
        ['FC-BBY-001', 'Baby Diapers Medium (40 pcs)', 'baby', 520.00, 420.00, 1.80, null, 30, false],
        ['FC-BBY-002', 'Baby Wipes Unscented (80 sheets)', 'baby', 110.00, 84.00, 0.45, null, 40, false],
        ['FC-BBY-003', 'Infant Milk Formula 1-3 Years 900g', 'baby', 690.00, 560.00, 0.95, 540, 25, false],
    ],
];
