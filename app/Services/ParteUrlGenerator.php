<?php

namespace App\Services;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

class ParteUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        // For 'parte' disk, generate URL as /parte/{hash}/{filename}
        // For other disks, use default behavior
        if ($this->media->disk === 'parte') {
            $url = config('app.url').'/parte/'.$this->getPathRelativeToRoot();

            return $url;
        }

        return parent::getUrl();
    }
}
