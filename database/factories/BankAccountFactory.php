<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'bank_name' => fake()->randomElement(['Commercial Bank', 'Sampath Bank', 'Hatton National Bank', 'Bank of Ceylon']),
            'account_name' => 'Studylikepro (Pvt) Ltd',
            'account_number' => (string) fake()->numerify('##########'),
            'branch' => fake()->randomElement(['Colombo 03', 'Nugegoda', 'Kandy', 'Galle']),
            'note' => null,
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
