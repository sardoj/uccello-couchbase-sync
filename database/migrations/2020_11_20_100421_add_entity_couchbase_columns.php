<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Uccello\Core\Database\Migrations\Migration;

class AddEntityCouchbaseColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table($this->tablePrefix.'entities', function (Blueprint $table) {
            $table->string('couchbase_id')->nullable()->after('data')->index('entities_couchbase_id');
            $table->string('couchbase_rev')->nullable()->after('couchbase_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table($this->tablePrefix.'entities', function (Blueprint $table) {
            $table->dropIndex('entities_couchbase_id');
            $table->dropColumn('couchbase_id');
            $table->dropColumn('couchbase_rev');
        });
    }
}
