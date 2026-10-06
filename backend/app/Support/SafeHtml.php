<?php

namespace App\Support;

class SafeHtml
{
    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        } $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,strong,em,u,blockquote,h2,h3,h4,ul,ol,li,a[href|title]');
        $config->set('URI.AllowedSchemes', ['https' => true, 'http' => true, 'mailto' => true]);
        $config->set('Cache.SerializerPath', storage_path('framework/cache'));

        return (new \HTMLPurifier($config))->purify($html);
    }
}
