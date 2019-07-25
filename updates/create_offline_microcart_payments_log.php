<?php namespace OFFLINE\Mall\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class CreateOfflineMicroCartPaymentsLog extends Migration
{
    public function up()
    {
        Schema::create('offline_microcart_payments_log', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('reference');
            $table->boolean('failed')->default(true);
            $table->string('payment_method')->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('ip')->nullable();
            $table->string('session_id')->nullable();
            $table->text('data')->nullable();
            $table->integer('cart_id')->nullable();
            $table->text('cart_data')->nullable();
            $table->string('message')->nullable();
            $table->string('code')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_microcart_payments_log');
    }
}
