<?php

namespace App\Services;

/**
 * PDF テンプレートパス解決。
 * storage / resources / public / DocumentRoot 近傍など、配置差を吸収する。
 */
class PdfTemplatePathResolver
{
    /**
     * @param  string  $logicalName  config('pdf_templates.aliases') のキー
     * @return string 実在する絶対パス
     */
    public function resolve(string $logicalName): string
    {
        $override = trim((string) config("pdf_templates.files.{$logicalName}", ''));
        if ($override !== '') {
            $resolved = $this->firstExisting([$override]);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $filenames = config("pdf_templates.aliases.{$logicalName}");
        if (! is_array($filenames) || $filenames === []) {
            $filenames = [$logicalName];
        }

        $candidates = [];
        foreach ($filenames as $filename) {
            $filename = basename((string) $filename);
            if ($filename === '' || $filename === '.' || $filename === '..') {
                continue;
            }
            foreach ($this->candidateDirectories() as $dir) {
                $candidates[] = $dir.DIRECTORY_SEPARATOR.$filename;
            }
        }

        $resolved = $this->firstExisting($candidates);
        if ($resolved !== null) {
            return $resolved;
        }

        $tried = array_values(array_unique(array_map(
            static fn (string $path) => str_replace('\\', '/', $path),
            $candidates,
        )));

        throw new \RuntimeException(
            "PDFテンプレートが見つかりません: {$logicalName}（".implode(', ', $filenames).'）。'
            .' 探索パス: '.implode(' | ', array_slice($tried, 0, 12))
            .(count($tried) > 12 ? ' ...' : '')
        );
    }

    /**
     * @return list<string>
     */
    private function candidateDirectories(): array
    {
        $dirs = [];

        $configured = trim((string) config('pdf_templates.dir', ''));
        if ($configured !== '') {
            $dirs[] = $this->absolutePath($configured);
        }

        $dirs = array_merge($dirs, [
            storage_path('app/template'),
            storage_path('app/private/template'),
            base_path('storage/app/template'),
            base_path('storage/app/private/template'),
            base_path('resources/pdf-templates'),
            base_path('resources/templates'),
            public_path('templates'),
            public_path('pdf-templates'),
            // Apache: DocumentRoot が public/ のとき storage はひとつ上の兄弟
            dirname(public_path()).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'template',
            dirname(public_path()).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'pdf-templates',
            // base_path が public 配下になっているデプロイ向け
            dirname(base_path()).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'template',
            dirname(base_path()).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'pdf-templates',
        ]);

        $normalized = [];
        foreach ($dirs as $dir) {
            $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $dir), DIRECTORY_SEPARATOR);
            if ($dir === '') {
                continue;
            }
            $normalized[$dir] = $dir;
        }

        return array_values($normalized);
    }

    /**
     * @param  list<string>  $paths
     */
    private function firstExisting(array $paths): ?string
    {
        foreach ($paths as $path) {
            $path = $this->absolutePath((string) $path);
            if ($path === '') {
                continue;
            }
            $real = realpath($path);
            if ($real !== false && is_file($real) && is_readable($real)) {
                return $real;
            }
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function absolutePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        // Windows 絶対パス / UNC / Unix 絶対パス以外は base_path 基準
        $isAbsolute = (bool) preg_match('#^([a-zA-Z]:[\\\\/]|\\\\\\\\|/)#', $path);
        if (! $isAbsolute) {
            $path = base_path($path);
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
