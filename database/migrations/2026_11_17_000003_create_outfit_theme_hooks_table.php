<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Người dùng chốt 2026-11-17: chủ đề CHỈ để gom nhóm; mỗi câu hook là một dòng riêng có hashtag riêng (tìm outfit theo
 * hashtag của hook) và trạng thái duyệt riêng. Hook nói về mùa thì gắn tag mùa (thudong / xuanhe) vào tags của hook.
 * Chuyển dữ liệu: mỗi dòng của outfit_themes.hooks -> 1 hook, tags = tags của chủ đề, status = status của chủ đề;
 * rồi bỏ tags / hooks / season / status / reviewed_at khỏi outfit_themes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outfit_theme_hooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outfit_theme_id')->constrained('outfit_themes')->cascadeOnDelete();
            $table->string('body', 500);           // câu hook = tiêu đề lô, *từ khoá*
            $table->string('tags', 1000);          // hashtag outfit phải có ĐỦ: "chanvaymini tatcaoco thudong"
            $table->string('status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['outfit_theme_id', 'status']);
        });

        $now = now();
        foreach (DB::table('outfit_themes')->orderBy('id')->get() as $theme) {
            $lines = collect(preg_split('/\R/u', (string) $theme->hooks))
                ->map(fn ($h) => trim(preg_replace('/\s+/u', ' ', $h)))->filter()->unique()->values();
            $rows = $lines->map(fn ($body, $i) => [
                'outfit_theme_id' => $theme->id, 'body' => mb_substr($body, 0, 500), 'tags' => (string) $theme->tags,
                'status' => $theme->status ?: 'pending', 'reviewed_at' => $theme->reviewed_at, 'sort' => $i + 1,
                'created_at' => $now, 'updated_at' => $now,
            ])->all();
            if ($rows) {
                DB::table('outfit_theme_hooks')->insert($rows);
            }
        }

        Schema::table('outfit_themes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });
        Schema::table('outfit_themes', function (Blueprint $table) {
            $table->dropColumn(['tags', 'hooks', 'season', 'status', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('outfit_themes', function (Blueprint $table) {
            $table->string('tags', 1000)->default('');
            $table->text('hooks')->nullable();
            $table->string('season', 20)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->index(['user_id', 'status']);
        });
        foreach (DB::table('outfit_themes')->pluck('id') as $id) {
            $hooks = DB::table('outfit_theme_hooks')->where('outfit_theme_id', $id)->orderBy('sort')->orderBy('id')->get();
            DB::table('outfit_themes')->where('id', $id)->update([
                'hooks' => $hooks->pluck('body')->implode("\n"), 'tags' => (string) ($hooks->first()->tags ?? ''),
                'status' => $hooks->contains('status', 'approved') ? 'approved' : 'pending',
            ]);
        }
        Schema::dropIfExists('outfit_theme_hooks');
    }
};
