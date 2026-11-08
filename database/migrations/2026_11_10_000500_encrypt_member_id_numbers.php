<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// TC kimlik numaraları şifreli saklanır; şifreli metin uzun olduğu için kolon TEXT olur.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->text('id_number')->nullable()->change();
        });

        $this->transformIdNumbers(fn (string $plain): string => Crypt::encryptString($plain));
    }

    public function down(): void
    {
        $this->transformIdNumbers(fn (string $encrypted): string => Crypt::decryptString($encrypted));

        Schema::table('members', function (Blueprint $table) {
            $table->string('id_number')->nullable()->change();
        });
    }

    /** @param  callable(string): string  $transform */
    private function transformIdNumbers(callable $transform): void
    {
        DB::transaction(function () use ($transform): void {
            foreach (DB::table('members')->whereNotNull('id_number')->get(['id', 'id_number']) as $member) {
                DB::table('members')->where('id', $member->id)->update(['id_number' => $transform($member->id_number)]);
            }
        });
    }
};
