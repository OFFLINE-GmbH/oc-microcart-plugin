<?php namespace OFFLINE\MicroCart\Updates;

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

class CreateOfflineMicrocartItems extends Migration
{
    public function up()
    {
        Schema::create('offline_microcart_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->increments('id')->unsigned();
            $table->integer('cart_id')->unsigned()->nullable()->index();
            $table->string('kind')->nullable()->index();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('price')->default(0);
            $table->integer('total')->default(0);
            $table->integer('subtotal')->default(0);
            $table->decimal('percentage', 5, 4)->nullable();
            $table->integer('tax_amount')->nullable();
            $table->boolean('is_before_tax')->boolean()->default(0);
            $table->boolean('is_tax_free')->boolean()->default(0);
            $table->text('meta')->nullable();
            $table->integer('sort_order')->nullable()->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_microcart_items');
    }
}
