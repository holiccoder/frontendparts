<?php

namespace App\Enums;

enum ComponentForkStatus: string
{
    // Queued, waiting for BuildComponentForkPreview to start the rebuild.
    case Pending = 'pending';
    // The rebuild is running (vite build + screenshots) right now.
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';
}
