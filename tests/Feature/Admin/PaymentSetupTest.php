<?php

use App\Models\BankAccount;
use App\Models\Payment;
use App\Models\User;

function paymentSetupAdmin(): User
{
    return User::factory()->admin()->create();
}

it('shows the payment setup console to admins only', function () {
    BankAccount::factory()->create(['bank_name' => 'Sampath Bank']);

    $this->actingAs(paymentSetupAdmin())
        ->get(route('admin.payment-setup.index'))
        ->assertOk()
        ->assertSee('Payment setup')
        ->assertSee('Sampath Bank')
        ->assertSee('Online transfer');

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('admin.payment-setup.index'))
        ->assertForbidden();
});

it('adds a bank account and makes the first one the default', function () {
    $this->actingAs(paymentSetupAdmin())
        ->post(route('admin.payment-setup.bank-accounts.store'), [
            'bank_name' => 'Commercial Bank',
            'account_name' => 'Studylikepro (Pvt) Ltd',
            'account_number' => '8001234567',
            'branch' => 'Colombo 03',
            'note' => 'Use your booking reference',
            'is_active' => '1',
            'sort_order' => '2',
        ])
        ->assertRedirect(route('admin.payment-setup.index'))
        ->assertSessionHas('status', 'bank-account-created');

    $account = BankAccount::query()->firstOrFail();

    expect($account->bank_name)->toBe('Commercial Bank')
        ->and($account->is_active)->toBeTrue()
        ->and($account->is_default)->toBeTrue()
        ->and($account->sort_order)->toBe(2);
});

it('keeps exactly one default account', function () {
    $admin = paymentSetupAdmin();
    $first = BankAccount::factory()->create(['is_default' => true]);
    $second = BankAccount::factory()->create(['is_default' => false]);

    $this->actingAs($admin)
        ->post(route('admin.payment-setup.bank-accounts.default', $second))
        ->assertRedirect();

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->refresh()->is_default)->toBeTrue();

    // Updating another account into the default role moves it too.
    $this->actingAs($admin)
        ->put(route('admin.payment-setup.bank-accounts.update', $first), [
            'bank_name' => $first->bank_name,
            'account_name' => $first->account_name,
            'account_number' => $first->account_number,
            'is_active' => '1',
            'is_default' => '1',
        ])
        ->assertRedirect();

    expect($first->refresh()->is_default)->toBeTrue()
        ->and($second->refresh()->is_default)->toBeFalse();
});

it('hides an account from checkout without deleting it', function () {
    $account = BankAccount::factory()->create(['is_active' => true, 'is_default' => true]);

    $this->actingAs(paymentSetupAdmin())
        ->post(route('admin.payment-setup.bank-accounts.toggle', $account))
        ->assertRedirect();

    expect($account->refresh()->is_active)->toBeFalse();
});

it('refuses to delete an account payments already reference', function () {
    $account = BankAccount::factory()->create();
    Payment::factory()->create(['bank_account_id' => $account->id]);

    $this->actingAs(paymentSetupAdmin())
        ->delete(route('admin.payment-setup.bank-accounts.destroy', $account))
        ->assertRedirect(route('admin.payment-setup.index'))
        ->assertSessionHasErrors('account');

    expect(BankAccount::query()->whereKey($account->id)->exists())->toBeTrue();
});

it('deletes an unused account and hands the default role on', function () {
    $unused = BankAccount::factory()->create();
    $kept = BankAccount::factory()->create(['is_default' => true]);

    $this->actingAs(paymentSetupAdmin())
        ->delete(route('admin.payment-setup.bank-accounts.destroy', $unused))
        ->assertRedirect()
        ->assertSessionHas('status', 'bank-account-deleted');

    expect(BankAccount::query()->whereKey($unused->id)->exists())->toBeFalse()
        ->and($kept->refresh()->is_default)->toBeTrue();
});

it('saves the payment method switches and hold windows', function () {
    $this->actingAs(paymentSetupAdmin())
        ->put(route('admin.payment-setup.settings.update'), [
            'online_transfer_enabled' => '1',
            'bank_deposit_enabled' => '0',
            'card_payment_enabled' => '1',
            'online_transfer_hold_minutes' => '20',
            'bank_deposit_hold_hours' => '48',
            'online_transfer_instructions' => 'Send it before 5 PM.',
            'bank_deposit_instructions' => '',
        ])
        ->assertRedirect(route('admin.payment-setup.index'))
        ->assertSessionHas('status', 'payment-settings-saved');

    expect(platform_settings()->string('bank_deposit_enabled'))->toBe('0')
        ->and(platform_settings()->string('card_payment_enabled'))->toBe('1')
        ->and(platform_settings()->int('online_transfer_hold_minutes'))->toBe(20)
        ->and(platform_settings()->int('bank_deposit_hold_hours'))->toBe(48)
        ->and(platform_settings()->string('online_transfer_instructions'))->toBe('Send it before 5 PM.')
        ->and(platform_settings()->string('bank_deposit_instructions'))->toBe('');
});

it('never accepts an online transfer hold below fifteen minutes', function () {
    $this->actingAs(paymentSetupAdmin())
        ->put(route('admin.payment-setup.settings.update'), [
            'online_transfer_enabled' => '1',
            'bank_deposit_enabled' => '1',
            'card_payment_enabled' => '0',
            'online_transfer_hold_minutes' => '5',
            'bank_deposit_hold_hours' => '24',
        ])
        ->assertSessionHasErrors('online_transfer_hold_minutes');

    expect(platform_settings()->int('online_transfer_hold_minutes'))->toBe(15);
});
