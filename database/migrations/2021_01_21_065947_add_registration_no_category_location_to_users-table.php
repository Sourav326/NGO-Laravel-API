<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRegistrationNoCategoryLocationToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('location')->after('phone')->nullable();
            $table->string('registration_no')->nullable()->after('location');
            $table->integer('category')->nullable()->after('registration_no');
            $table->string('bio')->nullable()->after('category');
            $table->string('occupation')->nullable()->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('location');
            $table->dropColumn('registration_no');
            $table->dropColumn('category');
            $table->dropColumn('bio');
            $table->dropColumn('occupation');
        });
    }
}
