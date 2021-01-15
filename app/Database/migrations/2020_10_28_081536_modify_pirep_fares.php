<?php

use App\Contracts\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModifyPirepFares extends Migration
{
    /**
     * Modify the PIREP fares table so that we can save all of the fares for that particular PIREP
     * Basically copy all of those fields over, and then use this table directly, instead of the
     * relationship to the fares table
     *
     * @return void
     */
    public function up()
    {
        /*
         * Add the columns we need from the fares table so then this is now "fixed" in time
         */
        Schema::table('pirep_fares', function (Blueprint $table) {
            $table->unsignedInteger('fare_id')->change()->nullable()->default(0);

            $table->string('code', 50);
            $table->string('name', 50);

            // count is already there

            $table->unsignedDecimal('price')->nullable()->default(0.00);
            $table->unsignedDecimal('cost')->nullable()->default(0.00);
            $table->unsignedInteger('capacity')->nullable()->default(0);
        });

        /**
         * Now iterate through the existing table and copy/update everything
         * Some fares might already have been removed deleted so just insert some null/errored
         * values for those
         */
        $parent_fares = [];
        $fares = DB::table('pirep_fares')->get();
        foreach ($fares as $fare) {
            if (empty($parent_fares[$fare->fare_id])) {
                $parent_fares[$fare->fare_id] = DB::table('fares')->where('id', $fare->fare_id)->first();
            }
        }
    }

    public function down()
    {
    }
}
