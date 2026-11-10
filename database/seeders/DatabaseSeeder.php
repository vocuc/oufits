<?php

namespace Database\Seeders;

use App\Models\Outfit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $outfits = [
            [
                'file' => 'mau-1.txt',
                'title' => 'Len đen trễ vai + short đen + tất đen',
                'style' => 'Street style kiểu Paris, cá tính, tôn chân dài',
                'hashtags' => 'thudong mauden aolen aotrevai quanshort tatden thatlung tuideovai streetstyle chandai giayboot bootdui bootcaoco',
                'source_title' => '40 套显腿长辣妹 look｜秋冬出门直接抄作业',
                'source_author' => '青禾南枝',
                'source_url' => 'https://www.rednote.com/discovery/item/6acb963c000000001402d8c3',
                'source_position' => 'Ảnh 1, khung thứ 2 hàng trên',
            ],
            [
                'file' => 'mau-2.txt',
                'title' => 'Len kem trễ vai nơ + chân váy xếp ly kem mini',
                'style' => 'Tiểu thư Hàn ngọt ngào, sáng màu, tôn dáng nhỏ người',
                'hashtags' => 'thudong xuan maukem mautrang aolen aotrevai aono chanvay vayxepli vayngan ngoctrai thatlung tieuthu phonghan giayboot bootcaoco bootdalon giaymaryjane',
                'source_title' => '8套｜早春韩系穿搭155cm小个子显高显瘦',
                'source_author' => '芋圆圆不圆',
                'source_url' => 'https://www.rednote.com/discovery/item/69809a2e0000000022030c62',
                'source_position' => 'Ảnh 1, khung đầu hàng trên',
            ],
            [
                'file' => 'mau-3.txt',
                'title' => 'Đầm nhung đen trễ vai mini bèo + chuỗi ngọc trai',
                'style' => 'Tiệc tối sang chảnh, quyến rũ',
                'hashtags' => 'thudong dutiec mauden vay damngan damnhung aotrevai ngoctrai clutch sangchanh quyenru giaycaogot giaymule giaysandal giaydinhda',
                'source_title' => '40套 御姐裙装｜狠狠期待夏天啦',
                'source_author' => '秋橙子',
                'source_url' => 'https://www.rednote.com/discovery/item/681ee5be0000000022005741',
                'source_position' => 'Ảnh 1, khung thứ 2 hàng dưới',
            ],
        ];

        // Outfit mẫu thuộc tài khoản đầu tiên; chưa có tài khoản thì để trống, tài khoản đăng ký đầu tiên sẽ nhận.
        $ownerId = User::orderBy('id')->value('id');

        foreach ($outfits as $data) {
            $outfit = Outfit::updateOrCreate(
                ['user_id' => $ownerId, 'source_url' => $data['source_url'], 'title' => $data['title']],
                [
                    'style' => $data['style'],
                    'source_title' => $data['source_title'],
                    'source_author' => $data['source_author'],
                    'source_position' => $data['source_position'],
                    // prompts/*.txt chỉ chứa item + cách phối; phần dùng chung ở resources/prompts
                    'items' => trim(file_get_contents(__DIR__.'/prompts/'.$data['file'])),
                ]
            );

            $outfit->syncHashtags($data['hashtags']);
        }
    }
}
