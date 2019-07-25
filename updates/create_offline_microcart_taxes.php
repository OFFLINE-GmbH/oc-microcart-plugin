<?php namespace OFFLINE\MicroCart\Updates;

use October\Rain\Database\Updates\Migration;
use Schema;

class CreateOfflineMicrocartTaxes extends Migration
{
    public function up()
    {
        Schema::create('offline_microcart_taxes', function ($table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->string('name', 255);
            $table->decimal('percentage', 7, 4);
            $table->boolean('is_default')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('offline_microcart_payment_method_tax', function ($table) {
            $table->increments('id')->unsigned();
            $table->integer('payment_method_id');
            $table->integer('tax_id');
        });
        Schema::create('offline_microcart_cart_item_tax', function ($table) {
            $table->increments('id')->unsigned();
            $table->integer('cart_item_id');
            $table->integer('tax_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_microcart_taxes');
        Schema::dropIfExists('offline_microcart_payment_method_tax');
        Schema::dropIfExists('offline_microcart_cart_item_tax');
    }
}
