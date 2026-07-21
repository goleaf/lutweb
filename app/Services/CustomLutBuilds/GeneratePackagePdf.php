<?php

namespace App\Services\CustomLutBuilds;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class GeneratePackagePdf
{
    public function handle(
        string $path,
        PackageDocumentSnapshot $document,
        PackageName $packageName,
        string $parametersHash,
        string $generatedAtUtc,
    ): void {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setChroot(dirname($path));
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->html($document, $packageName, $parametersHash, $generatedAtUtc), 'UTF-8');
        $dompdf->render();

        $output = $dompdf->output(['compress' => 0]);

        if ($output === '') {
            throw new RuntimeException('Generated PDF output is empty.');
        }

        if (file_put_contents($path, $output) === false) {
            throw new RuntimeException('Unable to write generated PDF.');
        }

        $this->validate($path);
    }

    private function html(PackageDocumentSnapshot $document, PackageName $packageName, string $parametersHash, string $generatedAtUtc): string
    {
        $body = strtr($document->body, [
            '{{ package_name }}' => $packageName->displayName,
            '{{ package_stem }}' => $packageName->stem,
            '{{ document_version }}' => $document->version,
        ]);
        $draftMarker = $document->isDraft()
            ? '<div class="draft">DRAFT - NOT FOR SALE</div>'
            : '';

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            .'body{font-family:"DejaVu Sans",sans-serif;font-size:12px;line-height:1.55;color:#1c1917;margin:34px;}'
            .'h1{font-size:24px;margin:0 0 8px;}h2{font-size:16px;margin:18px 0 6px;}'
            .'.muted{color:#57534e}.draft{border:3px solid #991b1b;color:#991b1b;font-size:20px;font-weight:bold;text-align:center;padding:12px;margin:12px 0 18px;}'
            .'.meta{border-collapse:collapse;width:100%;margin:12px 0 18px}.meta th{text-align:left;width:34%;background:#f5f5f4}.meta th,.meta td{border:1px solid #d6d3d1;padding:6px;}'
            .'.body{white-space:pre-wrap;border-top:1px solid #d6d3d1;padding-top:12px;}'
            .'</style></head><body>'
            .'<p class="muted">LUT Web</p>'
            .$draftMarker
            .'<h1>'.e($document->title).'</h1>'
            .'<table class="meta">'
            .'<tr><th>Custom LUT</th><td>'.e($packageName->displayName).'</td></tr>'
            .'<tr><th>Package stem</th><td>'.e($packageName->stem).'</td></tr>'
            .'<tr><th>Document version</th><td>'.e($document->version).'</td></tr>'
            .'<tr><th>Transform Version</th><td>'.e((string) config('custom-lut-builds.transform_version')).'</td></tr>'
            .'<tr><th>Generated UTC</th><td>'.e($generatedAtUtc).'</td></tr>'
            .'<tr><th>Parameters hash</th><td>'.e(substr($parametersHash, 0, 16)).'</td></tr>'
            .'</table>'
            .'<div class="body">'.e($body).'</div>'
            .'</body></html>';
    }

    private function validate(string $path): void
    {
        $size = filesize($path);

        if ($size === false || $size <= 0 || $size > (int) config('custom-lut-builds.maximum_package_size_bytes', 104_857_600)) {
            throw new RuntimeException('Generated PDF size is invalid.');
        }

        $prefix = file_get_contents($path, false, null, 0, 5);

        if ($prefix !== '%PDF-') {
            throw new RuntimeException('Generated PDF is not a PDF document.');
        }

        $contents = file_get_contents($path);

        if (is_string($contents) && (str_contains($contents, 'http://') || str_contains($contents, 'https://'))) {
            throw new RuntimeException('Generated PDF unexpectedly references a remote URL.');
        }
    }
}
