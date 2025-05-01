<?php

namespace App\Models;


trait SingleFileFunctions
{

    public function singleFile()
    {
        return $this->morphMany(SingleFile::class, 'parent');
    }
    public function uploadFile($videoLink, $thumbLink = null, $mediaType)
    {

        $this->singleFile()->create([
            'aws_link' => $videoLink,
            'thumbnail' => $thumbLink,
            'media_type' => $mediaType,
            'parent_id' => $this->id,
            'parent_type' => get_class($this)
        ]);
    }
}
