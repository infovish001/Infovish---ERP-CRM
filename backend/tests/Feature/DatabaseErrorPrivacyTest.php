<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DatabaseErrorPrivacyTest extends TestCase
{
    public function test_deferred_transaction_failure_reporting_excludes_private_details(): void
    {
        Log::spy();
        report(new \PDOException('Synthetic commit error with private invoice details'));
        Log::shouldHaveReceived('error')->once()->with('Database transaction failed.', ['sqlstate' => null]);
    }

    public function test_query_failure_reporting_excludes_sql_bindings_and_private_database_details(): void
    {
        Log::spy();
        report(new QueryException('tenant', 'insert into crm_leads (email) values (?)', ['private@example.test'], new \PDOException('Synthetic database detail containing private@example.test')));
        Log::shouldHaveReceived('error')->once()->with('Database operation failed.', ['connection' => 'tenant', 'sqlstate' => null]);
    }
}
