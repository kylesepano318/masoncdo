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
        $cast = in_array($media->getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'CHAR' : 'TEXT';
        $pattern = '%'.basename($path).'%';

        return PageSection::where('image', $path)->orWhere('mobile_image', $path)->orWhereRaw("CAST(settings AS $cast) LIKE ?", [$pattern])->exists() || Page::whereRaw("CAST(published_sections AS $cast) LIKE ?", [$pattern])->orWhereRaw("CAST(seo AS $cast) LIKE ?", [$pattern])->exists() || Member::where('profile_photo', $path)->exists() || Affiliation::where('logo', $path)->exists() || SiteSetting::whereRaw("CAST(value AS $cast) LIKE ?", [$pattern])->exists() || Celebration::where('image', $path)->orWhereRaw("CAST(gallery AS $cast) LIKE ?", [$pattern])->exists();
    }
}
