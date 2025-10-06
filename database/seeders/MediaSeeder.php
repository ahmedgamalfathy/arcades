<?php

namespace Database\Seeders;

use App\Models\Media\Media;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class MediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $media=[// path , type , category
                ['path' => 'avatars/3.png', 'type' => 0, 'category' =>'avatar','created_at' => now(), 'updated_at' => now()],
                ['path' => 'avatars/14.png', 'type' => 0, 'category' =>'avatar','created_at' => now(), 'updated_at' => now()],
                ['path' => 'avatars/15.png', 'type' => 0, 'category' =>'avatar','created_at' => now(), 'updated_at' => now()],
                ['path' => 'avatars/16.png', 'type' => 0, 'category' =>'avatar','created_at' => now(), 'updated_at' => now()],
                ['path' => 'avatars/18.png', 'type' => 0, 'category' =>'avatar','created_at' => now(), 'updated_at' => now()],
                ['path' => 'avatars/19.png', 'type' => 0, 'category' =>'avatar','created_at' => now(), 'updated_at' => now()],
                ['path' => 'avatars/20.png', 'type' => 0, 'category' =>'avatar','created_at' => now(), 'updated_at' => now()],

            ['path' => 'devices/Frame 1618873634.png', 'type' => 0, 'category' =>'devices','created_at' => now(), 'updated_at' => now()],
            ['path' => 'devices/Frame 1618873636.png', 'type' => 0, 'category' =>'devices','created_at' => now(), 'updated_at' => now()],
            ['path' => 'devices/Frame 1618873638.png', 'type' => 0, 'category' =>'devices','created_at' => now(), 'updated_at' => now()],
            ['path' => 'devices/Frame 1618873639.png', 'type' => 0, 'category' =>'devices','created_at' => now(), 'updated_at' => now()],
            ['path' => 'devices/Frame 1618873640.png', 'type' => 0, 'category' =>'devices','created_at' => now(), 'updated_at' => now()],

            ['path' => 'roles/admin.svg', 'type' => 0, 'category' =>'roles','created_at' => now(), 'updated_at' => now()],
            ['path' => 'roles/superAdmin.svg', 'type' => 0, 'category' =>'roles','created_at' => now(), 'updated_at' => now()],

        ];
        foreach ($media as $item) {
            Media::create([
                'path'=>$item['path'],
                'type'=>$item['type'],
                'category'=>$item['category'],
                'created_at'=>$item['created_at'],
                'updated_at'=>$item['updated_at'],
            ]);
        }
    }
}
