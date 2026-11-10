<?php

namespace Database\Seeders;

use App\Models\Outfit;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $commonFix = <<<'TXT'
Giày bị sai:
Giữ nguyên toàn bộ ảnh, chỉ sửa giày thành đúng đôi trong ảnh tôi tải lên: cùng màu, cùng dáng, cùng chất liệu, cùng chi tiết.

Giày bị che / bị cắt:
Lùi khung hình để thấy trọn từ đỉnh đầu tới hết bàn chân và một phần sàn; giày phải nét, không bị váy, túi hay tay che.

Bị lộ mặt:
Chiếc iPhone màu trắng phải che kín toàn bộ khuôn mặt người mẫu.

Quần áo / túi hiện logo:
Xóa toàn bộ logo và chữ trên quần áo, túi. Chỉ giữ logo trên giày.
TXT;

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
                'fix' => 'Áo len đen oversize trễ vai dài phủ mông, thắt lưng đen khóa bạc ngang eo đè lên áo, short đen gần như bị che, tất đen mỏng suốt chân, túi hobo da đen đeo vai bên trái ảnh.',
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
                'fix' => 'Áo len kem trễ vai có dải gấp và nơ to ở ngực bên trái ảnh, sơ vin vào chân váy xếp ly kem mini cạp cao, thắt lưng đen khóa vàng ở cạp, choker ngọc trai, tóc búi thấp, chân trần.',
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
                'fix' => 'Đầm nhung đen trễ vai tay dài ôm sát, chân váy satin đen phồng bèo mini, hai chuỗi ngọc trai (một ngắn sát cổ, một dài tới giữa ngực), clutch satin đen tay bên trái ảnh, chân trần.',
            ],
        ];

        foreach ($outfits as $data) {
            $outfit = Outfit::updateOrCreate(
                ['source_url' => $data['source_url'], 'title' => $data['title']],
                [
                    'style' => $data['style'],
                    'source_title' => $data['source_title'],
                    'source_author' => $data['source_author'],
                    'source_position' => $data['source_position'],
                    'prompt' => trim(file_get_contents(__DIR__.'/prompts/'.$data['file'])),
                    'fix_prompt' => "Sai cách mặc:\n".$data['fix']."\n\n".$commonFix,
                ]
            );

            $outfit->syncHashtags($data['hashtags']);
        }
    }
}
