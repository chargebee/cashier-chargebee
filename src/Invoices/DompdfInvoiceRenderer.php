<?php

namespace Chargebee\Cashier\Invoices;

use Chargebee\Cashier\Contracts\InvoiceRenderer;
use Chargebee\Cashier\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;

class DompdfInvoiceRenderer implements InvoiceRenderer
{
    /**
     * {@inheritDoc}
     */
    public function render(Invoice $invoice, array $data = [], array $options = []): string
    {
        if (! defined('DOMPDF_ENABLE_AUTOLOAD')) {
            define('DOMPDF_ENABLE_AUTOLOAD', false);
        }

        $dompdfOptions = new Options;
        $dompdfOptions->setChroot(base_path());

        $dompdf = new Dompdf($dompdfOptions);
        $dompdf->setPaper($options['paper'] ?? 'letter');
        $dompdf->loadHtml($invoice->view($data)->render());
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
