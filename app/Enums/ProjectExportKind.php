<?php

namespace App\Enums;

enum ProjectExportKind: string
{
    // Pack zip (SPEC §6.2): the drop-in `components/` + `data/` folder.
    case Pack = 'pack';
    // Scaffold (SPEC §6.3): a complete runnable starter (Next.js / Nuxt).
    case Scaffold = 'scaffold';
}
