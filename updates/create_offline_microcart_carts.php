<?php namespace OFFLINE\MicroCart\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class CreateOfflineMicrocartCarts extends Migration
{
    public function up()
    {
        Schema::create('offline_microcart_carts', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('session_id')->nullable();

            $table->string('email')->nullable();

            $table->string('shipping_company')->nullable();
            $table->string('shipping_firstname')->nullable();
            $table->string('shipping_lastname')->nullable();
            $table->text('shipping_lines')->nullable();
            $table->string('shipping_zip', 20)->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_country')->nullable();

            $table->boolean('billing_differs')->default(0);
            $table->string('billing_company')->nullable();
            $table->string('billing_firstname')->nullable();
            $table->string('billing_lastname')->nullable();
            $table->text('billing_lines')->nullable();
            $table->string('billing_zip', 20)->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_country')->nullable();

            $table->integer('payment_id')->nullable();
            $table->integer('payment_method_id')->nullable();
            $table->string('payment_state')->nullable();

            $table->string('currency')->nullable();

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_microcart_carts');
    }
}
