<?php

return [
    'dedup_minutes' => (int) env('ORDER_DEDUP_MINUTES', 20),
    'default_purchase_percent' => (float) env('DEFAULT_PURCHASE_PERCENT', 15.0),
    'valuable_threshold' => (float) env('ORDER_VALUABLE_THRESHOLD', 50000.0),
];
