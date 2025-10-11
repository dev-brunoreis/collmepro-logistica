<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class LabelPdfService
{
    /**
     * Generate PDF from labels collection
     */
    public function generate($labels): \Barryvdh\DomPDF\PDF
    {
        $data = [
            'labels' => $labels,
            'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
            'total' => $labels->count(),
        ];

        return Pdf::loadView('exports.labels-pdf', $data)
            ->setPaper('a4', 'landscape');
    }

    /**
     * Generate and download PDF
     */
    public function download($labels, string $filename = 'etiquetas.pdf')
    {
        return $this->generate($labels)->download($filename);
    }

    /**
     * Generate and stream PDF to browser
     */
    public function stream($labels, string $filename = 'etiquetas.pdf')
    {
        return $this->generate($labels)->stream($filename);
    }
}

