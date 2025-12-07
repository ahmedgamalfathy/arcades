<?php
namespace App\Services\Select;

use App\Models\Media\Media;

class MediaSelectService
{
//dailynow
    public function getMediaAvatar($categoryName=null)
    {
        $categoryMedia=['1'=>'avatar','2'=>'devices','3'=>'roles'];
        if($categoryName == null){
            return Media::on('tenant')->get(['id as value', 'path as label']);
        }
        $categoryName=$categoryMedia[$categoryName];
        return Media::on('tenant')->where('category',$categoryName)->get(['id as value', 'path']);
    }
    public function getMediaDevices($categoryName=null)
    {
        $categoryMedia=['1'=>'avatar','2'=>'devices','3'=>'roles'];
        if($categoryName == null){
            return Media::on('tenant')->get(['id as value', 'path as label']);
        }
        $categoryName=$categoryMedia[$categoryName];
        return Media::on('tenant')->where('category',$categoryName)->get(['id as value', 'path']);
    }
}
