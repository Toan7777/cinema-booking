<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Liệt kê domain cụ thể
    'allowed_origins' => [
        'http://localhost:5173',
        'https://cinema-booking-frontend-eight.vercel.app',
    ],

    // Dùng regex pattern để cho phép MỌI subdomain của Vercel thuộc project này
    // (bao gồm cả preview URL như cinema-booking-frontend-xxxx.vercel.app)
    'allowed_origins_patterns' => [
        '#^https://cinema-booking-frontend.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Token-based auth không cần credentials=true nữa (không dùng cookie)
    'supports_credentials' => false,
];
