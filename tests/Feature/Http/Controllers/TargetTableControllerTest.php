<?php

use App\Models\TabMapping;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('rows of a configured target table are shown', function () {
    TabMapping::factory()->create(['target_table' => 'sales']);
    Schema::create('sales', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    DB::table('sales')->insert(['name' => 'Apple']);

    $this->get(route('tables.show', 'sales'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tables/show')
            ->where('columns', ['id', 'name'])
            ->where('rows.data.0.name', 'Apple'));
});

test('tables that are not sync targets cannot be viewed', function () {
    $this->get(route('tables.show', 'sessions'))
        ->assertNotFound();
});
