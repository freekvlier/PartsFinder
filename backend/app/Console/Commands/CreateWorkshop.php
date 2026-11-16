<?php

namespace App\Console\Commands;

use App\Models\Workshop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workshop:create {name : Name of the workshop} {discount=0 : Discount percentage (0-100)}')]
#[Description('Create a workshop and print its API token')]
class CreateWorkshop extends Command
{
    /**
     * Create a workshop and print its API token.
     *
     * @return int The command exit code.
     */
    public function handle(): int
    {
        $discount = $this->argument('discount');

        if (! is_numeric($discount) || $discount < 0 || $discount > 100) {
            $this->components->error('The discount must be a number between 0 and 100.');

            return self::FAILURE;
        }

        $workshop = Workshop::create([
            'name' => $this->argument('name'),
            'discount_percentage' => $discount,
        ]);

        $this->components->info("Workshop created. API token (shown once): {$workshop->createToken('api')->plainTextToken}");

        return self::SUCCESS;
    }
}
