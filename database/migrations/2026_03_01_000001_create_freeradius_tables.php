<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'radius';

    public function up(): void
    {
        if (!Schema::connection('radius')->hasTable('radcheck')) {
            Schema::connection('radius')->create('radcheck', function (Blueprint $table) {
                $table->id();
                $table->string('username', 64)->default('')->index();
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default(':=');
                $table->string('value', 253)->default('');
            });
        }

        if (!Schema::connection('radius')->hasTable('radreply')) {
            Schema::connection('radius')->create('radreply', function (Blueprint $table) {
                $table->id();
                $table->string('username', 64)->default('')->index();
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default(':=');
                $table->string('value', 253)->default('');
            });
        }

        if (!Schema::connection('radius')->hasTable('radgroupcheck')) {
            Schema::connection('radius')->create('radgroupcheck', function (Blueprint $table) {
                $table->id();
                $table->string('groupname', 64)->default('')->index();
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default(':=');
                $table->string('value', 253)->default('');
            });
        }

        if (!Schema::connection('radius')->hasTable('radgroupreply')) {
            Schema::connection('radius')->create('radgroupreply', function (Blueprint $table) {
                $table->id();
                $table->string('groupname', 64)->default('')->index();
                $table->string('attribute', 64)->default('');
                $table->char('op', 2)->default(':=');
                $table->string('value', 253)->default('');
            });
        }

        if (!Schema::connection('radius')->hasTable('radusergroup')) {
            Schema::connection('radius')->create('radusergroup', function (Blueprint $table) {
                $table->id();
                $table->string('username', 64)->default('')->index();
                $table->string('groupname', 64)->default('');
                $table->integer('priority')->default(1);
            });
        }

        if (!Schema::connection('radius')->hasTable('radacct')) {
            Schema::connection('radius')->create('radacct', function (Blueprint $table) {
                $table->bigIncrements('radacctid');
                $table->string('acctsessionid', 64)->default('');
                $table->string('acctuniqueid', 32)->default('')->unique();
                $table->string('username', 64)->default('')->index();
                $table->string('realm', 64)->default('');
                $table->string('nasipaddress', 15)->default('')->index();
                $table->unsignedInteger('nasportid')->nullable();
                $table->string('nasporttype', 32)->nullable();
                $table->dateTime('acctstarttime')->nullable()->index();
                $table->dateTime('acctupdatetime')->nullable();
                $table->dateTime('acctstoptime')->nullable()->index();
                $table->unsignedInteger('acctinterval')->nullable();
                $table->unsignedInteger('acctsessiontime')->nullable();
                $table->string('acctauthentic', 32)->nullable();
                $table->string('connectinfo_start', 128)->nullable();
                $table->string('connectinfo_stop', 128)->nullable();
                $table->unsignedBigInteger('acctinputoctets')->nullable();
                $table->unsignedBigInteger('acctoutputoctets')->nullable();
                $table->string('calledstationid', 50)->default('');
                $table->string('callingstationid', 50)->default('');
                $table->string('acctterminatecause', 32)->default('');
                $table->string('servicetype', 32)->nullable();
                $table->string('framedprotocol', 32)->nullable();
                $table->string('framedipaddress', 15)->default('')->index();
                $table->string('framedipv6address', 45)->default('');
                $table->string('framedipv6prefix', 45)->default('');
                $table->string('framedinterfaceid', 44)->default('');
                $table->string('delegatedipv6prefix', 45)->default('');
                $table->string('class', 64)->default('');
            });
        }

        if (!Schema::connection('radius')->hasTable('radpostauth')) {
            Schema::connection('radius')->create('radpostauth', function (Blueprint $table) {
                $table->id();
                $table->string('username', 64)->default('');
                $table->string('pass', 64)->default('');
                $table->string('reply', 32)->default('');
                $table->timestamp('authdate')->useCurrent();
                $table->string('class', 64)->default('');

                $table->index('username');
            });
        }

        if (!Schema::connection('radius')->hasTable('nas')) {
            Schema::connection('radius')->create('nas', function (Blueprint $table) {
                $table->id();
                $table->string('nasname', 128)->index();
                $table->string('shortname', 32)->nullable();
                $table->string('type', 30)->default('other');
                $table->integer('ports')->nullable();
                $table->string('secret', 60)->default('secret');
                $table->string('server', 64)->nullable();
                $table->string('community', 50)->nullable();
                $table->string('description', 200)->default('RADIUS Client');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('radius')->dropIfExists('radpostauth');
        Schema::connection('radius')->dropIfExists('radacct');
        Schema::connection('radius')->dropIfExists('radusergroup');
        Schema::connection('radius')->dropIfExists('radgroupreply');
        Schema::connection('radius')->dropIfExists('radgroupcheck');
        Schema::connection('radius')->dropIfExists('radreply');
        Schema::connection('radius')->dropIfExists('radcheck');
        Schema::connection('radius')->dropIfExists('nas');
    }
};
