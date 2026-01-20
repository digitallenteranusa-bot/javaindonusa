<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\IspInfo;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfService
{
    /**
     * Generate Invoice PDF
     */
    public function generateInvoicePdf(Invoice $invoice)
    {
        $invoice->load(['customer', 'customer.area', 'customer.package']);
        $ispInfo = IspInfo::first();

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'ispInfo' => $ispInfo,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Generate Payment Receipt PDF
     */
    public function generatePaymentReceiptPdf(Payment $payment)
    {
        $payment->load(['customer', 'collector', 'receivedBy', 'invoices']);
        $ispInfo = IspInfo::first();

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'payment' => $payment,
            'ispInfo' => $ispInfo,
            'amountInWords' => $this->numberToWords($payment->amount),
        ]);

        $pdf->setPaper([0, 0, 226.77, 600], 'portrait'); // 80mm width receipt

        return $pdf;
    }

    /**
     * Generate bulk invoices PDF
     */
    public function generateBulkInvoicesPdf(array $invoiceIds)
    {
        $invoices = Invoice::with(['customer', 'customer.area'])
            ->whereIn('id', $invoiceIds)
            ->get();

        $ispInfo = IspInfo::first();

        $pdf = Pdf::loadView('pdf.invoices-bulk', [
            'invoices' => $invoices,
            'ispInfo' => $ispInfo,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Convert number to Indonesian words
     */
    protected function numberToWords($number): string
    {
        $number = abs($number);
        $words = [
            '', 'satu', 'dua', 'tiga', 'empat', 'lima',
            'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'
        ];

        if ($number < 12) {
            return $words[$number];
        } elseif ($number < 20) {
            return $words[$number - 10] . ' belas';
        } elseif ($number < 100) {
            return $words[floor($number / 10)] . ' puluh ' . $words[$number % 10];
        } elseif ($number < 200) {
            return 'seratus ' . $this->numberToWords($number - 100);
        } elseif ($number < 1000) {
            return $words[floor($number / 100)] . ' ratus ' . $this->numberToWords($number % 100);
        } elseif ($number < 2000) {
            return 'seribu ' . $this->numberToWords($number - 1000);
        } elseif ($number < 1000000) {
            return $this->numberToWords(floor($number / 1000)) . ' ribu ' . $this->numberToWords($number % 1000);
        } elseif ($number < 1000000000) {
            return $this->numberToWords(floor($number / 1000000)) . ' juta ' . $this->numberToWords($number % 1000000);
        } else {
            return $this->numberToWords(floor($number / 1000000000)) . ' milyar ' . $this->numberToWords($number % 1000000000);
        }
    }
}
