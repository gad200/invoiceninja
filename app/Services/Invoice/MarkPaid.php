<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Invoice\InvoiceWorkflowSettings;
use App\Jobs\Payment\EmailPayment;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Carbon;

class MarkPaid extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {
        if ($this->invoice->status_id == Invoice::STATUS_DRAFT) {
            $this->invoice->service()->markSent()->save();
        }

        /*Don't double pay*/
        if ($this->invoice->status_id == Invoice::STATUS_PAID) {
            return $this->invoice;
        }

        /* Create Payment */
        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $this->invoice->balance;
        $payment->applied = $this->invoice->balance;
        $payment->number = $this->getNextPaymentNumber($this->invoice->client, $payment);
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $this->invoice->client_id;
        $payment->transaction_reference = ctrans('texts.manual_entry');
        $payment->currency_id = $this->invoice->client->getSetting('currency_id');
        $payment->is_manual = true;
        
        $payment_type_id = $this->invoice->client->getSetting('payment_type_id');

        if((int)$payment_type_id > 0)
            $payment->type_id = (int)$payment_type_id;

        $payment->saveQuietly();

        $this->setExchangeRate($payment);

        /* Create a payment relationship to the invoice entity */
        $payment->invoices()->attach($this->invoice->id, [
            'amount' => $payment->amount,
        ]);

        event('eloquent.created: App\Models\Payment', $payment);

        $this->invoice->next_send_date = null;
        
        $this->invoice
                ->service()
                ->setExchangeRate()
                ->updateBalance($payment->amount * -1)
                ->updatePaidToDate($payment->amount)
                ->setStatus(Invoice::STATUS_PAID)
                ->save();

        $this->invoice
                ->service()
                ->applyNumber()
                ->touchPdf()
                ->save();

        $payment->ledger()
                ->updatePaymentBalance($payment->amount * -1);

        $this->invoice
            ->client
            ->service()
            ->updateBalance($payment->amount * -1)
            ->updatePaidToDate($payment->amount)
            ->save();

        $this->invoice
             ->service()
             ->workFlow()
             ->save();

        /* Update Invoice balance */
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->invoice;
    }

    private function setExchangeRate(Payment $payment)
    {

        $client_currency = $payment->client->getSetting('currency_id');
        $company_currency = $payment->client->company->settings->currency_id;

        if ($company_currency != $client_currency) {

            $exchange_rate = new CurrencyApi();

            $payment->exchange_rate = $exchange_rate->exchangeRate($client_currency, $company_currency, Carbon::parse($payment->date));
            //$payment->exchange_currency_id = $client_currency; // 23/06/2021
            $payment->exchange_currency_id = $company_currency;
        
            $payment->save();

        }

    }
}