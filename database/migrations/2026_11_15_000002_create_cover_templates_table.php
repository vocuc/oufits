<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thư viện ảnh bìa lô phối đồ (/cover-templates): mỗi mẫu = ảnh mẫu + cách làm (Claude ghi),
 * trạng thái duyệt (chủ shop duyệt trên web; worker chỉ dùng mẫu "approved") và cột "request" để chủ shop ghi yêu cầu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cover_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 255);
            $table->string('hook_type', 100)->nullable();
            $table->text('how_to')->nullable();
            $table->string('image_path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('request')->nullable();
            $table->timestamp('request_updated_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'code']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cover_templates');
    }
};
