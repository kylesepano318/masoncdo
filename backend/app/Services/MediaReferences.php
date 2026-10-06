<?php

namespace App\Services;

use App\Models\Affiliation;
use App\Models\Celebration;
use App\Models\Media;
use App\Models\Member;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\SiteSetting;

class MediaReferences
{
    public function inUse(Media $media): bool
    {
        $path = $media->path;
        $pattern = '%'.basename($path).'%';

        return PageSection::where('image', $path)->orWhere('mobile_image', $path)->orWhereRaw('CAST(settings AS TEXT) LIKE ?', [$pattern])->exists() || Page::whereRaw('CAST(published_sections AS TEXT) LIKE ?', [$pattern])->orWhereRaw('CAST(seo AS TEXT) LIKE ?', [$pattern])->exists() || Member::where('profile_photo', $path)->exists() || Affiliation::where('logo', $path)->exists() || SiteSetting::whereRaw('CAST(value AS TEXT) LIKE ?', [$pattern])->exists() || Celebration::where('image', $path)->orWhereRaw('CAST(gallery AS TEXT) LIKE ?', [$pattern])->exists();
    }
}
