<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kho kiến thức phối đồ Claude học được (D:\FlowKit\a51-skills\outfit-video\thiet-ke-kien-thuc.txt):
 * - styling_rules: "<loại giày> + <khía cạnh> = <giá trị> thì hợp / rất hợp / nên tránh"
 * - styling_ideas: ý tưởng phối học từ 1 ảnh (TikTok, OOTD...) kèm cách thực hiện
 * Thuộc tài khoản sở hữu token (mọi shop dùng chung 1 tài khoản OOTD).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('styling_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shoe_type', 40)->nullable(); // slug loại giày (giaysneaker, bootcaoco...); null = nguyên tắc chung
            $table->string('shoe_key', 40)->default(''); // = shoe_type ?? '' để khoá unique dùng được khi shoe_type null
            $table->string('dimension', 20); // occasion | season | style | bottom | outer | color | socks | age | taste | pose | other
            $table->string('value', 60);
            $table->string('relation', 10); // good | best | avoid
            $table->decimal('score', 4, 3)->default(0);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->text('note')->nullable();
            $table->json('examples')->nullable();
            $table->string('source', 20); // ootd_stats | tiktok | user | claude
            $table->boolean('is_confirmed')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'shoe_key', 'dimension', 'value', 'source']);
            $table->index(['user_id', 'dimension', 'value']);
        });

        Schema::create('styling_ideas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_key', 120); // nguồn + id bài + vị trí ảnh (vd tiktok:7699878033842867476:3); trùng = cập nhật
            $table->string('title', 200);
            $table->text('idea'); // ý tưởng: vì sao đẹp, điểm nhấn, cảm giác
            $table->text('how_to'); // cách thực hiện: từng món đồ (màu, dáng, chất liệu), cách mặc, phụ kiện, tất, dáng chụp
            $table->json('shoe_types')->nullable();
            $table->json('occasions')->nullable();
            $table->json('seasons')->nullable();
            $table->json('styles')->nullable();
            $table->json('colors')->nullable();
            $table->json('tags')->nullable();
            $table->string('age_range', 10)->nullable(); // vd 18-25
            $table->string('source', 20); // tiktok | ootd | user | claude
            $table->string('source_url', 500)->nullable();
            $table->string('source_author', 100)->nullable();
            $table->string('image_ref', 255)->nullable(); // file ảnh đã học, vd miumiuu5_7699878033842867476_03.jpg
            $table->foreignId('outfit_id')->nullable()->constrained()->nullOnDelete(); // outfit OOTD tạo từ ý tưởng này
            $table->unsignedTinyInteger('rating')->default(3); // 1..5
            $table->boolean('is_confirmed')->default(false);
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'source_key']);
            $table->index(['user_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('styling_ideas');
        Schema::dropIfExists('styling_rules');
    }
};
