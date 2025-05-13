<?php

namespace App\Models;


trait SingleFileOne
{
    public function singleFileOne()
    {
        return $this->morphOne(SingleFile::class, 'parent');
    }
    public function uploadStory($videoLink, $thumbLink = null, $mediaType)
    {
        $this->singleFileOne()->create([
            'aws_link' => $videoLink,
            'thumbnail' => $thumbLink,
            'media_type' => $mediaType,
            // 'parent_id' => $this->id,
            // 'parent_type' => get_class($this)       
        ]);
    }
}
