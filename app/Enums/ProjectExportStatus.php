<?php

namespace App\Enums;

enum ProjectExportStatus: string
{
    // Queued, waiting for BuildProjectPackZip to assemble the pack zip.
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
