<?php

return ['paths' => ['api/*', 'sanctum/csrf-cookie'], 'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], 'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('FRONTEND_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://127.0.0.1:5173')))))), 'allowed_origins_patterns' => [], 'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-XSRF-TOKEN', 'X-Requested-With'], 'exposed_headers' => [], 'max_age' => 600, 'supports_credentials' => true];
