<?php namespace OFFLINE\MicroCart\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateOfflineMicrocartPaymentMethods extends Migration
{
    public function up()
    {
        Schema::create('offline_microcart_payment_methods', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->boolean('is_default')->default(0);
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->text('payment_provider');
            $table->string('label', 255)->nullable();
            $table->integer('price')->nullable();
            $table->decimal('percentage', 7, 4)->nullable();
            $table->integer('sort_order')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('offline_microcart_payment_methods');
    }
}
