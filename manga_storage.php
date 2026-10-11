<?php

// Kept outside htdocs so Apache cannot serve private files directly.
function manga_storage_dir(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'mymangalist_uploads';
}

function manga_upload_files(string $type): array
{
    if ($type === 'pdf') {
        $file = $_FILES['pdf'] ?? null;
        if (!is_array($file) || is_array($file['tmp_name'] ?? null)) {
            throw new InvalidArgumentException('กรุณาเลือกไฟล์ PDF หนึ่งไฟล์');
        }
        $files = [$file];
    } elseif ($type === 'images') {
        $pages = $_FILES['pages'] ?? null;
        if (!is_array($pages) || !is_array($pages['tmp_name'] ?? null)) {
            throw new InvalidArgumentException('กรุณาเลือกภาพอย่างน้อยหนึ่งไฟล์');
        }
        $files = [];
        foreach ($pages['tmp_name'] as $index => $tmpName) {
            $files[] = [
                'tmp_name' => $tmpName,
                'error' => $pages['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $pages['size'][$index] ?? 0,
            ];
        }
    } else {
        throw new InvalidArgumentException('ประเภทไฟล์ไม่ถูกต้อง');
    }

    if (count($files) < 1 || count($files) > ($type === 'pdf' ? 1 : 15)) {
        throw new InvalidArgumentException('จำนวนไฟล์เกินกำหนด');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $total = 0;
    $validated = [];
    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('อัปโหลดไฟล์ไม่สำเร็จ');
        }
        $size = (int) ($file['size'] ?? 0);
        $limit = $type === 'pdf' ? 20 * 1024 * 1024 : 5 * 1024 * 1024;
        if ($size < 1 || $size > $limit) {
            throw new InvalidArgumentException('ไฟล์มีขนาดเกินกำหนด');
        }
        $total += $size;
        if ($total > 30 * 1024 * 1024) {
            throw new InvalidArgumentException('ขนาดไฟล์รวมเกิน 30 MB');
        }
        $tmp = (string) $file['tmp_name'];
        $mime = $finfo->file($tmp);
        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime]) || ($type === 'pdf') !== ($mime === 'application/pdf')) {
            throw new InvalidArgumentException('รองรับเฉพาะ PDF หรือภาพ JPG, PNG, WebP');
        }
        if ($type === 'pdf') {
            $handle = fopen($tmp, 'rb');
            $signature = $handle ? fread($handle, 5) : '';
            if ($handle) fclose($handle);
            if ($signature !== '%PDF-') {
                throw new InvalidArgumentException('ไฟล์ PDF ไม่ถูกต้อง');
            }
        } else {
            $image = @getimagesize($tmp);
            if (!$image || $image[0] > 4000 || $image[1] > 20000) {
                throw new InvalidArgumentException('ภาพไม่ถูกต้องหรือมีขนาดใหญ่เกินไป');
            }
        }
        $validated[] = ['tmp' => $tmp, 'mime' => $mime, 'ext' => $extensions[$mime], 'size' => $size];
    }
    return $validated;
}

function manga_delete_stored_files(array $filenames): void
{
    foreach ($filenames as $filename) {
        if (preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png|webp)$/', $filename)) {
            @unlink(manga_storage_dir() . DIRECTORY_SEPARATOR . $filename);
        }
    }
}
