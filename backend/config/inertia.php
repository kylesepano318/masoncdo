<?php

return [
    'ssr' => ['enabled' => false],
    'testing' => ['ensure_pages_exist' => true, 'page_paths' => [base_path('../frontend/src/pages')], 'page_extensions' => ['tsx', 'ts', 'jsx', 'js']],
];
