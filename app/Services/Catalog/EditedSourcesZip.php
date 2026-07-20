<?php

namespace App\Services\Catalog;

use ZipArchive;

/**
 * Instant download-of-edits (SPEC §5.6): packages the live-edit tab's EDITED
 * sources — posted by the client — into a zip with zero server-side build.
 * The endpoint streams the user's own bytes back; nothing is read from the
 * library tree and no compiler runs, so the download stays instant.
 */
class EditedSourcesZip
{
    /**
     * Build the zip into a temp file and return its path. The caller deletes
     * the file after streaming (`deleteFileAfterSend`).
     *
     * @param  list<array{path: string, code: string}>  $files
     */
    public function build(array $files): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'fp-edited-');

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $zip->addFromString($file['path'], $file['code']);
        }

        $zip->close();

        return $path;
    }
}
